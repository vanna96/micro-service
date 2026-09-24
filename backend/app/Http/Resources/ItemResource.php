<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'item_type' => $this->item_type,
            'uom_group_id' => $this->uom_group_id,
            'currency_id' => $this->currency_id,
            'branch' => $this->branch?->name,
            'branch_foreign_name' => $this->branch?->foreign_name,
            'category' => $this->category?->name,
            'category_foreign_name' => $this->category?->foreign_name,
            'uom_group' => $this->whenLoaded('uomGroup', fn () => $this->uomGroup ? [
                'id' => (int) $this->uomGroup->id,
                'code' => (string) $this->uomGroup->code,
                'name' => (string) $this->uomGroup->name,
                'foreign_name' => (string) ($this->uomGroup->foreign_name ?? ''),
            ] : null),
            'currency' => $this->currency?->code,
            'sku' => $this->sku,
            'name' => $this->name,
            'foreign_name' => $this->foreign_name,
            'branch_name' => $this->branch?->name ?: $this->branch_name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'formatted_price' => format_currency_amount($this->price, $this->currency),
            'uom_prices' => $this->whenLoaded('uomPrices', function () {
                $groupUnits = $this->relationLoaded('uomGroup') && $this->uomGroup?->relationLoaded('units')
                    ? $this->uomGroup->units->keyBy('unit_of_measure_id')
                    : collect();

                return $this->uomPrices->map(function ($itemUomPrice) use ($groupUnits) {
                    $groupUnit = $groupUnits->get($itemUomPrice->unit_of_measure_id);
                    $conversionFactor = (float) ($groupUnit?->conversion_factor_to_base ?? 1);
                    $basePrice = round_currency_amount((float) $this->price * $conversionFactor, $this->currency);

                    return [
                        'unit_of_measure_id' => (int) $itemUomPrice->unit_of_measure_id,
                        'uom_code' => (string) ($itemUomPrice->unit?->code ?? ''),
                        'uom_name' => (string) ($itemUomPrice->unit?->name ?? ''),
                        'uom_foreign_name' => (string) ($itemUomPrice->unit?->foreign_name ?? ''),
                        'base_price' => $basePrice,
                        'reduce_by_percent' => (float) $itemUomPrice->reduce_by_percent,
                        'price' => $itemUomPrice->resolvedPrice((float) $this->price, $conversionFactor, $this->currency),
                        'is_auto' => (bool) $itemUomPrice->is_auto,
                        'is_active' => (bool) $itemUomPrice->is_active,
                    ];
                })->values();
            }),
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'review_count' => (int) $this->review_count,
            'stock' => (int) $this->stock,
            'stock_control' => (bool) ($this->stock_control ?? true),
            'purchase' => (bool) ($this->purchase ?? true),
            'sale' => (bool) ($this->sale ?? true),
            'status' => $this->status,
            'is_premium' => (bool) $this->is_premium,
            'is_featured' => (bool) $this->is_featured,
            'is_new_arrival' => (bool) $this->is_new_arrival,
            'is_try_on_enabled' => (bool) $this->is_try_on_enabled,
            'sort_order' => (int) $this->sort_order,
            'thumbnail_url' => $this->image_url,
            'image_url' => $this->image_url,
            'has_variations' => $this->relationLoaded('optionGroups')
                ? $this->optionGroups->where('type', 'variant')->isNotEmpty()
                : $this->optionGroups()->where('type', 'variant')->exists(),
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups->map(fn ($group) => [
                'id' => (int) $group->id,
                'name' => (string) $group->name,
                'foreign_name' => (string) ($group->foreign_name ?? ''),
                'type' => (string) $group->type,
                'selection_type' => (string) $group->selection_type,
                'is_required' => (bool) $group->is_required,
                'min_selections' => (int) $group->min_selections,
                'max_selections' => $group->max_selections !== null ? (int) $group->max_selections : null,
                'sort_order' => (int) $group->sort_order,
                'status' => (string) $group->status,
                'values' => $group->values->map(fn ($value) => [
                    'id' => (int) $value->id,
                    'name' => (string) $value->name,
                    'foreign_name' => (string) ($value->foreign_name ?? ''),
                    'sku_suffix' => (string) ($value->sku_suffix ?? ''),
                    'color_hex' => $value->color_hex,
                    'price_adjustment' => (float) $value->price_adjustment,
                    'formatted_price_adjustment' => format_currency_amount($value->price_adjustment, $this->currency),
                    'is_default' => (bool) $value->is_default,
                    'sort_order' => (int) $value->sort_order,
                    'status' => (string) $value->status,
                ])->values(),
            ])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => (int) $variant->id,
                'sku' => (string) $variant->sku,
                'barcode' => $variant->barcode,
                'name' => (string) ($variant->name ?? ''),
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'resolved_price' => (float) ($variant->price ?? $this->price),
                'formatted_resolved_price' => format_currency_amount($variant->price ?? $this->price, $this->currency),
                'stock' => (int) $variant->stock,
                'is_default' => (bool) $variant->is_default,
                'sort_order' => (int) $variant->sort_order,
                'status' => (string) $variant->status,
                'option_values' => $variant->optionValues->map(fn ($value) => [
                    'id' => (int) $value->id,
                    'group_id' => (int) $value->item_option_group_id,
                    'group_name' => (string) ($value->group?->name ?? ''),
                    'group_foreign_name' => (string) ($value->group?->foreign_name ?? ''),
                    'name' => (string) $value->name,
                    'foreign_name' => (string) ($value->foreign_name ?? ''),
                    'color_hex' => $value->color_hex,
                ])->values(),
            ])->values()),
            'created_at' => $this->created_at?->format('d M, Y'),
            'galleries' => $this->galleries
                ->where('type', 'galleries')
                ->values()
                ->map(function ($gallery) {
                    return [
                        'id' => $gallery->id,
                        'name' => $gallery->name,
                        'image_url' => \Storage::disk('item')->url($gallery->name),
                        'created_at' => $gallery->created_at?->format('d M, Y'),
                    ];
                }),
        ];
    }
}
