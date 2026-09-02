<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemOptionGroup;
use App\Models\ItemOptionValue;
use App\Models\ItemVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ItemConfigurationService
{
    public function resolve(Item $item, ?int $variantId = null, array $optionValueIds = []): array
    {
        $item->loadMissing(['optionGroups.values', 'variants.optionValues']);

        $activeGroups = $item->optionGroups->where('status', 'Active');
        $variantGroups = $activeGroups->where('type', 'variant');
        $modifierGroups = $activeGroups->where('type', 'modifier');
        $variant = $this->resolveVariant($item, $variantGroups, $variantId);
        $modifierValues = $this->resolveModifiers($modifierGroups, $optionValueIds);
        $modifierTotal = round((float) $modifierValues->sum('price_adjustment'), 2);
        $basePrice = round((float) ($variant?->price ?? $item->price), 2);

        return [
            'line_key' => implode(':', [
                $item->id,
                $variant?->id ?? 0,
                $modifierValues->pluck('id')->sort()->implode(','),
            ]),
            'item_variant_id' => $variant?->id,
            'sku' => (string) ($variant?->sku ?? $item->sku),
            'name' => (string) ($variant?->name ?: $item->name),
            'base_price' => $basePrice,
            'option_total' => $modifierTotal,
            'unit_price' => round(max(0, $basePrice + $modifierTotal), 2),
            'selected_options' => $this->selectionSnapshot($variant, $modifierValues),
        ];
    }

    protected function resolveVariant(Item $item, Collection $variantGroups, ?int $variantId): ?ItemVariant
    {
        if ($variantGroups->isEmpty()) {
            if ($variantId !== null) {
                throw ValidationException::withMessages([
                    'variant_id' => 'This item does not have sellable variants.',
                ]);
            }

            return null;
        }

        if ($variantId === null) {
            throw ValidationException::withMessages([
                'variant_id' => 'Select a variant for this item.',
            ]);
        }

        $variant = $item->variants
            ->where('status', 'Active')
            ->firstWhere('id', $variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'variant_id' => 'The selected variant is unavailable for this item.',
            ]);
        }

        $selectedGroupIds = $variant->optionValues
            ->pluck('item_option_group_id')
            ->unique();

        if ($variantGroups->pluck('id')->diff($selectedGroupIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variant_id' => 'The selected variant has an incomplete option configuration.',
            ]);
        }

        return $variant;
    }

    protected function resolveModifiers(Collection $modifierGroups, array $optionValueIds): Collection
    {
        $selectedIds = collect($optionValueIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $availableValues = $modifierGroups
            ->flatMap(fn (ItemOptionGroup $group) => $group->values->where('status', 'Active'))
            ->keyBy('id');

        if ($selectedIds->diff($availableValues->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'One or more selected options are unavailable for this item.',
            ]);
        }

        $selectedValues = $selectedIds
            ->map(fn (int $id) => $availableValues->get($id))
            ->filter();

        $modifierGroups->each(function (ItemOptionGroup $group) use ($selectedValues) {
            $count = $selectedValues->where('item_option_group_id', $group->id)->count();
            $minimum = max((int) $group->min_selections, $group->is_required ? 1 : 0);
            $maximum = $group->selection_type === 'single'
                ? 1
                : ($group->max_selections !== null ? (int) $group->max_selections : null);

            if ($count < $minimum) {
                throw ValidationException::withMessages([
                    'option_value_ids' => "Select at least {$minimum} option(s) from {$group->name}.",
                ]);
            }

            if ($maximum !== null && $count > $maximum) {
                throw ValidationException::withMessages([
                    'option_value_ids' => "Select no more than {$maximum} option(s) from {$group->name}.",
                ]);
            }
        });

        return $selectedValues->values();
    }

    protected function selectionSnapshot(?ItemVariant $variant, Collection $modifierValues): array
    {
        $variantValues = $variant?->optionValues ?? collect();

        return $variantValues
            ->concat($modifierValues)
            ->groupBy('item_option_group_id')
            ->map(function (Collection $values) {
                /** @var ItemOptionValue $first */
                $first = $values->first();

                return [
                    'group_id' => (int) $first->item_option_group_id,
                    'group_name' => (string) ($first->group?->name ?? ''),
                    'type' => (string) ($first->group?->type ?? ''),
                    'values' => $values->map(fn (ItemOptionValue $value) => [
                        'id' => (int) $value->id,
                        'name' => (string) $value->name,
                        'price_adjustment' => (float) $value->price_adjustment,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
