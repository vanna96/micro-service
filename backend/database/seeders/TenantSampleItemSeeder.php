<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemOptionGroup;
use App\Models\ItemOptionValue;
use App\Models\ItemVariant;
use App\Models\UomGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantSampleItemSeeder extends Seeder
{
    public function run(): void
    {
        if (! tenant()) {
            return;
        }

        $categories = $this->seedCategories();
        $uomGroups = UomGroup::query()->whereIn('code', ['COUNT', 'MANUAL', 'VOLUME', 'WEIGHT'])->get()->keyBy('code');
        $currency = Currency::query()->where('code', 'USD')->first()
            ?? Currency::query()->where('status', 'Active')->orderBy('id')->first();

        DB::connection(tenant()->database_connection_name)->transaction(function () use ($categories, $uomGroups, $currency) {
            foreach ($this->products() as $sortOrder => $productData) {
                $configuration = $productData['configuration'] ?? [];
                $uomPrices = $productData['uom_prices'] ?? [];
                unset($productData['configuration'], $productData['uom_prices']);

                $categoryName = $productData['category'];
                $uomGroupCode = $productData['uom_group'] ?? null;
                $imageAsset = $productData['image'];
                unset($productData['category'], $productData['uom_group'], $productData['image']);

                $item = Item::query()->updateOrCreate(
                    ['sku' => $productData['sku']],
                    array_merge($productData, [
                        'category_id' => $categories->get($categoryName)?->id,
                        'uom_group_id' => $uomGroupCode ? $uomGroups->get($uomGroupCode)?->id : null,
                        'currency_id' => $currency?->id,
                        'sort_order' => $sortOrder,
                        'status' => 'Active',
                    ])
                );

                $this->syncThumbnail($item, $imageAsset);
                $this->syncConfiguration($item, $configuration);
                $this->syncUomPrices($item, $uomPrices);
            }
        });

        Item::flushQueryCache();
        ItemOptionGroup::flushQueryCache();
        ItemOptionValue::flushQueryCache();
        ItemVariant::flushQueryCache();
    }

    private function seedCategories()
    {
        return collect([
            'Clothing',
            'Footwear',
            'Watches',
            'Food',
            'Accessories',
            'Electronics',
            'Bags',
            'Beauty',
            'Fitness',
        ])->mapWithKeys(function (string $name) {
            $category = Category::query()->updateOrCreate(
                ['name' => $name],
                ['foreign_name' => null, 'status' => 'Active']
            );

            return [$name => $category];
        });
    }

    private function syncThumbnail(Item $item, string $assetName): void
    {
        $sourcePath = public_path('assets/'.$assetName);

        if (! is_file($sourcePath)) {
            return;
        }

        $disk = Storage::disk('item');
        $extension = strtolower(pathinfo($assetName, PATHINFO_EXTENSION) ?: 'png');
        $fileName = 'sample_'.$item->sku.'.'.$extension;
        $contents = file_get_contents($sourcePath);

        if ($contents === false || ! $disk->put($fileName, $contents, [
            'visibility' => 'public',
            'ContentType' => mime_content_type($sourcePath) ?: 'image/png',
        ])) {
            throw new \RuntimeException('Unable to upload sample item image: '.$sourcePath);
        }

        $thumbnail = $item->galleries()->where('type', 'thumbnail')->first();

        if (! $thumbnail) {
            $thumbnail = $item->galleries()->create([
                'type' => 'thumbnail',
                'status' => 'Active',
                'name' => $fileName,
            ]);
        } else {
            if ($thumbnail->name !== $fileName && $disk->exists($thumbnail->name)) {
                $disk->delete($thumbnail->name);
            }

            $thumbnail->update([
                'status' => 'Active',
                'name' => $fileName,
            ]);
        }

        $item->forceFill(['image_id' => $thumbnail->id])->save();
    }

    private function syncConfiguration(Item $item, array $groups): void
    {
        $item->variants()->delete();
        $item->optionGroups()->delete();

        if ($item->item_type !== 'variation' || empty($groups)) {
            return;
        }

        $combinationGroups = [];

        foreach ($groups as $groupIndex => $groupData) {
            $values = $groupData['values'];
            unset($groupData['values']);

            $group = $item->optionGroups()->create(array_merge([
                'item_variation_id' => null,
                'foreign_name' => null,
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'sort_order' => $groupIndex,
                'status' => 'Active',
            ], $groupData));

            $combinationGroups[] = collect($values)->map(function (array $valueData, int $valueIndex) use ($group) {
                $value = $group->values()->create([
                    'name' => $valueData['name'],
                    'foreign_name' => null,
                    'sku_suffix' => $valueData['sku_suffix'],
                    'color_hex' => $valueData['color_hex'] ?? null,
                    'price_adjustment' => $valueData['price_adjustment'] ?? 0,
                    'is_default' => $valueIndex === 0,
                    'sort_order' => $valueIndex,
                    'status' => 'Active',
                ]);

                return [
                    'id' => $value->id,
                    'name' => $value->name,
                    'sku_suffix' => $value->sku_suffix,
                    'price_adjustment' => (float) $value->price_adjustment,
                ];
            })->all();
        }

        $combinations = [[]];
        foreach ($combinationGroups as $values) {
            $combinations = collect($combinations)
                ->flatMap(fn (array $combination) => collect($values)->map(fn (array $value) => [...$combination, $value]))
                ->all();
        }

        foreach ($combinations as $variantIndex => $combination) {
            $priceAdjustment = collect($combination)->sum('price_adjustment');
            $variant = $item->variants()->create([
                'sku' => $item->sku.'-'.collect($combination)->pluck('sku_suffix')->implode('-'),
                'barcode' => null,
                'name' => collect($combination)->pluck('name')->implode(' / '),
                'price' => (float) $item->price + $priceAdjustment,
                'stock' => max(1, (int) floor($item->stock / max(1, count($combinations)))),
                'is_default' => $variantIndex === 0,
                'sort_order' => $variantIndex,
                'status' => 'Active',
            ]);

            $variant->optionValues()->sync(collect($combination)->pluck('id')->all());
        }
    }

    private function syncUomPrices(Item $item, array $uomPrices): void
    {
        $item->uomPrices()->delete();

        if ($item->item_type !== 'uom' || ! $item->uom_group_id || empty($uomPrices)) {
            return;
        }

        $unitsByCode = $item->uomGroup?->units->loadMissing('unit')->keyBy(fn ($groupUnit) => $groupUnit->unit?->code) ?? collect();

        foreach ($uomPrices as $uomPriceData) {
            $unitCode = $uomPriceData['unit_code'];
            $groupUnit = $unitsByCode->get($unitCode);
            if (! $groupUnit || ! $groupUnit->unit) {
                continue;
            }

            $item->uomPrices()->create([
                'unit_of_measure_id' => $groupUnit->unit_of_measure_id,
                'reduce_by_percent' => (float) ($uomPriceData['reduce_by_percent'] ?? 0),
                'price' => isset($uomPriceData['price']) ? (float) $uomPriceData['price'] : null,
                'is_auto' => (bool) ($uomPriceData['is_auto'] ?? true),
                'is_active' => (bool) ($uomPriceData['is_active'] ?? true),
            ]);
        }
    }

    private function products(): array
    {
        return array_merge($this->uomProducts(), $this->variationProducts(), $this->standardProducts());
    }

    private function uomProducts(): array
    {
        return [
            array_merge(
                $this->item('FD-SNK-001', 'Classic Salted Potato Chips', 2.99, 'Food', 'pr-21-Dip6sWIz.png', 'uom', 'WEIGHT', 80, 'CrunchBite'),
                ['uom_prices' => [
                    ['unit_code' => 'KG', 'reduce_by_percent' => 10, 'price' => null, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'G', 'reduce_by_percent' => 0, 'price' => 0.05, 'is_auto' => false, 'is_active' => true],
                ]]
            ),
            array_merge(
                $this->item('FD-DRY-005', 'Full Cream Fresh Milk', 2.49, 'Food', 'pr-25-2HQ0Uvnq.png', 'uom', 'VOLUME', 72, 'FarmFresh'),
                ['uom_prices' => [
                    ['unit_code' => 'L', 'reduce_by_percent' => 0, 'price' => 2.49, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'ML', 'reduce_by_percent' => 10, 'price' => null, 'is_auto' => true, 'is_active' => true],
                ]]
            ),
            array_merge(
                $this->item('FD-BEV-004', 'Cold Pressed Orange Juice', 3.99, 'Food', 'pr-24-CaCL_grq.png', 'uom', 'VOLUME', 60, 'FreshJuice'),
                ['uom_prices' => [
                    ['unit_code' => 'L', 'reduce_by_percent' => 0, 'price' => 3.99, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'ML', 'reduce_by_percent' => 5, 'price' => null, 'is_auto' => true, 'is_active' => true],
                ]]
            ),
            array_merge(
                $this->item('FD-DRY-002', 'Premium Roasted Almonds', 8.99, 'Food', 'pr-22-AujqxRgd.png', 'uom', 'WEIGHT', 45, 'NutriBite'),
                ['uom_prices' => [
                    ['unit_code' => 'KG', 'reduce_by_percent' => 0, 'price' => 8.99, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'G', 'reduce_by_percent' => 0, 'price' => 0.02, 'is_auto' => false, 'is_active' => true],
                ]]
            ),
            array_merge(
                $this->item('FD-BEV-003', 'Instant Espresso Coffee Roast', 6.99, 'Food', 'pr-23-DKNsLIkt.png', 'uom', 'WEIGHT', 36, 'AromaRoast'),
                ['uom_prices' => [
                    ['unit_code' => 'KG', 'reduce_by_percent' => 0, 'price' => 6.99, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'G', 'reduce_by_percent' => 0, 'price' => 0.01, 'is_auto' => false, 'is_active' => true],
                ]]
            ),
            array_merge(
                $this->item('FD-SNK-006', 'Instant Masala Noodles Pack', 1.99, 'Food', 'pr-26-CbwXBE4S.png', 'uom', 'COUNT', 120, 'QuickMeal'),
                ['uom_prices' => [
                    ['unit_code' => 'EA', 'reduce_by_percent' => 0, 'price' => 1.99, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'PK', 'reduce_by_percent' => 5, 'price' => null, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'BX', 'reduce_by_percent' => 10, 'price' => null, 'is_auto' => true, 'is_active' => true],
                    ['unit_code' => 'DOZ', 'reduce_by_percent' => 10, 'price' => null, 'is_auto' => true, 'is_active' => false],
                ]]
            ),
        ];
    }

    private function variationProducts(): array
    {
        return [
            array_merge(
                $this->item('WT-SMT-002', 'Smart Fitness Watch Pro', 99.99, 'Watches', 'pr-20-DLcbFUnr.png', 'variation', null, 90, 'TechPulse'),
                ['configuration' => [
                    $this->group('Case Size & Memory', [
                        $this->value('40mm (64GB)', '40-64', 0),
                        $this->value('44mm (128GB)', '44-128', 30),
                        $this->value('46mm (256GB Cellular)', '46-256', 70),
                    ]),
                    $this->group('Color', [
                        $this->value('Midnight Black', 'BLACK', 0, '#111827'),
                        $this->value('Titanium Gray', 'GRAY', 0, '#6B7280'),
                        $this->value('Starlight Silver', 'SILVER', 0, '#C0C0C0'),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('EL-CHR-001', 'USB-C Fast Charger & Cable', 19.99, 'Accessories', 'pr-27-2sMQbU1b.png', 'variation', null, 60, 'TechPulse'),
                ['configuration' => [
                    $this->group('Output Power', [
                        $this->value('30W Compact', '30W'),
                        $this->value('65W Dual Port GaN', '65W', 15),
                        $this->value('100W Pro 4-Port', '100W', 35),
                    ]),
                    $this->group('Cable Included', [
                        $this->value('1.2m Braided Type-C', '12M'),
                        $this->value('2.0m Heavy Duty Cable', '20M', 5),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('DR-WMN-002', 'Polka Dot Skater Dress', 32.99, 'Clothing', 'pr-3-BzZJ2azf.png', 'variation', null, 64, 'StyleHub'),
                ['configuration' => [
                    $this->group('Size', [
                        $this->value('S (UK 8)', 'S'),
                        $this->value('M (UK 10)', 'M'),
                        $this->value('L (UK 12)', 'L'),
                        $this->value('XL (UK 14)', 'XL', 3),
                    ]),
                    $this->group('Color', [
                        $this->value('Navy Blue Dot', 'NAVY', 0, '#1E3A8A'),
                        $this->value('Classic Crimson Dot', 'CRIMSON', 0, '#DC143C'),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('FT-MEN-002', "Men's Running Sports Shoes", 59.99, 'Footwear', 'pr-16-qIivuYeB.png', 'variation', null, 72, 'PulseFit'),
                ['configuration' => [
                    $this->group('Shoe Size', [
                        $this->value('US 8 / EU 41', 'EU41'),
                        $this->value('US 9 / EU 42', 'EU42'),
                        $this->value('US 10 / EU 43', 'EU43'),
                        $this->value('US 11 / EU 44', 'EU44'),
                    ]),
                    $this->group('Colorway', [
                        $this->value('Shadow Black', 'BLACK', 0, '#111827'),
                        $this->value('Volt Green', 'GREEN', 0, '#84CC16'),
                        $this->value('Pure White', 'WHITE', 0, '#FFFFFF'),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('JN-MEN-006', "Men's Light Blue Ripped Jeans", 44.99, 'Clothing', 'pr-6-B0ESum4b.png', 'variation', null, 8, 'UrbanDenim'),
                ['configuration' => [
                    $this->group('Waist Size', [
                        $this->value('30W x 32L', '30-32'),
                        $this->value('32W x 32L', '32-32'),
                        $this->value('34W x 32L', '34-32'),
                        $this->value('36W x 34L', '36-34'),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('BT-MKP-023', 'Matte Velvet Lipstick', 19.99, 'Beauty', 'pr-39-Bgva8Ct-.png', 'variation', null, 45, 'GlowGoddess'),
                ['configuration' => [
                    $this->group('Shade', [
                        $this->value('Ruby Wine #01', 'RUBY', 0, '#8B1E3F'),
                        $this->value('Nude Peach #04', 'PEACH', 0, '#E8A087'),
                        $this->value('Rosewood Berry #09', 'BERRY', 0, '#9B4668'),
                    ]),
                ]]
            ),
            array_merge(
                $this->item('FT-DBL-031', 'Adjustable Dumbbell Set', 59.99, 'Fitness', 'pr-40-pfTD79Wn.png', 'variation', null, 24, 'IronPeak'),
                ['configuration' => [
                    $this->group('Weight Specification', [
                        $this->value('Pair 20kg (2x10kg)', '20KG'),
                        $this->value('Pair 32kg (2x16kg)', '32KG', 40),
                        $this->value('Pair 40kg (2x20kg)', '40KG', 75),
                    ]),
                ]]
            ),
        ];
    }

    private function standardProducts(): array
    {
        return [
            $this->item('BG-HBG-011', "Women's Elegant Handbag", 89.99, 'Bags', 'pr-35-B2Unq0hU.png', 'uom', 'MANUAL', 25, 'NovaWear'),
            $this->item('BG-HBG-012', 'Designer Leather Shoulder Bag', 99.99, 'Bags', 'pr-36-COs7RIFT.png', 'uom', 'MANUAL', 18, 'NovaWear'),
            $this->item('TP-WMN-003', "Women's Red Camisole Top", 17.99, 'Clothing', 'pr-2-BB_J2W-Z.png', 'uom', 'MANUAL', 40, 'StyleHub'),
            $this->item('SH-WMN-004', "Women's Formal Pink Shirt", 29.99, 'Clothing', 'pr-4-JPhm9DaC.png', 'uom', 'MANUAL', 32, 'StyleHub'),
            $this->item('SK-WMN-005', "Women's Navy Mini Skirt", 22.99, 'Clothing', 'pr-5-CDf-ezTw.png', 'uom', 'MANUAL', 28, 'StyleHub'),
            $this->item('JK-MEN-007', "Men's Beige Casual Jacket", 64.99, 'Clothing', 'pr-7-DOHIoQVB.png', 'uom', 'MANUAL', 22, 'UrbanDenim'),
            $this->item('HD-MEN-008', "Men's Olive Green Hoodie", 42.99, 'Clothing', 'pr-8-D5PGAWyz.png', 'uom', 'MANUAL', 35, 'UrbanDenim'),
            $this->item('SH-MEN-009', "Men's Blue Formal Shirt", 34.99, 'Clothing', 'pr-9-BDpG0GmU.png', 'uom', 'MANUAL', 30, 'StyleHub'),
            $this->item('PT-WMN-011', "Women's Beige Palazzo Pants", 28.99, 'Clothing', 'pr-11-eQyjF3vr.png', 'uom', 'MANUAL', 26, 'StyleHub'),
            $this->item('DR-KID-012', 'Girls Floral Party Dress', 31.99, 'Clothing', 'pr-12-C9E8vuC8.png', 'uom', 'MANUAL', 24, 'TinyTrend'),
            $this->item('SH-MEN-013', "Men's Checked Casual Shirt", 33.99, 'Clothing', 'pr-13-DYerNIVt.png', 'uom', 'MANUAL', 30, 'UrbanDenim'),
            $this->item('WT-MEN-001', 'Stainless Steel Analog Watch', 129.99, 'Watches', 'pr-19-DZ7YoUrR.png', 'uom', 'MANUAL', 15, 'Chronos'),
            $this->item('TP-WMN-014', "Women's Wrap Style Top", 24.99, 'Clothing', 'pr-14-CCZuqt_h.png', 'uom', 'MANUAL', 34, 'StyleHub'),
            $this->item('HD-MEN-001', "Men's Black Hoodie", 39.99, 'Clothing', 'pr-1-DpkbRlV7.png', 'uom', 'MANUAL', 38, 'UrbanDenim'),
            $this->item('FT-MEN-001', "Men's Leather Ankle Boots", 79.99, 'Footwear', 'pr-15-6fjzOYEd.png', 'uom', 'MANUAL', 20, 'PulseFit'),
            $this->item('FT-WMN-003', "Women's Glitter High Heels", 49.99, 'Footwear', 'pr-17-DRBVlZ_i.png', 'uom', 'MANUAL', 18, 'GlamourWalk'),
            $this->item('FT-MEN-004', "Men's Casual Sneakers", 54.99, 'Footwear', 'pr-18-7yzb-3ya.png', 'uom', 'MANUAL', 28, 'PulseFit'),
            $this->item('AC-WLT-002', "Men's Genuine Leather Wallet", 29.99, 'Accessories', 'pr-28-DCx9aLKb.png', 'uom', 'MANUAL', 40, 'NovaWear'),
            $this->item('AC-BLT-003', "Men's Classic Leather Belt", 19.99, 'Accessories', 'pr-29-BJ3rc4Q5.png', 'uom', 'MANUAL', 42, 'NovaWear'),
            $this->item('BT-MKP-021', 'Liquid Foundation Makeup', 24.99, 'Beauty', 'pr-37-CrN88d3y.png', 'uom', 'MANUAL', 36, 'GlowGoddess'),
            $this->item('BT-SKN-022', 'Herbal Skincare Essentials Set', 49.99, 'Beauty', 'pr-38-C9wZYe_k.png', 'uom', 'MANUAL', 20, 'GlowGoddess'),
            $this->item('FT-CRD-032', 'Indoor Exercise Spin Bike', 349.99, 'Fitness', 'pr-41-BqS_jmfx.png', 'uom', 'MANUAL', 8, 'IronPeak'),
            $this->item('FT-BCH-033', 'Multi-Function Bench Press Set', 499.99, 'Fitness', 'pr-42-J9Sh46nX.png', 'uom', 'MANUAL', 6, 'IronPeak'),
        ];
    }

    private function item(
        string $sku,
        string $name,
        float $price,
        string $category,
        string $image,
        string $itemType,
        ?string $uomGroup,
        int $stock,
        string $brand
    ): array {
        return [
            'sku' => $sku,
            'name' => $name,
            'foreign_name' => null,
            'description' => $brand.' sample catalog item.',
            'price' => $price,
            'stock' => $stock,
            'item_type' => $itemType,
            'uom_group' => $uomGroup,
            'category' => $category,
            'image' => $image,
            'is_premium' => false,
            'is_featured' => false,
            'is_new_arrival' => false,
            'is_try_on_enabled' => $category === 'Clothing',
        ];
    }

    private function group(string $name, array $values): array
    {
        return ['name' => $name, 'values' => $values];
    }

    private function value(string $name, string $skuSuffix, float $priceAdjustment = 0, ?string $colorHex = null): array
    {
        return [
            'name' => $name,
            'sku_suffix' => $skuSuffix,
            'price_adjustment' => $priceAdjustment,
            'color_hex' => $colorHex,
        ];
    }
}
