<?php

namespace App\Services;

use App\Events\PosStockUpdatedEvent;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaseOrderService
{
    /**
     * Receive a Purchase Order and stock in items with stock_control enabled.
     *
     * @param PurchaseOrder $order
     * @param string|null $tenantId
     * @return array
     */
    public function receive(PurchaseOrder $order, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (tenant('id') ?: null);

        if ($order->isReceived()) {
            throw new DomainException("Purchase order #{$order->po_number} has already been received on {$order->stock_received_at->format('Y-m-d H:i')}.");
        }

        if ($order->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new DomainException("Cancelled purchase order #{$order->po_number} cannot be received.");
        }

        $connectionName = tenant()?->database_connection_name ?? 'tenant';
        $affectedItemIds = [];
        $stockDetails = [];

        DB::connection($connectionName)->transaction(function () use ($order, &$affectedItemIds, &$stockDetails) {
            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()
                ->with(['items.item.uomGroup.units.unit', 'items.variant'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->isReceived()) {
                throw new DomainException("Purchase order #{$lockedOrder->po_number} has already been received.");
            }

            foreach ($lockedOrder->items as $line) {
                /** @var Item|null $item */
                $item = Item::query()
                    ->with(['uomGroup.units.unit'])
                    ->lockForUpdate()
                    ->find($line->item_id);

                if (! $item) {
                    continue;
                }

                $qty = (float) $line->quantity;
                $stockControl = (bool) $item->stock_control;

                if ($stockControl) {
                    $factor = 1.0;

                    if ($line->uom_id && $item->uomGroup && $item->uomGroup->units) {
                        $matchedUnit = $item->uomGroup->units->first(function ($groupUnit) use ($line) {
                            return (int) $groupUnit->unit_of_measure_id === (int) $line->uom_id
                                || (int) $groupUnit->id === (int) $line->uom_id;
                        });

                        if ($matchedUnit && (float) $matchedUnit->conversion_factor_to_base > 0) {
                            $factor = (float) $matchedUnit->conversion_factor_to_base;
                        }
                    }

                    $baseUnitsToAdd = (int) round($qty * $factor);
                    $oldStock = (int) $item->stock;
                    $item->increment('stock', $baseUnitsToAdd);
                    $newStock = (int) $item->fresh()->stock;

                    $variantStockAdded = null;
                    if ($line->item_variant_id) {
                        /** @var ItemVariant|null $variant */
                        $variant = ItemVariant::query()
                            ->lockForUpdate()
                            ->find($line->item_variant_id);

                        if ($variant) {
                            $variantUnitsToAdd = (int) round($qty);
                            $variant->increment('stock', $variantUnitsToAdd);
                            $variantStockAdded = [
                                'variant_id' => $variant->id,
                                'variant_sku' => $variant->sku,
                                'added' => $variantUnitsToAdd,
                                'new_stock' => (int) $variant->fresh()->stock,
                            ];
                        }
                    }

                    $affectedItemIds[] = $item->id;
                    $stockDetails[] = [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_sku' => $item->sku,
                        'stock_control' => true,
                        'conversion_factor' => $factor,
                        'ordered_quantity' => $qty,
                        'base_units_added' => $baseUnitsToAdd,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                        'variant' => $variantStockAdded,
                    ];
                } else {
                    $stockDetails[] = [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_sku' => $item->sku,
                        'stock_control' => false,
                        'ordered_quantity' => $qty,
                        'base_units_added' => 0,
                        'message' => 'Stock control disabled for this item. Quantity recorded without updating inventory.',
                    ];
                }
            }

            $lockedOrder->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'stock_received_at' => now(),
            ]);
        });

        // Live broadcast for POS sync
        if (! empty($affectedItemIds)) {
            try {
                broadcast(new PosStockUpdatedEvent(
                    array_values(array_unique(array_filter($affectedItemIds))),
                    null,
                    $tenantId
                ));
            } catch (Throwable $broadcastError) {
                Log::warning("[PurchaseOrderService] Live stock broadcast warning: " . $broadcastError->getMessage());
            }
        }

        return [
            'success' => true,
            'purchase_order_id' => $order->id,
            'po_number' => $order->po_number,
            'stock_details' => $stockDetails,
            'affected_item_ids' => array_values(array_unique($affectedItemIds)),
        ];
    }

    /**
     * Generate next sequential PO Number.
     *
     * @param string $prefix
     * @return string
     */
    public function generatePoNumber(string $prefix = 'PO'): string
    {
        $datePrefix = date('Ymd');
        $base = "{$prefix}-{$datePrefix}-";

        $lastOrder = PurchaseOrder::query()
            ->where('po_number', 'like', "{$base}%")
            ->orderByDesc('id')
            ->first();

        if ($lastOrder && preg_match('/-(\d+)$/', (string) $lastOrder->po_number, $matches)) {
            $nextSeq = str_pad((string) (((int) $matches[1]) + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $nextSeq = '0001';
        }

        return "{$base}{$nextSeq}";
    }

    /**
     * Cancel a purchase order.
     *
     * @param PurchaseOrder $order
     * @param string|null $reason
     * @return PurchaseOrder
     * @throws DomainException
     */
    public function cancel(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        if ($order->isReceived()) {
            throw new DomainException("Received purchase order #{$order->po_number} cannot be cancelled because inventory has already been received.");
        }

        if ($order->status === PurchaseOrder::STATUS_CANCELLED) {
            return $order;
        }

        $notes = $order->notes;
        if ($reason) {
            $stamp = now()->format('Y-m-d H:i');
            $user = auth()->user()?->name ?: 'Admin';
            $cancelNote = "[Cancelled on {$stamp} by {$user}: {$reason}]";
            $notes = $notes ? "{$notes}\n{$cancelNote}" : $cancelNote;
        }

        $order->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'notes' => $notes,
        ]);

        return $order->fresh();
    }

    /**
     * Mark a purchase order as Ordered (placed with supplier).
     *
     * @param PurchaseOrder $order
     * @return PurchaseOrder
     * @throws DomainException
     */
    public function markOrdered(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->isReceived()) {
            throw new DomainException("Received purchase order #{$order->po_number} cannot be modified.");
        }

        $order->update([
            'status' => PurchaseOrder::STATUS_ORDERED,
        ]);

        return $order->fresh();
    }

    /**
     * Revert or reopen a purchase order back to Draft.
     *
     * @param PurchaseOrder $order
     * @return PurchaseOrder
     * @throws DomainException
     */
    public function markDraft(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->isReceived()) {
            throw new DomainException("Received purchase order #{$order->po_number} cannot be reverted to Draft.");
        }

        $order->update([
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        return $order->fresh();
    }
}
