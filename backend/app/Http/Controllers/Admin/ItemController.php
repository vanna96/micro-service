<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariation;
use App\Models\PriceList;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Repositories\BranchRepository;
use App\Repositories\CurrencyRepository;
use App\Repositories\ItemRepository;
use App\Repositories\PriceListRepository;
use App\Repositories\UomGroupRepository;
use App\Services\ItemExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    protected ItemRepository $items;

    protected BranchRepository $branches;

    protected CurrencyRepository $currencies;

    protected PriceListRepository $priceLists;

    protected UomGroupRepository $uomGroups;

    public function __construct(
        ItemRepository $items,
        BranchRepository $branches,
        CurrencyRepository $currencies,
        PriceListRepository $priceLists,
        UomGroupRepository $uomGroups
    ) {
        $this->items = $items;
        $this->branches = $branches;
        $this->currencies = $currencies;
        $this->priceLists = $priceLists;
        $this->uomGroups = $uomGroups;
        $this->middleware('admin.permission:items.view')->only(['index']);
        $this->middleware('admin.permission:items.create')->only(['create', 'store', 'importTemplate', 'import']);
        $this->middleware('admin.permission:items.edit')->only(['edit', 'update', 'destroyGallery']);
        $this->middleware('admin.permission:items.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.items.index', [
            'items' => $this->items->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $baseCurrency = $this->baseCurrency();

        return view('admin.items.create', [
            'item' => new Item([
                'status' => 'Active',
                'item_type' => 'uom',
                'stock_control' => true,
                'purchase' => true,
                'sale' => true,
                'is_try_on_enabled' => true,
                'currency_id' => $baseCurrency?->id,
            ]),
            'branchOptions' => $this->branches->getOptions(),
            'priceListOptions' => $this->priceLists->getOptions(),
            'currencyOptions' => $this->currencies->getActiveOptions(),
            'categoryOptions' => Category::query()->orderBy('name')->get(),
            'uomGroupOptions' => $this->uomGroups->getOptions(),
            'variationMasters' => $this->variationMasters(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $this->normalizeFlags($request);
        $validated = $this->validateItem($request);
        $validated = $this->prepareConfigurationPayload($request, $validated);
        $this->items->createForAdmin($validated);

        return redirect()
            ->route('admin.items.index')
            ->with('status', 'Item created successfully.');
    }

    public function importTemplate(Request $request, ItemExcelImportService $importer): StreamedResponse
    {
        $this->requiredTenant($request);
        $spreadsheet = $importer->makeTemplate();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'items-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function import(Request $request, ItemExcelImportService $importer): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $count = $importer->import($validated['import_file']);

        return redirect()
            ->route('admin.items.index')
            ->with('status', $count.' item'.($count === 1 ? '' : 's').' imported successfully.');
    }

    public function edit(Request $request, string $item): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $baseCurrency = $this->baseCurrency();

        if (! $itemModel->currency_id && $baseCurrency) {
            $itemModel->currency_id = $baseCurrency->id;
        }

        return view('admin.items.edit', [
            'item' => $itemModel,
            'branchOptions' => $this->branches->getOptions($itemModel->branch_id),
            'priceListOptions' => $this->priceLists->getOptions($itemModel->price_list_id),
            'currencyOptions' => $this->currencies->getActiveOptions(),
            'categoryOptions' => Category::query()->orderBy('name')->get(),
            'uomGroupOptions' => $this->uomGroups->getOptions($itemModel->uom_group_id),
            'variationMasters' => $this->variationMasters(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        $this->requiredTenant($request);
        $this->normalizeFlags($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $validated = $this->validateItem($request, $itemModel);
        $validated = $this->prepareConfigurationPayload($request, $validated);
        $this->items->updateForAdmin($itemModel, $validated);

        return redirect()
            ->route('admin.items.index')
            ->with('status', 'Item updated successfully.');
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        $this->requiredTenant($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $this->items->deleteForAdmin($itemModel);

        return redirect()
            ->route('admin.items.index')
            ->with('status', 'Item deleted successfully.');
    }

    public function destroyGallery(Request $request, string $item, string $gallery): JsonResponse
    {
        $this->requiredTenant($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $this->items->deleteGalleryImage($itemModel, (int) $gallery);

        return response()->json([
            'success' => true,
            'message' => 'Gallery image removed successfully.',
        ]);
    }

    private function validateItem(Request $request, ?Item $item = null): array
    {
        $itemId = $item?->id;
        $itemTable = $this->itemValidationTable();
        $branchTable = $this->branchValidationTable();
        $categoryTable = $this->categoryValidationTable();
        $currencyTable = $this->currencyValidationTable();
        $priceListTable = $this->priceListValidationTable();
        $variationTable = $this->variationValidationTable();
        $uomGroupTable = $this->uomGroupValidationTable();

        $validator = Validator::make($request->all(), [
            'category_id' => ['nullable', 'integer', Rule::exists($categoryTable, 'id')],
            'item_type' => ['required', Rule::in(['uom', 'variation'])],
            'uom_group_id' => [
                'nullable',
                'required_if:item_type,uom',
                'integer',
                Rule::exists($uomGroupTable, 'id'),
            ],
            'branch_id' => ['nullable', 'integer', Rule::exists($branchTable, 'id')],
            'price_list_id' => ['nullable', 'integer', Rule::exists($priceListTable, 'id')],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists($currencyTable, 'id')->where(fn ($query) => $query->where('status', 'Active')),
            ],
            'sku' => ['required', 'string', 'max:64', Rule::unique($itemTable, 'sku')->ignore($itemId)],
            'name' => ['required', 'string', 'max:255'],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_control' => ['nullable', 'boolean'],
            'purchase' => ['nullable', 'boolean'],
            'sale' => ['nullable', 'boolean'],
            'is_purchase' => ['nullable', 'boolean'],
            'is_sale' => ['nullable', 'boolean'],
            'uom_prices' => ['nullable', 'array', 'max:100'],
            'uom_prices.*.unit_of_measure_id' => ['required', 'integer', 'distinct', Rule::exists($this->unitOfMeasureValidationTable(), 'id')],
            'uom_prices.*.reduce_by_percent' => ['nullable', 'numeric', 'between:0,100'],
            'uom_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'uom_prices.*.is_auto' => ['nullable', 'boolean'],
            'uom_prices.*.is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_premium' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'is_try_on_enabled' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'configuration_enabled' => ['required', 'boolean'],
            'option_groups' => ['required_if:configuration_enabled,1', 'array', 'max:20'],
            'option_groups.*.key' => ['required', 'string', 'alpha_dash', 'max:64', 'distinct'],
            'option_groups.*.item_variation_id' => ['nullable', 'integer', Rule::exists($variationTable, 'id')],
            'option_groups.*.name' => ['required', 'string', 'max:255'],
            'option_groups.*.foreign_name' => ['nullable', 'string', 'max:255'],
            'option_groups.*.type' => ['required', Rule::in(['variant', 'modifier'])],
            'option_groups.*.selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'option_groups.*.is_required' => ['nullable', 'boolean'],
            'option_groups.*.min_selections' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.max_selections' => ['nullable', 'integer', 'min:1'],
            'option_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'option_groups.*.values' => ['required', 'array', 'min:1', 'max:100'],
            'option_groups.*.values.*.key' => ['required', 'string', 'alpha_dash', 'max:64'],
            'option_groups.*.values.*.name' => ['required', 'string', 'max:255'],
            'option_groups.*.values.*.foreign_name' => ['nullable', 'string', 'max:255'],
            'option_groups.*.values.*.sku_suffix' => ['nullable', 'string', 'max:64'],
            'option_groups.*.values.*.color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
            'option_groups.*.values.*.price_adjustment' => ['nullable', 'numeric'],
            'option_groups.*.values.*.is_default' => ['nullable', 'boolean'],
            'option_groups.*.values.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.values.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'variants' => ['nullable', 'array', 'max:500'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:128', 'distinct'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'variants.*.option_value_keys' => ['required', 'array', 'min:1'],
            'variants.*.option_value_keys.*' => ['required', 'string', 'alpha_dash'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $currency = $this->currencies->findActiveById((int) $request->input('currency_id'));

            if (! $currency) {
                return;
            }

            if (currency_decimal_count($request->input('price')) > (int) $currency->decimal_places) {
                $validator->errors()->add(
                    'price',
                    $currency->code.' allows up to '.$currency->decimal_places.' decimal place(s).'
                );
            }

            collect($request->input('uom_prices', []))->each(function (array $row, int $index) use ($validator, $currency) {
                if (! filter_var($row['is_auto'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    && (! array_key_exists('price', $row) || $row['price'] === '')) {
                    $validator->errors()->add("uom_prices.{$index}.price", 'A manual UoM price is required when Auto is disabled.');
                }

                if (($row['price'] ?? '') !== ''
                    && currency_decimal_count($row['price']) > (int) $currency->decimal_places) {
                    $validator->errors()->add(
                        "uom_prices.{$index}.price",
                        $currency->code.' allows up to '.$currency->decimal_places.' decimal place(s).'
                    );
                }
            });
        });

        $validator->after(function ($validator) use ($request) {
            if ($request->input('item_type') !== 'uom' || ! $request->filled('uom_group_id')) {
                return;
            }

            $group = UomGroup::query()
                ->with('units')
                ->find((int) $request->input('uom_group_id'));

            $allowedUnitIds = $group?->units
                ->pluck('unit_of_measure_id')
                ->map(fn ($id) => (int) $id)
                ->all() ?? [];

            $baseUnitId = (int) ($group?->units->firstWhere('is_base_unit', true)?->unit_of_measure_id ?? 0);

            collect($request->input('uom_prices', []))->each(function (array $row, int $index) use ($validator, $allowedUnitIds, $baseUnitId) {
                $unitId = (int) ($row['unit_of_measure_id'] ?? 0);
                if (! in_array($unitId, $allowedUnitIds, true)) {
                    $validator->errors()->add("uom_prices.{$index}.unit_of_measure_id", 'This UoM does not belong to the selected UoM group.');
                }
                if ($unitId === $baseUnitId && array_key_exists('is_active', $row) && ! filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN)) {
                    $validator->errors()->add("uom_prices.{$index}.is_active", 'The base unit cannot be deactivated.');
                }
            });
        });

        $validator->after(function ($validator) use ($request, $item) {
            if (! $request->boolean('configuration_enabled')) {
                return;
            }

            $groups = collect($request->input('option_groups', []));
            $variants = collect($request->input('variants', []));
            $valueGroups = collect();
            $variantGroupKeys = collect();
            $allValueKeys = $groups->flatMap(fn (array $group) => collect($group['values'] ?? [])->pluck('key'));

            if ($allValueKeys->filter()->duplicates()->isNotEmpty()) {
                $validator->errors()->add('option_groups', 'Every option value key must be unique within the item.');
            }

            $groups->values()->each(function (array $group, int $groupIndex) use ($validator, $valueGroups, $variantGroupKeys) {
                $groupKey = (string) ($group['key'] ?? '');
                $values = collect($group['values'] ?? []);
                $min = (int) ($group['min_selections'] ?? (($group['is_required'] ?? false) ? 1 : 0));
                $max = isset($group['max_selections']) ? (int) $group['max_selections'] : null;

                if ($max !== null && $min > $max) {
                    $validator->errors()->add("option_groups.{$groupIndex}.min_selections", 'Minimum selections cannot exceed maximum selections.');
                }

                if ($max !== null && $max > $values->count()) {
                    $validator->errors()->add("option_groups.{$groupIndex}.max_selections", 'Maximum selections cannot exceed the number of values.');
                }

                if (($group['selection_type'] ?? null) === 'single' && $max !== null && $max > 1) {
                    $validator->errors()->add("option_groups.{$groupIndex}.max_selections", 'A single-select group can allow at most one selection.');
                }

                if (($group['type'] ?? null) === 'variant') {
                    $variantGroupKeys->push($groupKey);

                    if (($group['selection_type'] ?? null) !== 'single') {
                        $validator->errors()->add("option_groups.{$groupIndex}.selection_type", 'Variant groups must use single selection.');
                    }
                }

                if (($group['type'] ?? null) === 'modifier'
                    && ($group['selection_type'] ?? null) === 'single'
                    && $values->where('is_default', true)->count() > 1) {
                    $validator->errors()->add("option_groups.{$groupIndex}.values", 'A single-select group can have only one default value.');
                }

                $values->each(function (array $value) use ($valueGroups, $groupKey) {
                    if (! empty($value['key'])) {
                        $valueGroups->put($value['key'], $groupKey);
                    }
                });
            });

            if ($variantGroupKeys->isNotEmpty() && $variants->isEmpty()) {
                $validator->errors()->add('variants', 'Generate at least one sellable variant for the variation groups.');
            }

            $combinationKeys = collect();
            $variants->values()->each(function (array $variant, int $variantIndex) use ($validator, $valueGroups, $variantGroupKeys, $combinationKeys) {
                $selectedKeys = collect($variant['option_value_keys'] ?? []);

                if ($selectedKeys->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("variants.{$variantIndex}.option_value_keys", 'A variant cannot contain the same option value more than once.');
                }

                $selectedKeys->each(function (string $key) use ($validator, $valueGroups, $variantIndex) {
                    if (! $valueGroups->has($key)) {
                        $validator->errors()->add("variants.{$variantIndex}.option_value_keys", "Unknown option value key: {$key}.");
                    }
                });

                $selectedGroups = $selectedKeys->map(fn (string $key) => $valueGroups->get($key))->filter();
                $variantGroupKeys->each(function (string $groupKey) use ($validator, $selectedGroups, $variantIndex) {
                    if ($selectedGroups->filter(fn ($selectedGroup) => $selectedGroup === $groupKey)->count() !== 1) {
                        $validator->errors()->add(
                            "variants.{$variantIndex}.option_value_keys",
                            "Select exactly one value from variation group {$groupKey}."
                        );
                    }
                });

                if ($selectedGroups->diff($variantGroupKeys)->isNotEmpty()) {
                    $validator->errors()->add("variants.{$variantIndex}.option_value_keys", 'Variants may only contain values from variation groups.');
                }

                $combinationKey = $selectedKeys->sort()->implode('|');
                if ($combinationKey !== '' && $combinationKeys->contains($combinationKey)) {
                    $validator->errors()->add("variants.{$variantIndex}.option_value_keys", 'This option combination is duplicated.');
                }
                $combinationKeys->push($combinationKey);
            });

            if ($variants->where('is_default', true)->count() > 1) {
                $validator->errors()->add('variants', 'Only one variant may be the default.');
            }

            $skus = $variants->pluck('sku')->filter()->values();
            if ($skus->isNotEmpty()) {
                $duplicateSkuExists = ItemVariant::query()
                    ->whereIn('sku', $skus)
                    ->when($item, fn ($query) => $query->where('item_id', '!=', $item->id))
                    ->exists();

                if ($duplicateSkuExists) {
                    $validator->errors()->add('variants', 'One or more variant SKUs are already in use.');
                }
            }
        });

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            Log::warning('Admin item validation failed', [
                'tenant_id' => tenant()?->getTenantKey(),
                'item_id' => $itemId,
                'error_fields' => array_keys($errors),
                'errors' => $errors,
            ]);
        }

        return $validator->validate();
    }

    private function itemValidationTable(): string
    {
        $table = (new Item())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function priceListValidationTable(): string
    {
        $table = (new PriceList())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function currencyValidationTable(): string
    {
        $table = app(\App\Models\Currency::class)->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function variationValidationTable(): string
    {
        $table = (new ItemVariation())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function uomGroupValidationTable(): string
    {
        $table = (new UomGroup())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function unitOfMeasureValidationTable(): string
    {
        $table = (new UnitOfMeasure())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }

    private function normalizeFlags(Request $request): void
    {
        $itemType = (string) $request->input('item_type', 'uom');
        $configurationEnabled = $itemType === 'variation';
        $currencyId = $request->input('currency_id') ?: $this->baseCurrency()?->id;

        $hasScopeSubmitted = $request->has('scope_submitted');
        $purchase = $hasScopeSubmitted
            ? $request->boolean('purchase')
            : ($request->has('purchase') ? $request->boolean('purchase') : ($request->has('is_purchase') ? $request->boolean('is_purchase') : true));
        $sale = $hasScopeSubmitted
            ? $request->boolean('sale')
            : ($request->has('sale') ? $request->boolean('sale') : ($request->has('is_sale') ? $request->boolean('is_sale') : true));

        $request->merge([
            'item_type' => $itemType,
            'uom_group_id' => $configurationEnabled ? null : $request->input('uom_group_id'),
            'uom_prices' => $configurationEnabled ? [] : $request->input('uom_prices', []),
            'currency_id' => $currencyId,
            'purchase' => $purchase,
            'sale' => $sale,
            'is_premium' => $request->boolean('is_premium'),
            'is_featured' => $request->boolean('is_featured'),
            'is_new_arrival' => $request->boolean('is_new_arrival'),
            'is_try_on_enabled' => $request->boolean('is_try_on_enabled'),
            'stock_control' => $request->boolean('stock_control'),
            'review_count' => $request->input('review_count', 0),
            'sort_order' => $request->input('sort_order', 0),
            'configuration_enabled' => $configurationEnabled,
        ]);

        if (! $configurationEnabled) {
            $request->merge([
                'option_groups' => [],
                'variants' => [],
            ]);
        }
    }

    private function prepareConfigurationPayload(Request $request, array $validated): array
    {
        $currency = $this->currencies->findActiveById((int) $request->input('currency_id'));
        $validated['price'] = format_currency_input($validated['price'] ?? null, $currency, 2);
        $validated['uom_prices'] = collect($validated['uom_prices'] ?? [])->values()->map(function (array $row) use ($currency): array {
            $isAuto = array_key_exists('is_auto', $row) ? (bool) $row['is_auto'] : true;
            $isActive = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true;

            return [
                'unit_of_measure_id' => (int) $row['unit_of_measure_id'],
                'reduce_by_percent' => round((float) ($row['reduce_by_percent'] ?? 0), 2),
                'price' => $isAuto ? null : format_currency_input($row['price'] ?? null, $currency, 2),
                'is_auto' => $isAuto,
                'is_active' => $isActive,
            ];
        })->all();

        if (! $request->boolean('configuration_enabled')) {
            $validated['option_groups'] = [];
            $validated['variants'] = [];
            unset($validated['configuration_enabled']);

            return $validated;
        }

        $validated['option_groups'] = collect($validated['option_groups'] ?? [])->values()->map(function (array $group, int $index) use ($currency): array {
            $isVariant = $group['type'] === 'variant';
            $isRequired = $isVariant || (bool) ($group['is_required'] ?? false);

            return array_merge($group, [
                'selection_type' => $isVariant ? 'single' : $group['selection_type'],
                'is_required' => $isRequired,
                'min_selections' => $isVariant ? 1 : (int) ($group['min_selections'] ?? ($isRequired ? 1 : 0)),
                'max_selections' => $isVariant ? 1 : ($group['max_selections'] ?? ($group['selection_type'] === 'single' ? 1 : null)),
                'sort_order' => (int) ($group['sort_order'] ?? $index),
                'status' => $group['status'] ?? 'Active',
                'values' => collect($group['values'])->values()->map(fn (array $value, int $valueIndex): array => array_merge($value, [
                    'price_adjustment' => format_currency_input($value['price_adjustment'] ?? 0, $currency, 2),
                    'is_default' => ! $isVariant && (bool) ($value['is_default'] ?? false),
                    'sort_order' => (int) ($value['sort_order'] ?? $valueIndex),
                    'status' => $value['status'] ?? 'Active',
                ]))->all(),
            ]);
        })->all();

        $validated['variants'] = collect($validated['variants'] ?? [])->values()->map(fn (array $variant, int $index): array => array_merge($variant, [
            'price' => format_currency_input($variant['price'] ?? null, $currency, 2),
            'stock' => (int) ($variant['stock'] ?? 0),
            'is_default' => (bool) ($variant['is_default'] ?? false),
            'sort_order' => (int) ($variant['sort_order'] ?? $index),
            'status' => $variant['status'] ?? 'Active',
        ]))->all();
        unset($validated['configuration_enabled']);

        return $validated;
    }

    private function baseCurrency(): ?\App\Models\Currency
    {
        return $this->currencies->findActiveByCode(
            data_get(admin_current_tenant()?->general_settings, 'currency')
        );
    }

    private function variationMasters()
    {
        return ItemVariation::query()
            ->where('status', 'Active')
            ->whereHas('options', fn ($query) => $query->where('status', 'Active'))
            ->with(['options' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get();
    }
}
