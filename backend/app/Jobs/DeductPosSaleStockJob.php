<?php

namespace App\Jobs;

use App\Events\PosStockUpdatedEvent;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeductPosSaleStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param int|string $saleId ID of the PosSale
     * @param string|null $tenantId Optional tenant ID
     */
    public function __construct(
        public int|string $saleId,
        public ?string $tenantId = null
    ) {
        $this->tenantId = $tenantId ?: (tenant('id') ?: null);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->tenantId && (! tenancy()->initialized || tenant('id') !== $this->tenantId)) {
                $tenant = Tenant::find($this->tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }

            $connectionName = tenant()?->database_connection_name ?? 'tenant';
            $affectedItemIds = [];
            $clientToken = null;

            DB::connection($connectionName)->transaction(function () use (&$affectedItemIds, &$clientToken) {
                /** @var PosSale|null $sale */
                $sale = PosSale::query()
                    ->with(['items'])
                    ->lockForUpdate()
                    ->find($this->saleId);

                if (! $sale) {
                    Log::warning("[DeductPosSaleStockJob] PosSale #{$this->saleId} not found.");
                    return;
                }

                $clientToken = $sale->client_token;

                // Idempotency check: if stock was already deducted for this sale, do not re-deduct.
                if ($sale->stock_deducted_at !== null) {
                    Log::info("[DeductPosSaleStockJob] PosSale #{$this->saleId} stock already deducted at {$sale->stock_deducted_at}. Skipping.");
                    return;
                }

                foreach ($sale->items as $saleItem) {
                    $itemId = $this->deductStockForItem($saleItem);
                    if ($itemId) {
                        $affectedItemIds[] = $itemId;
                    }
                }

                $sale->update(['stock_deducted_at' => now()]);

                Log::info("[DeductPosSaleStockJob] Successfully deducted stock for PosSale #{$this->saleId}.");
            });

            // Broadcast real-time stock update event via Soketi/Echo
            if (! empty($affectedItemIds)) {
                try {
                    broadcast(new PosStockUpdatedEvent(
                        array_values(array_unique(array_filter($affectedItemIds))),
                        $clientToken,
                        $this->tenantId
                    ));
                } catch (Throwable $broadcastError) {
                    Log::warning("[DeductPosSaleStockJob] Could not broadcast PosStockUpdatedEvent: " . $broadcastError->getMessage());
                }
            }
        } catch (Throwable $e) {
            Log::error("[DeductPosSaleStockJob] Failed to deduct stock for PosSale #{$this->saleId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Deduct stock for an individual POS sale item line.
     */
    protected function deductStockForItem(PosSaleItem $saleItem): ?int
    {
        $itemId = $saleItem->item_id;
        $variantId = $saleItem->item_variant_id;
        $soldQty = (float) $saleItem->quantity;

        if (! $itemId && ! $variantId) {
            return null;
        }

        /** @var Item|null $item */
        $item = $itemId
            ? Item::query()->with(['uomGroup.units.unit'])->lockForUpdate()->find($itemId)
            : null;

        /** @var ItemVariant|null $variant */
        $variant = $variantId
            ? ItemVariant::query()->with('item')->lockForUpdate()->find($variantId)
            : null;

        if (! $item && $variant?->item) {
            $item = $variant->item;
        }

        // Resilient Fallback: If variant was not resolved by ID, but the item has variants,
        // recover the matching variant by SKU, selected_options, or name.
        if (! $variant && $item) {
            $itemVariants = ItemVariant::query()
                ->with('optionValues')
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->get();

            if ($itemVariants->isNotEmpty()) {
                // 1. Try match by SKU (if line SKU differs from parent item SKU)
                if (filled($saleItem->sku) && $saleItem->sku !== $item->sku) {
                    $variant = $itemVariants->first(function (ItemVariant $v) use ($saleItem) {
                        return strtolower(trim((string) $v->sku)) === strtolower(trim((string) $saleItem->sku));
                    });
                }

                // 2. Try match by selected_options IDs or names
                $selectedOptions = $saleItem->selected_options;
                if (! $variant && ! empty($selectedOptions) && is_array($selectedOptions)) {
                    $optIds = collect();
                    foreach ($selectedOptions as $opt) {
                        $id = is_array($opt) ? ($opt['id'] ?? null) : (is_numeric($opt) ? (int) $opt : null);
                        if (is_numeric($id)) {
                            $optIds->push((int) $id);
                        }
                    }

                    if ($optIds->isNotEmpty()) {
                        $variant = $itemVariants->first(function (ItemVariant $v) use ($optIds) {
                            $vIds = $v->optionValues->pluck('id')->map(fn ($id) => (int) $id);
                            return $vIds->isNotEmpty()
                                && $vIds->count() === $optIds->count()
                                && $optIds->diff($vIds)->isEmpty();
                        });
                    }

                    if (! $variant) {
                        $optNames = collect();
                        foreach ($selectedOptions as $opt) {
                            $name = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? null) : (is_string($opt) ? $opt : null);
                            if (filled($name)) {
                                $optNames->push(strtolower(trim((string) $name)));
                            }
                        }

                        if ($optNames->isNotEmpty()) {
                            $variant = $itemVariants->first(function (ItemVariant $v) use ($optNames) {
                                $vNames = $v->optionValues->map(fn ($ov) => strtolower(trim((string) $ov->name)));
                                return $vNames->isNotEmpty()
                                    && $vNames->count() === $optNames->count()
                                    && $optNames->diff($vNames)->isEmpty();
                            });
                        }
                    }
                }

                // 3. Try match by line name
                if (! $variant && filled($saleItem->name)) {
                    $variant = $itemVariants->first(function (ItemVariant $v) use ($saleItem) {
                        return strtolower(trim((string) $v->name)) === strtolower(trim((string) $saleItem->name));
                    });
                }

                if ($variant) {
                    $saleItem->update(['item_variant_id' => $variant->id]);
                    Log::info("[DeductPosSaleStockJob] Recovered variant #{$variant->id} ('{$variant->sku}') for PosSaleItem #{$saleItem->id}.");
                }
            }
        }

        // 1. Process Base Item Stock Deduction
        if ($item && $item->stock_control) {
            $baseDeduction = $soldQty;

            // Resolve UOM conversion factor to base units if applicable
            if ($item->uomGroup && $item->uomGroup->units && $item->uomGroup->units->isNotEmpty()) {
                $saleUomCode = strtolower(trim((string) ($saleItem->uom_code ?? '')));
                $saleUomName = strtolower(trim((string) ($saleItem->uom_name ?? '')));

                $matchedUnit = $item->uomGroup->units->first(function ($groupUnit) use ($saleUomCode, $saleUomName) {
                    $unitCode = strtolower(trim((string) ($groupUnit->unit?->code ?? '')));
                    $unitName = strtolower(trim((string) ($groupUnit->unit?->name ?? '')));
                    $unitSymbol = strtolower(trim((string) ($groupUnit->unit?->symbol ?? '')));

                    return ($saleUomCode !== '' && ($unitCode === $saleUomCode || $unitSymbol === $saleUomCode))
                        || ($saleUomName !== '' && ($unitName === $saleUomName || $unitCode === $saleUomName));
                });

                if ($matchedUnit && (float) $matchedUnit->conversion_factor_to_base > 0) {
                    $baseDeduction = $soldQty * (float) $matchedUnit->conversion_factor_to_base;
                }
            }

            $currentStock = (int) $item->stock;
            $deductUnits = (int) round($baseDeduction);
            $newStock = max(0, $currentStock - $deductUnits);

            $item->update(['stock' => $newStock]);
        }

        // 2. Process Item Variant Stock Deduction
        if ($variant && ($item?->stock_control ?? $variant->item?->stock_control ?? true)) {
            $currentVariantStock = (int) $variant->stock;
            $deductVariantUnits = (int) round($soldQty);
            $newVariantStock = max(0, $currentVariantStock - $deductVariantUnits);

            $variant->update(['stock' => $newVariantStock]);
        }

        return $item?->id ? (int) $item->id : ($saleItem->item_id ? (int) $saleItem->item_id : null);
    }
}
