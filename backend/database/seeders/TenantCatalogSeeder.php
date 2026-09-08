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
            [
                'code' => 'MANUAL',
                'name' => 'Manual',
                'units' => [
                    ['code' => 'EA', 'name' => 'Each', 'symbol' => 'ea', 'factor' => 1, 'decimal_places' => 0, 'base' => true],
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
                    ['name' => 'XX Small', 'sku_suffix' => 'XXS'],
                    ['name' => 'X Small', 'sku_suffix' => 'XS'],
                    ['name' => 'Small', 'sku_suffix' => 'S'],
                    ['name' => 'Medium', 'sku_suffix' => 'M', 'is_default' => true],
                    ['name' => 'Large', 'sku_suffix' => 'L'],
                    ['name' => 'Extra Large', 'sku_suffix' => 'XL'],
                    ['name' => '2X Large', 'sku_suffix' => '2XL'],
                    ['name' => '3X Large', 'sku_suffix' => '3XL'],
                    ['name' => '4X Large', 'sku_suffix' => '4XL'],
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
                    ['name' => 'Gray', 'sku_suffix' => 'GRAY', 'color_hex' => '#6B7280'],
                    ['name' => 'Silver', 'sku_suffix' => 'SILVER', 'color_hex' => '#C0C0C0'],
                    ['name' => 'Brown', 'sku_suffix' => 'BROWN', 'color_hex' => '#92400E'],
                    ['name' => 'Beige', 'sku_suffix' => 'BEIGE', 'color_hex' => '#D6C6A5'],
                    ['name' => 'Pink', 'sku_suffix' => 'PINK', 'color_hex' => '#EC4899'],
                    ['name' => 'Purple', 'sku_suffix' => 'PURPLE', 'color_hex' => '#8B5CF6'],
                    ['name' => 'Orange', 'sku_suffix' => 'ORANGE', 'color_hex' => '#F97316'],
                    ['name' => 'Yellow', 'sku_suffix' => 'YELLOW', 'color_hex' => '#EAB308'],
                    ['name' => 'Navy', 'sku_suffix' => 'NAVY', 'color_hex' => '#1E3A8A'],
                    ['name' => 'Gold', 'sku_suffix' => 'GOLD', 'color_hex' => '#D4AF37'],
                    ['name' => 'Multicolor', 'sku_suffix' => 'MULTI'],
                    ['name' => 'Transparent', 'sku_suffix' => 'CLEAR'],
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
                    ['name' => '16 GB', 'sku_suffix' => '16GB'],
                    ['name' => '32 GB', 'sku_suffix' => '32GB'],
                    ['name' => '64 GB', 'sku_suffix' => '64GB'],
                    ['name' => '128 GB', 'sku_suffix' => '128GB', 'is_default' => true],
                    ['name' => '256 GB', 'sku_suffix' => '256GB'],
                    ['name' => '512 GB', 'sku_suffix' => '512GB'],
                    ['name' => '1 TB', 'sku_suffix' => '1TB'],
                    ['name' => '2 TB', 'sku_suffix' => '2TB'],
                ],
            ],
            [
                'name' => 'Memory',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => '2 GB', 'sku_suffix' => '2GB'],
                    ['name' => '4 GB', 'sku_suffix' => '4GB'],
                    ['name' => '8 GB', 'sku_suffix' => '8GB', 'is_default' => true],
                    ['name' => '16 GB', 'sku_suffix' => '16GB'],
                    ['name' => '32 GB', 'sku_suffix' => '32GB'],
                    ['name' => '64 GB', 'sku_suffix' => '64GB'],
                    ['name' => '128 GB', 'sku_suffix' => '128GB'],
                ],
            ],
            [
                'name' => 'Shoe Size',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'EU 35', 'sku_suffix' => 'EU35'],
                    ['name' => 'EU 36', 'sku_suffix' => 'EU36'],
                    ['name' => 'EU 37', 'sku_suffix' => 'EU37'],
                    ['name' => 'EU 38', 'sku_suffix' => 'EU38'],
                    ['name' => 'EU 39', 'sku_suffix' => 'EU39'],
                    ['name' => 'EU 40', 'sku_suffix' => 'EU40', 'is_default' => true],
                    ['name' => 'EU 41', 'sku_suffix' => 'EU41'],
                    ['name' => 'EU 42', 'sku_suffix' => 'EU42'],
                    ['name' => 'EU 43', 'sku_suffix' => 'EU43'],
                    ['name' => 'EU 44', 'sku_suffix' => 'EU44'],
                    ['name' => 'EU 45', 'sku_suffix' => 'EU45'],
                    ['name' => 'EU 46', 'sku_suffix' => 'EU46'],
                ],
            ],
            [
                'name' => 'Capacity',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => '250 mL', 'sku_suffix' => '250ML'],
                    ['name' => '330 mL', 'sku_suffix' => '330ML'],
                    ['name' => '500 mL', 'sku_suffix' => '500ML', 'is_default' => true],
                    ['name' => '750 mL', 'sku_suffix' => '750ML'],
                    ['name' => '1 L', 'sku_suffix' => '1L'],
                    ['name' => '1.5 L', 'sku_suffix' => '15L'],
                    ['name' => '2 L', 'sku_suffix' => '2L'],
                ],
            ],
            [
                'name' => 'Weight',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => '100 g', 'sku_suffix' => '100G'],
                    ['name' => '250 g', 'sku_suffix' => '250G'],
                    ['name' => '500 g', 'sku_suffix' => '500G', 'is_default' => true],
                    ['name' => '1 kg', 'sku_suffix' => '1KG'],
                    ['name' => '2 kg', 'sku_suffix' => '2KG'],
                    ['name' => '5 kg', 'sku_suffix' => '5KG'],
                ],
            ],
            [
                'name' => 'Pack Size',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Single', 'sku_suffix' => '1PC', 'is_default' => true],
                    ['name' => 'Pack of 2', 'sku_suffix' => '2PK'],
                    ['name' => 'Pack of 4', 'sku_suffix' => '4PK'],
                    ['name' => 'Pack of 6', 'sku_suffix' => '6PK'],
                    ['name' => 'Pack of 12', 'sku_suffix' => '12PK'],
                    ['name' => 'Pack of 24', 'sku_suffix' => '24PK'],
                ],
            ],
            [
                'name' => 'Material',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Cotton', 'sku_suffix' => 'COTTON'],
                    ['name' => 'Polyester', 'sku_suffix' => 'POLY'],
                    ['name' => 'Denim', 'sku_suffix' => 'DENIM'],
                    ['name' => 'Wool', 'sku_suffix' => 'WOOL'],
                    ['name' => 'Linen', 'sku_suffix' => 'LINEN'],
                    ['name' => 'Leather', 'sku_suffix' => 'LEATHER'],
                    ['name' => 'Faux Leather', 'sku_suffix' => 'FAUX'],
                    ['name' => 'Metal', 'sku_suffix' => 'METAL'],
                    ['name' => 'Plastic', 'sku_suffix' => 'PLASTIC'],
                    ['name' => 'Glass', 'sku_suffix' => 'GLASS'],
                    ['name' => 'Wood', 'sku_suffix' => 'WOOD'],
                ],
            ],
            [
                'name' => 'Flavor',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Original', 'sku_suffix' => 'ORIGINAL', 'is_default' => true],
                    ['name' => 'Chocolate', 'sku_suffix' => 'CHOC'],
                    ['name' => 'Vanilla', 'sku_suffix' => 'VANILLA'],
                    ['name' => 'Strawberry', 'sku_suffix' => 'STRAWBERRY'],
                    ['name' => 'Salted', 'sku_suffix' => 'SALTED'],
                    ['name' => 'Cheese', 'sku_suffix' => 'CHEESE'],
                    ['name' => 'Barbecue', 'sku_suffix' => 'BBQ'],
                    ['name' => 'Spicy', 'sku_suffix' => 'SPICY'],
                    ['name' => 'Lemon', 'sku_suffix' => 'LEMON'],
                ],
            ],
            [
                'name' => 'Fit',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Slim', 'sku_suffix' => 'SLIM'],
                    ['name' => 'Regular', 'sku_suffix' => 'REGULAR', 'is_default' => true],
                    ['name' => 'Relaxed', 'sku_suffix' => 'RELAXED'],
                    ['name' => 'Oversized', 'sku_suffix' => 'OVERSIZED'],
                ],
            ],
            [
                'name' => 'Finish',
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'Matte', 'sku_suffix' => 'MATTE'],
                    ['name' => 'Glossy', 'sku_suffix' => 'GLOSSY'],
                    ['name' => 'Satin', 'sku_suffix' => 'SATIN'],
                    ['name' => 'Metallic', 'sku_suffix' => 'METALLIC'],
                ],
            ],
            [
                'name' => 'Spice Level',
                'type' => 'modifier',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'options' => [
                    ['name' => 'No Spice', 'sku_suffix' => 'NO-SPICE'],
                    ['name' => 'Mild', 'sku_suffix' => 'MILD', 'is_default' => true],
                    ['name' => 'Medium', 'sku_suffix' => 'MEDIUM'],
                    ['name' => 'Hot', 'sku_suffix' => 'HOT'],
                    ['name' => 'Extra Hot', 'sku_suffix' => 'EXTRA-HOT'],
                ],
            ],
            [
                'name' => 'Add-ons',
                'type' => 'modifier',
                'selection_type' => 'multiple',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => null,
                'options' => [
                    ['name' => 'Extra Cheese', 'sku_suffix' => 'CHEESE'],
                    ['name' => 'Extra Sauce', 'sku_suffix' => 'SAUCE'],
                    ['name' => 'Extra Meat', 'sku_suffix' => 'MEAT'],
                    ['name' => 'Extra Vegetables', 'sku_suffix' => 'VEG'],
                    ['name' => 'Extra Egg', 'sku_suffix' => 'EGG'],
                ],
            ],
            [
                'name' => 'Toppings',
                'type' => 'modifier',
                'selection_type' => 'multiple',
                'is_required' => false,
                'min_selections' => 0,
                'max_selections' => null,
                'options' => [
                    ['name' => 'Cheese', 'sku_suffix' => 'CHEESE'],
                    ['name' => 'Mushroom', 'sku_suffix' => 'MUSHROOM'],
                    ['name' => 'Onion', 'sku_suffix' => 'ONION'],
                    ['name' => 'Olives', 'sku_suffix' => 'OLIVES'],
                    ['name' => 'Tomato', 'sku_suffix' => 'TOMATO'],
                    ['name' => 'Pepper', 'sku_suffix' => 'PEPPER'],
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
