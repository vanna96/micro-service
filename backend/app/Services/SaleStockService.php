<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Validation\ValidationException;

class SaleStockService
{
    /**
     * Validate and reserve stock for authoritative pricing lines.
     * This must run inside the sale database transaction.
     *
     * @return array<int>
     */
    public function reserve(iterable $pricedLines, string $errorKey = 'items'): array
    {
        $itemRequirements = [];
        $variantRequirements = [];

        foreach ($pricedLines as $line) {
            $itemId = (int) $line['item_id'];
            $quantity = (int) $line['quantity'];
            $conversionFactor = max(0.00000001, (float) ($line['uom_conversion_factor'] ?? 1));
            $baseQuantity = max(1, (int) round($quantity * $conversionFactor));
            $itemRequirements[$itemId] = ($itemRequirements[$itemId] ?? 0) + $baseQuantity;

            if (! empty($line['item_variant_id'])) {
                $variantId = (int) $line['item_variant_id'];
                $variantRequirements[$variantId] = ($variantRequirements[$variantId] ?? 0) + $quantity;
            }
        }

        $itemIds = array_keys($itemRequirements);
        sort($itemIds);
        $items = Item::query()
            ->dontCache()
            ->whereIn('id', $itemIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($itemRequirements as $itemId => $requiredQuantity) {
            $item = $items->get($itemId);
            if (! $item || $item->status !== 'Active' || ! $item->sale) {
                throw ValidationException::withMessages([
                    $errorKey => 'One or more products are no longer available for sale.',
                ]);
            }

            if ($item->stock_control && (int) $item->stock < $requiredQuantity) {
                throw ValidationException::withMessages([
                    $errorKey => "{$item->name} does not have enough stock for this sale.",
                ]);
            }
        }

        $variantIds = array_keys($variantRequirements);
        sort($variantIds);
        $variants = ItemVariant::query()
            ->dontCache()
            ->whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($variantRequirements as $variantId => $requiredQuantity) {
            $variant = $variants->get($variantId);
            $item = $variant ? $items->get((int) $variant->item_id) : null;
            if (! $variant || ! $item || $variant->status !== 'Active') {
                throw ValidationException::withMessages([
                    $errorKey => 'One or more selected product variants are no longer available.',
                ]);
            }

            if ($item->stock_control && (int) $variant->stock < $requiredQuantity) {
                throw ValidationException::withMessages([
                    $errorKey => "{$variant->name} does not have enough stock for this sale.",
                ]);
            }
        }

        foreach ($itemRequirements as $itemId => $requiredQuantity) {
            $item = $items->get($itemId);
            if ($item->stock_control) {
                $item->forceFill(['stock' => (int) $item->stock - $requiredQuantity])->save();
            }
        }

        foreach ($variantRequirements as $variantId => $requiredQuantity) {
            $variant = $variants->get($variantId);
            $item = $items->get((int) $variant->item_id);
            if ($item->stock_control) {
                $variant->forceFill(['stock' => (int) $variant->stock - $requiredQuantity])->save();
            }
        }

        return $itemIds;
    }
}
