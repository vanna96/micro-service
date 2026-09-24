<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';

    protected $model = 'App\Models\PurchaseOrder';

    public function getAdminListing(string $search = '', ?string $status = null, ?int $vendorId = null, ?int $branchId = null): Collection
    {
        return PurchaseOrder::query()
            ->with(['vendor', 'branch', 'currency', 'items.item'])
            ->when($status && in_array($status, PurchaseOrder::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                            $vendorQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    public function getStatusCounts(string $search = ''): array
    {
        $base = PurchaseOrder::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                            $vendorQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            });

        return [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', PurchaseOrder::STATUS_DRAFT)->count(),
            'ordered' => (clone $base)->where('status', PurchaseOrder::STATUS_ORDERED)->count(),
            'received' => (clone $base)->where('status', PurchaseOrder::STATUS_RECEIVED)->count(),
            'cancelled' => (clone $base)->where('status', PurchaseOrder::STATUS_CANCELLED)->count(),
        ];
    }

    public function createWithItems(array $attributes, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($attributes, $items) {
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->create($attributes);

            $itemIds = collect($items)->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
            $itemsMap = \App\Models\Item::query()->with('currency')->whereIn('id', $itemIds)->get()->keyBy('id');
            $orderDate = $po->order_date ? $po->order_date->format('Y-m-d') : now()->toDateString();
            $exchangeRates = app(RateIndexRepository::class)->getRatesForDate($orderDate);
            $orderCurrency = \App\Models\Currency::find($po->currency_id) ?: tenant_base_currency();
            $orderCurrencyCode = strtoupper(trim((string) ($orderCurrency?->code ?: 'USD')));

            $subtotal = 0;
            foreach ($items as $line) {
                $qty = max(0.0001, (float) ($line['quantity'] ?? 1));
                $cost = max(0, (float) ($line['unit_cost'] ?? 0));
                $lineSubtotal = round($qty * $cost, 4);

                $po->items()->create([
                    'item_id' => (int) $line['item_id'],
                    'item_variant_id' => ! empty($line['item_variant_id']) ? (int) $line['item_variant_id'] : null,
                    'uom_id' => ! empty($line['uom_id']) ? (int) $line['uom_id'] : null,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'subtotal' => $lineSubtotal,
                    'notes' => ! empty($line['notes']) ? (string) $line['notes'] : null,
                ]);

                $itemModel = $itemsMap->get((int) $line['item_id']);
                $itemCurrencyCode = strtoupper(trim((string) ($itemModel?->currency?->code ?: $orderCurrencyCode)));
                $lineSubtotalInOrderCurrency = $this->convertAmount(
                    $lineSubtotal,
                    $itemCurrencyCode,
                    $orderCurrencyCode,
                    $exchangeRates['by_code'] ?? []
                );

                $subtotal += $lineSubtotalInOrderCurrency;
            }

            $po->subtotal = round($subtotal, 4);
            $po->total_amount = max(0, round($subtotal + (float) ($po->tax_amount ?? 0) + (float) ($po->shipping_amount ?? 0) - (float) ($po->discount_amount ?? 0), 4));
            $po->save();

            return $po->load(['vendor', 'branch', 'currency', 'items.item', 'items.variant', 'items.unitOfMeasure']);
        });
    }

    public function updateWithItems(PurchaseOrder $po, array $attributes, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $attributes, $items) {
            $po->update($attributes);

            // Recreate items
            $po->items()->delete();

            $itemIds = collect($items)->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
            $itemsMap = \App\Models\Item::query()->with('currency')->whereIn('id', $itemIds)->get()->keyBy('id');
            $orderDate = $po->order_date ? $po->order_date->format('Y-m-d') : now()->toDateString();
            $exchangeRates = app(RateIndexRepository::class)->getRatesForDate($orderDate);
            $orderCurrency = \App\Models\Currency::find($po->currency_id) ?: tenant_base_currency();
            $orderCurrencyCode = strtoupper(trim((string) ($orderCurrency?->code ?: 'USD')));

            $subtotal = 0;
            foreach ($items as $line) {
                $qty = max(0.0001, (float) ($line['quantity'] ?? 1));
                $cost = max(0, (float) ($line['unit_cost'] ?? 0));
                $lineSubtotal = round($qty * $cost, 4);

                $po->items()->create([
                    'item_id' => (int) $line['item_id'],
                    'item_variant_id' => ! empty($line['item_variant_id']) ? (int) $line['item_variant_id'] : null,
                    'uom_id' => ! empty($line['uom_id']) ? (int) $line['uom_id'] : null,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'subtotal' => $lineSubtotal,
                    'notes' => ! empty($line['notes']) ? (string) $line['notes'] : null,
                ]);

                $itemModel = $itemsMap->get((int) $line['item_id']);
                $itemCurrencyCode = strtoupper(trim((string) ($itemModel?->currency?->code ?: $orderCurrencyCode)));
                $lineSubtotalInOrderCurrency = $this->convertAmount(
                    $lineSubtotal,
                    $itemCurrencyCode,
                    $orderCurrencyCode,
                    $exchangeRates['by_code'] ?? []
                );

                $subtotal += $lineSubtotalInOrderCurrency;
            }

            $po->subtotal = round($subtotal, 4);
            $po->total_amount = max(0, round($subtotal + (float) ($po->tax_amount ?? 0) + (float) ($po->shipping_amount ?? 0) - (float) ($po->discount_amount ?? 0), 4));
            $po->save();

            return $po->fresh(['vendor', 'branch', 'currency', 'items.item', 'items.variant', 'items.unitOfMeasure']);
        });
    }

    public function convertAmount(float $amount, string $fromCode, string $toCode, array $ratesByCode): float
    {
        $fromCode = strtoupper(trim($fromCode));
        $toCode = strtoupper(trim($toCode));

        if ($fromCode === $toCode || $amount <= 0) {
            return $amount;
        }

        $fromRate = (float) ($ratesByCode[$fromCode] ?? 1.0);
        $toRate = (float) ($ratesByCode[$toCode] ?? 1.0);

        if ($fromRate <= 0) {
            $fromRate = 1.0;
        }
        if ($toRate <= 0) {
            $toRate = 1.0;
        }

        return ($amount / $fromRate) * $toRate;
    }
}
