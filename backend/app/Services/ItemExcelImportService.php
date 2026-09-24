<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariation;
use App\Models\PriceList;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Repositories\ItemRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemExcelImportService
{
    private const MAX_ROWS = 500;

    private const MAX_DETAIL_ROWS = 1000;

    private const ITEM_HEADERS = [
        'sku', 'name', 'foreign_name', 'item_type', 'category', 'branch', 'uom_group',
        'price_list', 'currency', 'price', 'stock', 'status', 'premium', 'featured',
        'new_arrival', 'try_on', 'sort_order', 'description',
    ];

    private const UOM_PRICE_HEADERS = [
        'item_sku', 'unit_code', 'reduce_by_percent', 'price', 'auto', 'active',
    ];

    private const OPTION_HEADERS = [
        'item_sku', 'group_key', 'variation', 'group_name', 'group_foreign_name', 'type',
        'selection_type', 'required', 'min_selections', 'max_selections', 'group_sort_order',
        'group_status', 'value_key', 'value_name', 'value_foreign_name', 'sku_suffix',
        'color_hex', 'price_adjustment', 'default', 'value_sort_order', 'value_status',
    ];

    private const VARIANT_HEADERS = [
        'item_sku', 'sku', 'barcode', 'name', 'price', 'stock', 'default',
        'sort_order', 'status', 'option_value_keys',
    ];

    public function __construct(private ItemRepository $items)
    {
    }

    public function makeTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setTitle('Items Import Template')
            ->setSubject('Bulk item creation with UOM prices, options, and variants');

        $items = $spreadsheet->getActiveSheet();
        $this->configureInputSheet(
            $items,
            'Items',
            self::ITEM_HEADERS,
            ['A', 'B', 'D', 'G', 'I', 'J', 'K'],
            [18, 28, 24, 14, 22, 18, 18, 18, 12, 13, 11, 13, 12, 12, 14, 12, 12, 38],
            self::MAX_ROWS
        );
        $items->getStyle('A2:A'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('@');
        $items->getStyle('J2:J'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $items->getStyle('K2:K'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0');

        $uomPrices = $spreadsheet->createSheet();
        $this->configureInputSheet(
            $uomPrices,
            'UOM Prices',
            self::UOM_PRICE_HEADERS,
            ['A', 'B', 'E', 'F'],
            [18, 18, 20, 16, 12, 12],
            self::MAX_DETAIL_ROWS
        );
        $uomPrices->getStyle('C2:D'.(self::MAX_DETAIL_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0.00');

        $options = $spreadsheet->createSheet();
        $this->configureInputSheet(
            $options,
            'Options',
            self::OPTION_HEADERS,
            ['A', 'B', 'D', 'F', 'G', 'H', 'L', 'M', 'N', 'S', 'U'],
            [18, 18, 22, 24, 24, 14, 16, 12, 16, 16, 16, 14, 18, 24, 24, 18, 16, 20, 12, 16, 14],
            self::MAX_DETAIL_ROWS
        );
        $options->getStyle('R2:R'.(self::MAX_DETAIL_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0.00');

        $variants = $spreadsheet->createSheet();
        $this->configureInputSheet(
            $variants,
            'Variants',
            self::VARIANT_HEADERS,
            ['A', 'B', 'F', 'G', 'I', 'J'],
            [18, 20, 20, 26, 16, 12, 12, 12, 13, 42],
            self::MAX_DETAIL_ROWS
        );
        $variants->getStyle('A2:C'.(self::MAX_DETAIL_ROWS + 1))->getNumberFormat()->setFormatCode('@');
        $variants->getStyle('E2:E'.(self::MAX_DETAIL_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0.00');

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->setShowGridlines(false);
        $instructions->fromArray([
            ['Items import guide'],
            ['1. Items', 'Add every new item here first. Existing item and variant SKUs are rejected and never overwritten.'],
            ['2. UOM Prices', 'For UOM items, add one row per unit. unit_code must belong to the item UOM group. Leave price blank when auto is Yes.'],
            ['3. Options', 'For variation items, use one row per option value. Repeat item_sku and group_key. Fill group columns on the first row; later rows for that group may leave them blank.'],
            ['4. Variants', 'Add each sellable combination. Join one value_key from every variant group with |, for example size_m|color_red. Do not include modifier values.'],
            ['Variant groups', 'Type variant imports as single-select, required, minimum 1 and maximum 1. Every variant group needs at least one sellable variant.'],
            ['Modifier groups', 'Type modifier may be single or multiple. Minimum cannot exceed maximum, and maximum cannot exceed the number of values.'],
            ['Amber cells', 'Amber columns are required. In Options, group setup columns are required only on the first row of each group. UOM price is required only when auto is No.'],
            ['Reference fields', 'Use dropdowns. Item links use SKU; category and variation use name; branch, UOM group, price list, currency and unit use code.'],
            ['Yes / No', 'Use Yes or No for flags. In Items, blank flag cells mean No.'],
            ['Limits', 'Up to '.self::MAX_ROWS.' items and '.self::MAX_DETAIL_ROWS.' rows per extra sheet. Any error rejects the whole file.'],
            ['Import', 'Keep all sheet names and headers unchanged. Complete only the sheets you need, save as .xlsx, then upload from Items.'],
        ], null, 'A1');
        $instructions->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']]],
        ]);
        $instructions->getStyle('A2:A12')->getFont()->setBold(true)->getColor()->setRGB('334155');
        $instructions->getStyle('A1:B12')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $instructions->getStyle('B2:B12')->getAlignment()->setWrapText(true);
        $instructions->getColumnDimension('A')->setWidth(22);
        $instructions->getColumnDimension('B')->setWidth(105);
        for ($row = 2; $row <= 12; $row++) {
            $instructions->getRowDimension($row)->setRowHeight(40);
        }

        $reference = $spreadsheet->createSheet();
        $reference->setTitle('Reference Data');
        $reference->setShowGridlines(false);
        $referenceColumns = [
            'A' => ['Item types', ['uom', 'variation']],
            'B' => ['Categories', Category::query()->where('status', 'Active')->orderBy('name')->pluck('name')->all()],
            'C' => ['Branch codes', Branch::query()->where('status', 'Active')->orderBy('sort_order')->orderBy('name')->pluck('code')->all()],
            'D' => ['UOM group codes', UomGroup::query()->where('status', 'Active')->orderBy('name')->pluck('code')->all()],
            'E' => ['Price list codes', PriceList::query()->where('status', 'Active')->orderBy('name')->pluck('code')->all()],
            'F' => ['Currency codes', Currency::query()->where('status', 'Active')->orderBy('sort_order')->orderBy('code')->pluck('code')->all()],
            'G' => ['Statuses', ['Active', 'Inactive']],
            'H' => ['Yes / No', ['Yes', 'No']],
            'I' => ['Unit codes', UnitOfMeasure::query()->where('status', 'Active')->orderBy('code')->pluck('code')->all()],
            'J' => ['Variation names', ItemVariation::query()->where('status', 'Active')->orderBy('name')->pluck('name')->all()],
            'K' => ['Group types', ['variant', 'modifier']],
            'L' => ['Selection types', ['single', 'multiple']],
        ];
        foreach ($referenceColumns as $column => [$heading, $values]) {
            $reference->setCellValue($column.'1', $heading);
            foreach (array_values($values) as $index => $value) {
                $reference->setCellValue($column.($index + 2), $value);
            }
            $reference->getColumnDimension($column)->setWidth(22);
        }
        $reference->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $reference->freezePane('A2');

        $this->addReferenceValidation($items, 'D', 'A', 2, false, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'E', 'B', count($referenceColumns['B'][1]), true, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'F', 'C', count($referenceColumns['C'][1]), true, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'G', 'D', count($referenceColumns['D'][1]), false, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'H', 'E', count($referenceColumns['E'][1]), true, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'I', 'F', count($referenceColumns['F'][1]), true, self::MAX_ROWS);
        $this->addReferenceValidation($items, 'L', 'G', 2, false, self::MAX_ROWS);
        foreach (['M', 'N', 'O', 'P'] as $column) {
            $this->addReferenceValidation($items, $column, 'H', 2, true, self::MAX_ROWS);
        }

        $itemSkuFormula = "'Items'!\$A\$2:\$A\$".(self::MAX_ROWS + 1);
        $this->addSheetValidation($uomPrices, 'A', $itemSkuFormula, false);
        $this->addReferenceValidation($uomPrices, 'B', 'I', count($referenceColumns['I'][1]), false);
        foreach (['E', 'F'] as $column) {
            $this->addReferenceValidation($uomPrices, $column, 'H', 2, false);
        }

        $this->addSheetValidation($options, 'A', $itemSkuFormula, false);
        $this->addReferenceValidation($options, 'C', 'J', count($referenceColumns['J'][1]), true);
        $this->addReferenceValidation($options, 'F', 'K', 2, true);
        $this->addReferenceValidation($options, 'G', 'L', 2, true);
        $this->addReferenceValidation($options, 'H', 'H', 2, true);
        $this->addReferenceValidation($options, 'L', 'G', 2, true);
        $this->addReferenceValidation($options, 'S', 'H', 2, false);
        $this->addReferenceValidation($options, 'U', 'G', 2, false);

        $this->addSheetValidation($variants, 'A', $itemSkuFormula, false);
        $this->addReferenceValidation($variants, 'G', 'H', 2, false);
        $this->addReferenceValidation($variants, 'I', 'G', 2, false);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function import(UploadedFile $file): int
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'import_file' => 'The Excel file could not be read. Download a fresh template and try again.',
            ]);
        }

        $errors = [];
        $itemRows = $this->readSheet($spreadsheet, 'Items', self::ITEM_HEADERS, self::MAX_ROWS, $errors, true);
        $uomRows = $this->readSheet($spreadsheet, 'UOM Prices', self::UOM_PRICE_HEADERS, self::MAX_DETAIL_ROWS, $errors);
        $optionRows = $this->readSheet($spreadsheet, 'Options', self::OPTION_HEADERS, self::MAX_DETAIL_ROWS, $errors);
        $variantRows = $this->readSheet($spreadsheet, 'Variants', self::VARIANT_HEADERS, self::MAX_DETAIL_ROWS, $errors);
        $spreadsheet->disconnectWorksheets();

        $categoryMap = $this->modelMap(Category::query()->where('status', 'Active')->get(), ['name']);
        $branchMap = $this->modelMap(Branch::query()->where('status', 'Active')->get(), ['code', 'name']);
        $uomGroups = UomGroup::query()->where('status', 'Active')->with(['units.unit'])->get();
        $uomGroupMap = $this->modelMap($uomGroups, ['code', 'name']);
        $priceListMap = $this->modelMap(PriceList::query()->where('status', 'Active')->get(), ['code', 'name']);
        $unitMap = $this->modelMap(UnitOfMeasure::query()->where('status', 'Active')->get(), ['code', 'name']);
        $variationMap = $this->modelMap(ItemVariation::query()->where('status', 'Active')->get(), ['name']);
        $currencies = Currency::query()->where('status', 'Active')->get();
        $currencyMap = $this->modelMap($currencies, ['code', 'name']);
        $baseCurrencyCode = (string) data_get(admin_current_tenant()?->general_settings, 'currency', '');
        $baseCurrency = $currencies->first(fn (Currency $currency) => strcasecmp($currency->code, $baseCurrencyCode) === 0)
            ?: $currencies->first();

        $items = [];
        $contexts = [];
        $itemIndexBySku = [];
        $seenItemSkus = [];

        foreach ($itemRows as $row) {
            $excelRow = $row['_excel_row'];
            $sku = trim((string) ($row['sku'] ?? ''));
            $skuKey = $this->key($sku);
            $itemType = strtolower(trim((string) ($row['item_type'] ?? 'uom')));
            $status = $this->normalizeStatus($row['status'] ?? ($itemType === 'variation' ? 'Inactive' : 'Active'));
            $stock = $this->normalizeWholeNumber($row['stock'] ?? null);
            $currencyValue = trim((string) ($row['currency'] ?? ''));
            $currency = $currencyValue === '' ? $baseCurrency : ($currencyMap[$this->key($currencyValue)] ?? null);

            $validator = Validator::make([
                'sku' => $sku,
                'name' => trim((string) ($row['name'] ?? '')),
                'item_type' => $itemType,
                'price' => $row['price'] ?? null,
                'stock' => $stock,
                'status' => $status,
                'sort_order' => ($row['sort_order'] ?? '') === '' ? 0 : $row['sort_order'],
            ], [
                'sku' => ['required', 'string', 'max:64'],
                'name' => ['required', 'string', 'max:255'],
                'item_type' => ['required', 'in:uom,variation'],
                'price' => ['present', 'numeric', 'min:0'],
                'stock' => ['present', 'numeric', 'min:0'],
                'status' => ['required', 'in:Active,Inactive'],
                'sort_order' => ['integer', 'min:0'],
            ]);
            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Items row {$excelRow}: {$message}";
            }
            if (is_numeric($stock) && floor((float) $stock) !== (float) $stock) {
                $errors[] = "Items row {$excelRow}: stock must be a whole number.";
            }
            if ($sku !== '' && isset($seenItemSkus[$skuKey])) {
                $errors[] = "Items row {$excelRow}: SKU {$sku} is duplicated in the file.";
            }
            if ($sku !== '') {
                $seenItemSkus[$skuKey] = true;
            }

            $uomGroupValue = trim((string) ($row['uom_group'] ?? ''));
            $uomGroup = $uomGroupValue === '' ? null : ($uomGroupMap[$this->key($uomGroupValue)] ?? null);
            if ($itemType === 'uom' && ! $uomGroup) {
                $errors[] = "Items row {$excelRow}: choose a valid active UOM group.";
            }
            if (! $currency) {
                $errors[] = "Items row {$excelRow}: choose a valid active currency.";
            } elseif (($row['price'] ?? '') !== '' && currency_decimal_count($row['price']) > (int) $currency->decimal_places) {
                $errors[] = "Items row {$excelRow}: {$currency->code} allows up to {$currency->decimal_places} decimal place(s).";
            }

            $category = $this->optionalReference($row, 'category', $categoryMap, $excelRow, $errors, 'category');
            $branch = $this->optionalReference($row, 'branch', $branchMap, $excelRow, $errors, 'branch');
            $priceList = $this->optionalReference($row, 'price_list', $priceListMap, $excelRow, $errors, 'price list');
            $flags = [];
            foreach (['premium', 'featured', 'new_arrival', 'try_on'] as $flag) {
                $flags[$flag] = $this->parseBoolean($row[$flag] ?? null);
                if ($flags[$flag] === null) {
                    $errors[] = "Items row {$excelRow}: {$flag} must be Yes or No.";
                    $flags[$flag] = false;
                }
            }

            $itemIndex = count($items);
            if ($sku !== '' && ! isset($itemIndexBySku[$skuKey])) {
                $itemIndexBySku[$skuKey] = $itemIndex;
            }
            $items[] = [
                'category_id' => $category?->id,
                'item_type' => $itemType,
                'uom_group_id' => $itemType === 'uom' ? $uomGroup?->id : null,
                'branch_id' => $branch?->id,
                'price_list_id' => $priceList?->id,
                'currency_id' => $currency?->id,
                'sku' => $sku,
                'name' => trim((string) ($row['name'] ?? '')),
                'foreign_name' => trim((string) ($row['foreign_name'] ?? '')) ?: null,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'price' => $row['price'] ?? 0,
                'rating' => null,
                'review_count' => 0,
                'stock' => $stock ?? 0,
                'is_premium' => $flags['premium'],
                'is_featured' => $flags['featured'],
                'is_new_arrival' => $flags['new_arrival'],
                'is_try_on_enabled' => $flags['try_on'],
                'sort_order' => ($row['sort_order'] ?? '') === '' ? 0 : (int) $row['sort_order'],
                'status' => $status,
                'uom_prices' => [],
                'option_groups' => [],
                'variants' => [],
            ];
            $contexts[$itemIndex] = [
                'currency' => $currency,
                'uom_group' => $uomGroup,
                'group_indexes' => [],
                'value_groups' => [],
                'variant_groups' => [],
                'combinations' => [],
                'default_variants' => 0,
            ];
        }

        if ($items === []) {
            $errors[] = 'Add at least one item row before importing.';
        }

        $this->prepareUomPrices($uomRows, $items, $contexts, $itemIndexBySku, $unitMap, $errors);
        $this->prepareOptions($optionRows, $items, $contexts, $itemIndexBySku, $variationMap, $errors);
        $seenVariantSkus = [];
        $seenBarcodes = [];
        $this->prepareVariants(
            $variantRows,
            $items,
            $contexts,
            $itemIndexBySku,
            $seenItemSkus,
            $seenVariantSkus,
            $seenBarcodes,
            $errors
        );

        foreach ($items as $itemIndex => $item) {
            if ($item['item_type'] === 'variation' && $item['status'] === 'Active' && $item['option_groups'] === []) {
                $errors[] = "Items: active variation item {$item['sku']} needs at least one row in Options.";
            }
            foreach ($item['option_groups'] as $group) {
                if ($group['values'] === []) {
                    $errors[] = "Options: group {$group['key']} for item {$item['sku']} needs at least one value.";
                }
                if ($group['max_selections'] !== null && $group['min_selections'] > $group['max_selections']) {
                    $errors[] = "Options: group {$group['key']} has minimum selections greater than maximum.";
                }
                if ($group['max_selections'] !== null && $group['max_selections'] > count($group['values'])) {
                    $errors[] = "Options: group {$group['key']} has maximum selections greater than its number of values.";
                }
                if ($group['type'] === 'modifier' && $group['selection_type'] === 'single'
                    && collect($group['values'])->where('is_default', true)->count() > 1) {
                    $errors[] = "Options: single-select modifier {$group['key']} can have only one default value.";
                }
            }
            if ($contexts[$itemIndex]['variant_groups'] !== [] && $item['variants'] === []) {
                $errors[] = "Variants: item {$item['sku']} needs at least one sellable variant.";
            }
        }

        if ($seenItemSkus !== []) {
            foreach (Item::query()->whereIn(DB::raw('LOWER(sku)'), array_keys($seenItemSkus))->pluck('sku') as $sku) {
                $errors[] = "SKU {$sku} already exists. Existing items are not overwritten.";
            }
        }
        if ($seenVariantSkus !== []) {
            foreach (ItemVariant::query()->whereIn(DB::raw('LOWER(sku)'), array_keys($seenVariantSkus))->pluck('sku') as $sku) {
                $errors[] = "Variant SKU {$sku} already exists.";
            }
        }
        if ($seenBarcodes !== []) {
            foreach (ItemVariant::query()->whereIn(DB::raw('LOWER(barcode)'), array_keys($seenBarcodes))->pluck('barcode') as $barcode) {
                $errors[] = "Variant barcode {$barcode} already exists.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'import_file' => array_slice(array_values(array_unique($errors)), 0, 40),
            ]);
        }

        DB::connection((new Item)->getConnectionName())->transaction(function () use ($items) {
            foreach ($items as $attributes) {
                $this->items->createForAdmin($attributes);
            }
        });

        return count($items);
    }

    private function prepareUomPrices(array $rows, array &$items, array &$contexts, array $itemIndexBySku, array $unitMap, array &$errors): void
    {
        foreach ($rows as $row) {
            $itemIndex = $this->linkedItemIndex($row, 'UOM Prices', $itemIndexBySku, $errors);
            if ($itemIndex === null) {
                continue;
            }
            $excelRow = $row['_excel_row'];
            if ($items[$itemIndex]['item_type'] !== 'uom') {
                $errors[] = "UOM Prices row {$excelRow}: {$row['item_sku']} is not a UOM item.";

                continue;
            }
            $context = &$contexts[$itemIndex];
            $unitCode = trim((string) ($row['unit_code'] ?? ''));
            $unit = $unitMap[$this->key($unitCode)] ?? null;
            if (! $unit) {
                $errors[] = "UOM Prices row {$excelRow}: choose a valid active unit code.";
            }
            $allowedUnits = collect($context['uom_group']?->units ?? [])
                ->filter(fn ($groupUnit) => $groupUnit->status === 'Active')
                ->keyBy(fn ($groupUnit) => (int) $groupUnit->unit_of_measure_id);
            if ($unit && ! $allowedUnits->has((int) $unit->id)) {
                $errors[] = "UOM Prices row {$excelRow}: unit {$unitCode} does not belong to the selected UOM group.";
            }
            if ($unit && collect($items[$itemIndex]['uom_prices'])->contains('unit_of_measure_id', (int) $unit->id)) {
                $errors[] = "UOM Prices row {$excelRow}: unit {$unitCode} is duplicated for item {$items[$itemIndex]['sku']}.";
            }

            $auto = $this->requiredBoolean($row['auto'] ?? null, 'UOM Prices', $excelRow, 'auto', $errors);
            $active = $this->requiredBoolean($row['active'] ?? null, 'UOM Prices', $excelRow, 'active', $errors);
            $reduction = ($row['reduce_by_percent'] ?? '') === '' ? 0 : $row['reduce_by_percent'];
            $price = $row['price'] ?? null;
            if (! is_numeric($reduction) || (float) $reduction < 0 || (float) $reduction > 100) {
                $errors[] = "UOM Prices row {$excelRow}: reduce_by_percent must be between 0 and 100.";
            }
            if ($auto === false && ($price === null || trim((string) $price) === '')) {
                $errors[] = "UOM Prices row {$excelRow}: price is required when auto is No.";
            }
            if ($price !== null && trim((string) $price) !== '' && (! is_numeric($price) || (float) $price < 0)) {
                $errors[] = "UOM Prices row {$excelRow}: price must be zero or greater.";
            } elseif ($price !== null && trim((string) $price) !== '' && $context['currency']
                && currency_decimal_count($price) > (int) $context['currency']->decimal_places) {
                $errors[] = "UOM Prices row {$excelRow}: {$context['currency']->code} allows up to {$context['currency']->decimal_places} decimal place(s).";
            }
            $baseUnitId = (int) ($context['uom_group']?->units->firstWhere('is_base_unit', true)?->unit_of_measure_id
                ?? $context['uom_group']?->base_unit_id ?? 0);
            if ($unit && (int) $unit->id === $baseUnitId && $active === false) {
                $errors[] = "UOM Prices row {$excelRow}: the base unit cannot be inactive.";
            }
            if ($unit) {
                $items[$itemIndex]['uom_prices'][] = [
                    'unit_of_measure_id' => (int) $unit->id,
                    'reduce_by_percent' => round((float) $reduction, 2),
                    'price' => $auto === true ? null : $price,
                    'is_auto' => $auto ?? false,
                    'is_active' => $active ?? false,
                ];
            }
            unset($context);
        }
    }

    private function prepareOptions(array $rows, array &$items, array &$contexts, array $itemIndexBySku, array $variationMap, array &$errors): void
    {
        foreach ($rows as $row) {
            $itemIndex = $this->linkedItemIndex($row, 'Options', $itemIndexBySku, $errors);
            if ($itemIndex === null) {
                continue;
            }
            $excelRow = $row['_excel_row'];
            if ($items[$itemIndex]['item_type'] !== 'variation') {
                $errors[] = "Options row {$excelRow}: {$row['item_sku']} is not a variation item.";

                continue;
            }
            $context = &$contexts[$itemIndex];
            $groupKey = trim((string) ($row['group_key'] ?? ''));
            $groupKeyNormalized = $this->key($groupKey);
            if ($groupKey === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $groupKey) || mb_strlen($groupKey) > 64) {
                $errors[] = "Options row {$excelRow}: group_key is required and may contain letters, numbers, dashes, and underscores.";
                unset($context);

                continue;
            }

            $groupIndex = $context['group_indexes'][$groupKeyNormalized] ?? null;
            if ($groupIndex === null) {
                $groupName = trim((string) ($row['group_name'] ?? ''));
                $type = strtolower(trim((string) ($row['type'] ?? '')));
                $selectionType = strtolower(trim((string) ($row['selection_type'] ?? '')));
                $required = $this->requiredBoolean($row['required'] ?? null, 'Options', $excelRow, 'required', $errors);
                $groupStatus = $this->normalizeStatus($row['group_status'] ?? 'Active');
                if ($groupName === '') {
                    $errors[] = "Options row {$excelRow}: group_name is required on the first row of a group.";
                }
                if (! in_array($type, ['variant', 'modifier'], true)) {
                    $errors[] = "Options row {$excelRow}: type must be variant or modifier on the first row of a group.";
                }
                if (! in_array($selectionType, ['single', 'multiple'], true)) {
                    $errors[] = "Options row {$excelRow}: selection_type must be single or multiple on the first row of a group.";
                }
                if (! in_array($groupStatus, ['Active', 'Inactive'], true)) {
                    $errors[] = "Options row {$excelRow}: group_status must be Active or Inactive.";
                }
                $variationValue = trim((string) ($row['variation'] ?? ''));
                $variation = $variationValue === '' ? null : ($variationMap[$this->key($variationValue)] ?? null);
                if ($variationValue !== '' && ! $variation) {
                    $errors[] = "Options row {$excelRow}: {$variationValue} is not a valid active variation.";
                }
                $min = ($row['min_selections'] ?? '') === '' ? (($required ?? false) ? 1 : 0) : $row['min_selections'];
                $max = ($row['max_selections'] ?? '') === '' ? ($selectionType === 'single' ? 1 : null) : $row['max_selections'];
                if (! is_numeric($min) || (int) $min < 0 || (string) (int) $min !== (string) $min) {
                    $errors[] = "Options row {$excelRow}: min_selections must be a whole number of zero or greater.";
                }
                if ($max !== null && (! is_numeric($max) || (int) $max < 1 || (string) (int) $max !== (string) $max)) {
                    $errors[] = "Options row {$excelRow}: max_selections must be a whole number of one or greater.";
                }
                if ($type === 'variant') {
                    $selectionType = 'single';
                    $required = true;
                    $min = 1;
                    $max = 1;
                    $context['variant_groups'][] = $groupKeyNormalized;
                }
                if (count($items[$itemIndex]['option_groups']) >= 20) {
                    $errors[] = "Options row {$excelRow}: an item can have at most 20 groups.";
                }
                $groupIndex = count($items[$itemIndex]['option_groups']);
                $items[$itemIndex]['option_groups'][] = [
                    'key' => $groupKey,
                    'item_variation_id' => $variation?->id,
                    'name' => $groupName,
                    'foreign_name' => trim((string) ($row['group_foreign_name'] ?? '')) ?: null,
                    'type' => $type,
                    'selection_type' => $selectionType,
                    'is_required' => $required ?? false,
                    'min_selections' => (int) $min,
                    'max_selections' => $max === null ? null : (int) $max,
                    'sort_order' => ($row['group_sort_order'] ?? '') === '' ? $groupIndex : (int) $row['group_sort_order'],
                    'status' => $groupStatus,
                    'values' => [],
                ];
                $context['group_indexes'][$groupKeyNormalized] = $groupIndex;
            }

            $group = &$items[$itemIndex]['option_groups'][$groupIndex];
            $valueKey = trim((string) ($row['value_key'] ?? ''));
            $valueKeyNormalized = $this->key($valueKey);
            $valueName = trim((string) ($row['value_name'] ?? ''));
            $isDefault = $this->requiredBoolean($row['default'] ?? null, 'Options', $excelRow, 'default', $errors);
            $valueStatus = $this->normalizeStatus($row['value_status'] ?? 'Active');
            $adjustment = ($row['price_adjustment'] ?? '') === '' ? 0 : $row['price_adjustment'];
            $color = trim((string) ($row['color_hex'] ?? ''));
            if ($valueKey === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $valueKey) || mb_strlen($valueKey) > 64) {
                $errors[] = "Options row {$excelRow}: value_key is required and may contain letters, numbers, dashes, and underscores.";
            }
            if ($valueName === '') {
                $errors[] = "Options row {$excelRow}: value_name is required.";
            }
            if (isset($context['value_groups'][$valueKeyNormalized])) {
                $errors[] = "Options row {$excelRow}: value_key {$valueKey} is duplicated for item {$items[$itemIndex]['sku']}.";
            }
            if (! is_numeric($adjustment)) {
                $errors[] = "Options row {$excelRow}: price_adjustment must be a number.";
            } elseif ($context['currency'] && currency_decimal_count($adjustment) > (int) $context['currency']->decimal_places) {
                $errors[] = "Options row {$excelRow}: {$context['currency']->code} allows up to {$context['currency']->decimal_places} decimal place(s).";
            }
            if ($color !== '' && ! preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color)) {
                $errors[] = "Options row {$excelRow}: color_hex must use #RRGGBB or #RRGGBBAA.";
            }
            if (! in_array($valueStatus, ['Active', 'Inactive'], true)) {
                $errors[] = "Options row {$excelRow}: value_status must be Active or Inactive.";
            }
            if (count($group['values']) >= 100) {
                $errors[] = "Options row {$excelRow}: a group can have at most 100 values.";
            }
            $valueIndex = count($group['values']);
            $group['values'][] = [
                'key' => $valueKey,
                'name' => $valueName,
                'foreign_name' => trim((string) ($row['value_foreign_name'] ?? '')) ?: null,
                'sku_suffix' => trim((string) ($row['sku_suffix'] ?? '')) ?: null,
                'color_hex' => $color ?: null,
                'price_adjustment' => $adjustment,
                'is_default' => $group['type'] === 'variant' ? false : ($isDefault ?? false),
                'sort_order' => ($row['value_sort_order'] ?? '') === '' ? $valueIndex : (int) $row['value_sort_order'],
                'status' => $valueStatus,
            ];
            $context['value_groups'][$valueKeyNormalized] = $groupKeyNormalized;
            unset($group, $context);
        }
    }

    private function prepareVariants(array $rows, array &$items, array &$contexts, array $itemIndexBySku, array $seenItemSkus, array &$seenVariantSkus, array &$seenBarcodes, array &$errors): void
    {
        foreach ($rows as $row) {
            $itemIndex = $this->linkedItemIndex($row, 'Variants', $itemIndexBySku, $errors);
            if ($itemIndex === null) {
                continue;
            }
            $excelRow = $row['_excel_row'];
            if ($items[$itemIndex]['item_type'] !== 'variation') {
                $errors[] = "Variants row {$excelRow}: {$row['item_sku']} is not a variation item.";

                continue;
            }
            $context = &$contexts[$itemIndex];
            $sku = trim((string) ($row['sku'] ?? ''));
            $skuKey = $this->key($sku);
            $barcode = trim((string) ($row['barcode'] ?? ''));
            $barcodeKey = $this->key($barcode);
            $stock = $this->normalizeWholeNumber($row['stock'] ?? null);
            $price = $row['price'] ?? null;
            $status = $this->normalizeStatus($row['status'] ?? 'Active');
            $isDefault = $this->requiredBoolean($row['default'] ?? null, 'Variants', $excelRow, 'default', $errors);
            $keys = collect(preg_split('/[|,]/', (string) ($row['option_value_keys'] ?? '')))
                ->map(fn ($key) => trim((string) $key))->filter()->values();
            $normalizedKeys = $keys->map(fn ($key) => $this->key($key));

            if ($sku === '' || mb_strlen($sku) > 64) {
                $errors[] = "Variants row {$excelRow}: sku is required and must not exceed 64 characters.";
            }
            if ($sku !== '' && (isset($seenVariantSkus[$skuKey]) || isset($seenItemSkus[$skuKey]))) {
                $errors[] = "Variants row {$excelRow}: SKU {$sku} is duplicated in the file.";
            }
            if ($sku !== '') {
                $seenVariantSkus[$skuKey] = true;
            }
            if ($barcode !== '' && isset($seenBarcodes[$barcodeKey])) {
                $errors[] = "Variants row {$excelRow}: barcode {$barcode} is duplicated in the file.";
            }
            if ($barcode !== '') {
                $seenBarcodes[$barcodeKey] = true;
            }
            if (! is_numeric($stock) || (float) $stock < 0 || floor((float) $stock) !== (float) $stock) {
                $errors[] = "Variants row {$excelRow}: stock must be a whole number of zero or greater.";
            }
            if ($price !== null && trim((string) $price) !== '' && (! is_numeric($price) || (float) $price < 0)) {
                $errors[] = "Variants row {$excelRow}: price must be zero or greater.";
            } elseif ($price !== null && trim((string) $price) !== '' && $context['currency']
                && currency_decimal_count($price) > (int) $context['currency']->decimal_places) {
                $errors[] = "Variants row {$excelRow}: {$context['currency']->code} allows up to {$context['currency']->decimal_places} decimal place(s).";
            }
            if (! in_array($status, ['Active', 'Inactive'], true)) {
                $errors[] = "Variants row {$excelRow}: status must be Active or Inactive.";
            }
            if ($normalizedKeys->duplicates()->isNotEmpty()) {
                $errors[] = "Variants row {$excelRow}: option_value_keys contains a duplicate value.";
            }
            foreach ($normalizedKeys as $key) {
                if (! isset($context['value_groups'][$key])) {
                    $errors[] = "Variants row {$excelRow}: unknown option value key {$key}.";
                }
            }
            $selectedGroups = $normalizedKeys->map(fn ($key) => $context['value_groups'][$key] ?? null)->filter();
            foreach ($context['variant_groups'] as $groupKey) {
                if ($selectedGroups->filter(fn ($selectedGroup) => $selectedGroup === $groupKey)->count() !== 1) {
                    $errors[] = "Variants row {$excelRow}: select exactly one value from variant group {$groupKey}.";
                }
            }
            if ($selectedGroups->diff($context['variant_groups'])->isNotEmpty()) {
                $errors[] = "Variants row {$excelRow}: variants may only contain values from variant groups.";
            }
            $combination = $normalizedKeys->sort()->implode('|');
            if ($combination !== '' && in_array($combination, $context['combinations'], true)) {
                $errors[] = "Variants row {$excelRow}: this option combination is duplicated for item {$items[$itemIndex]['sku']}.";
            }
            if ($combination !== '') {
                $context['combinations'][] = $combination;
            }
            if ($isDefault === true && ++$context['default_variants'] > 1) {
                $errors[] = "Variants row {$excelRow}: item {$items[$itemIndex]['sku']} can have only one default variant.";
            }
            $items[$itemIndex]['variants'][] = [
                'sku' => $sku,
                'barcode' => $barcode ?: null,
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'price' => $price === null || trim((string) $price) === '' ? null : $price,
                'stock' => (int) $stock,
                'is_default' => $isDefault ?? false,
                'sort_order' => ($row['sort_order'] ?? '') === '' ? count($items[$itemIndex]['variants']) : (int) $row['sort_order'],
                'status' => $status,
                'option_value_keys' => $keys->all(),
            ];
            unset($context);
        }
    }

    private function configureInputSheet(Worksheet $sheet, string $title, array $headers, array $requiredColumns, array $widths, int $maxRows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setShowGridlines(false);
        $lastColumn = $sheet->getHighestColumn();
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1E293B']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        foreach ($requiredColumns as $column) {
            $sheet->getStyle($column.'2:'.$column.($maxRows + 1))
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7D6');
        }
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
    }

    private function addReferenceValidation(Worksheet $sheet, string $target, string $source, int $count, bool $allowBlank, int $maxRows = self::MAX_DETAIL_ROWS): void
    {
        if ($count > 0) {
            $this->addSheetValidation($sheet, $target, "'Reference Data'!$".$source.'$2:$'.$source.'$'.($count + 1), $allowBlank, $maxRows);
        }
    }

    private function addSheetValidation(Worksheet $sheet, string $column, string $formula, bool $allowBlank, int $maxRows = self::MAX_DETAIL_ROWS): void
    {
        for ($row = 2; $row <= $maxRows + 1; $row++) {
            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank($allowBlank)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Invalid value')
                ->setError('Choose a value from the list.')
                ->setFormula1($formula);
            $sheet->getCell($column.$row)->setDataValidation($validation);
        }
    }

    private function readSheet(Spreadsheet $spreadsheet, string $name, array $requiredHeaders, int $maxRows, array &$errors, bool $required = false): array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (! $sheet) {
            if ($required) {
                $errors[] = "The {$name} sheet is missing.";
            }

            return [];
        }
        $rows = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow(), null, false, false, false);
        if ($rows === []) {
            if ($required) {
                $errors[] = "The {$name} sheet is empty.";
            }

            return [];
        }
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), array_shift($rows));
        $missing = array_values(array_diff($requiredHeaders, $headers));
        if ($missing !== []) {
            $errors[] = "The {$name} header row is missing: ".implode(', ', $missing).'.';

            return [];
        }
        $result = [];
        foreach ($rows as $index => $values) {
            $row = ['_excel_row' => $index + 2];
            foreach ($headers as $columnIndex => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$columnIndex] ?? null;
                }
            }
            if ($this->isBlankRow($row)) {
                continue;
            }
            if (count($result) >= $maxRows) {
                $errors[] = "The {$name} sheet can contain at most {$maxRows} non-empty rows.";
                break;
            }
            $result[] = $row;
        }

        return $result;
    }

    private function linkedItemIndex(array $row, string $sheet, array $itemIndexBySku, array &$errors): ?int
    {
        $sku = trim((string) ($row['item_sku'] ?? ''));
        $index = $itemIndexBySku[$this->key($sku)] ?? null;
        if ($sku === '' || $index === null) {
            $errors[] = "{$sheet} row {$row['_excel_row']}: item_sku must match an SKU on the Items sheet.";

            return null;
        }

        return $index;
    }

    private function optionalReference(array $row, string $field, array $map, int $excelRow, array &$errors, string $label): mixed
    {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value === '') {
            return null;
        }
        $model = $map[$this->key($value)] ?? null;
        if (! $model) {
            $errors[] = "Items row {$excelRow}: {$value} is not a valid active {$label}.";
        }

        return $model;
    }

    private function requiredBoolean(mixed $value, string $sheet, int $row, string $field, array &$errors): ?bool
    {
        if ($value === null || trim((string) $value) === '') {
            $errors[] = "{$sheet} row {$row}: {$field} must be Yes or No.";

            return null;
        }
        $parsed = $this->parseBoolean($value);
        if ($parsed === null) {
            $errors[] = "{$sheet} row {$row}: {$field} must be Yes or No.";
        }

        return $parsed;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if ($value === null || trim((string) $value) === '') {
            return false;
        }

        return match (strtolower(trim((string) $value))) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => null,
        };
    }

    private function normalizeHeader(mixed $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $header))), '_');
    }

    private function normalizeWholeNumber(mixed $value): mixed
    {
        if ($value === false) {
            return 0;
        }

        if (is_numeric($value) && floor((float) $value) === (float) $value) {
            return (int) $value;
        }

        return $value;
    }

    private function normalizeStatus(mixed $value): string
    {
        return ucfirst(strtolower(trim((string) $value)));
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->except('_excel_row')->every(fn ($value) => trim((string) $value) === '');
    }

    private function modelMap(iterable $models, array $attributes): array
    {
        $map = [];
        foreach ($models as $model) {
            foreach ($attributes as $attribute) {
                $value = trim((string) $model->{$attribute});
                if ($value !== '') {
                    $map[$this->key($value)] = $model;
                }
            }
        }

        return $map;
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
