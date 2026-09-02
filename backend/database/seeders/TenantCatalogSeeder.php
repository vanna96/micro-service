<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ItemOption;
use App\Models\ItemVariation;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use Illuminate\Database\Seeder;

class TenantCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! tenant()) {
            return;
        }

        $this->seedCategories();
        $this->seedUnitsOfMeasure();
        $this->seedVariations();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Fruits', 'foreign_name' => null],
            ['name' => 'Vegetables', 'foreign_name' => null],
            ['name' => 'Dairy', 'foreign_name' => null],
            ['name' => 'Bakery', 'foreign_name' => null],
            ['name' => 'Snacks', 'foreign_name' => null],
            ['name' => 'Beverages', 'foreign_name' => null],
            ['name' => 'Pantry', 'foreign_name' => null],
            ['name' => 'Household', 'foreign_name' => null],
        ];

        foreach ($categories as $index => $category) {
            Category::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'foreign_name' => $category['foreign_name'],
                    'status' => 'Active',
                ]
            );
        }
    }

    private function seedUnitsOfMeasure(): void
    {
        $groups = [
            [
                'code' => 'WEIGHT',
                'name' => 'Weight',
                'units' => [
                    ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'factor' => 1, 'decimal_places' => 3, 'base' => true],
                    ['code' => 'G', 'name' => 'Gram', 'symbol' => 'g', 'factor' => 0.001, 'decimal_places' => 0, 'base' => false],
                ],
            ],
            [
                'code' => 'VOLUME',
                'name' => 'Volume',
                'units' => [
                    ['code' => 'L', 'name' => 'Liter', 'symbol' => 'L', 'factor' => 1, 'decimal_places' => 3, 'base' => true],
                    ['code' => 'ML', 'name' => 'Milliliter', 'symbol' => 'ml', 'factor' => 0.001, 'decimal_places' => 0, 'base' => false],
                ],
            ],
            [
                'code' => 'COUNT',
                'name' => 'Count',
                'units' => [
                    ['code' => 'EA', 'name' => 'Piece', 'symbol' => 'pc', 'factor' => 1, 'decimal_places' => 0, 'base' => true],
                    ['code' => 'PK', 'name' => 'Pack', 'symbol' => 'pack', 'factor' => 6, 'decimal_places' => 0, 'base' => false],
                    ['code' => 'BX', 'name' => 'Box', 'symbol' => 'box', 'factor' => 12, 'decimal_places' => 0, 'base' => false],
                    ['code' => 'DOZ', 'name' => 'Dozen', 'symbol' => 'doz', 'factor' => 12, 'decimal_places' => 0, 'base' => false],
                ],
            ],
        ];

        foreach ($groups as $groupIndex => $groupData) {
            $group = UomGroup::query()->updateOrCreate(
                ['code' => $groupData['code']],
                [
                    'name' => $groupData['name'],
                    'foreign_name' => null,
                    'description' => $groupData['name'].' units',
                    'status' => 'Active',
                ]
            );

            $baseUnit = null;

            foreach ($groupData['units'] as $unitIndex => $unitData) {
                $unit = UnitOfMeasure::query()->updateOrCreate(
                    ['uom_group_id' => null, 'code' => $unitData['code']],
                    [
                        'name' => $unitData['name'],
                        'foreign_name' => null,
                        'symbol' => $unitData['symbol'],
                        'alternate_quantity' => 1,
                        'base_quantity' => $unitData['factor'],
                        'conversion_factor_to_base' => $unitData['factor'],
                        'is_base_unit' => $unitData['base'],
                        'decimal_places' => $unitData['decimal_places'],
                        'sort_order' => $unitIndex,
                        'status' => 'Active',
                    ]
                );

                UomGroupUnit::query()->updateOrCreate(
                    ['uom_group_id' => $group->id, 'unit_of_measure_id' => $unit->id],
                    [
                        'alternate_quantity' => 1,
                        'base_quantity' => $unitData['factor'],
                        'conversion_factor_to_base' => $unitData['factor'],
                        'is_base_unit' => $unitData['base'],
                        'sort_order' => $unitIndex,
                        'status' => 'Active',
                    ]
                );

                if ($unitData['base']) {
                    $baseUnit = $unit;
                }
            }

            if ($baseUnit) {
                $group->update(['base_unit_id' => $baseUnit->id]);
            }
        }
    }

    private function seedVariations(): void
    {
        $variations = [
            [
                'name' => 'Size',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Small', 'sku_suffix' => 'S'],
                    ['name' => 'Medium', 'sku_suffix' => 'M', 'is_default' => true],
                    ['name' => 'Large', 'sku_suffix' => 'L'],
                    ['name' => 'Extra Large', 'sku_suffix' => 'XL'],
                ],
            ],
            [
                'name' => 'Color',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Red', 'sku_suffix' => 'RED', 'color_hex' => '#EF4444'],
                    ['name' => 'Blue', 'sku_suffix' => 'BLUE', 'color_hex' => '#3B82F6'],
                    ['name' => 'Black', 'sku_suffix' => 'BLACK', 'color_hex' => '#111827'],
                    ['name' => 'White', 'sku_suffix' => 'WHITE', 'color_hex' => '#FFFFFF'],
                    ['name' => 'Green', 'sku_suffix' => 'GREEN', 'color_hex' => '#22C55E'],
                ],
            ],
            [
                'name' => 'Storage',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => '64 GB', 'sku_suffix' => '64GB'],
                    ['name' => '128 GB', 'sku_suffix' => '128GB', 'is_default' => true],
                    ['name' => '256 GB', 'sku_suffix' => '256GB'],
                    ['name' => '512 GB', 'sku_suffix' => '512GB'],
                ],
            ],
        ];

        foreach ($variations as $variationData) {
            $variation = ItemVariation::query()->updateOrCreate(
                ['name' => $variationData['name']],
                [
                    'foreign_name' => null,
                    'type' => $variationData['type'],
                    'selection_type' => $variationData['selection_type'],
                    'is_required' => $variationData['is_required'],
                    'min_selections' => $variationData['min_selections'],
                    'max_selections' => $variationData['max_selections'],
                    'status' => 'Active',
                ]
            );

            foreach ($variationData['options'] as $optionIndex => $optionData) {
                ItemOption::query()->updateOrCreate(
                    ['item_variation_id' => $variation->id, 'name' => $optionData['name']],
                    [
                        'foreign_name' => null,
                        'sku_suffix' => $optionData['sku_suffix'],
                        'color_hex' => $optionData['color_hex'] ?? null,
                        'price_adjustment' => $optionData['price_adjustment'] ?? 0,
                        'is_default' => $optionData['is_default'] ?? false,
                        'sort_order' => $optionIndex,
                        'status' => 'Active',
                    ]
                );
            }
        }
    }
}
