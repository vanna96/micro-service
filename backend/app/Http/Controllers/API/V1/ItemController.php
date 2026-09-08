<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PriceList;
use App\Models\UomGroup;
use App\Repositories\CurrencyRepository;
use App\Repositories\ItemRepository;
use App\Rules\Base64Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    protected ItemRepository $items;

    protected CurrencyRepository $currencies;

    public function __construct(ItemRepository $items, CurrencyRepository $currencies)
    {
        $this->items = $items;
        $this->currencies = $currencies;
    }

    public function list(Request $request)
    {
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $branchId = $request->get('branch_id');
        $branchName = $request->get('branch_name');
        $status = $request->get('status');
        $premiumOnly = filter_var($request->get('is_premium'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $featuredOnly = filter_var($request->get('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $newArrivalOnly = filter_var($request->get('is_new_arrival'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $items = $this->items
            ->list()
            ->when(! in_array($search, ['undefined', 'null', ''], true), function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('branch_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($branchName, fn ($query) => $query->where('branch_name', $branchName))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($premiumOnly !== null, fn ($query) => $query->where('is_premium', $premiumOnly))
            ->when($featuredOnly !== null, fn ($query) => $query->where('is_featured', $featuredOnly))
            ->when($newArrivalOnly !== null, fn ($query) => $query->where('is_new_arrival', $newArrivalOnly))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => ItemResource::collection($items->items()),
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);
        $validated = $this->prepareItemPayload($request, $validated);
        $item = $this->items->createForAdmin($validated);

        return response()->json([
            'success' => true,
            'message' => translate('Item created successfully.', request('lng')),
            'data' => new ItemResource($item->load(['category', 'uomGroup', 'galleries', 'image', 'optionGroups.values', 'variants.optionValues'])),
        ], 200);
    }

    public function edit(string $item)
    {
        return response()->json([
            'data' => new ItemResource($this->items->loadForAdminEdit((int) $item)),
        ], 200);
    }

    public function update(Request $request, string $item)
    {
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $validated = $this->validateItem($request, $itemModel);
        $validated = $this->prepareItemPayload($request, $validated);
        $itemModel = $this->items->updateForAdmin($itemModel, $validated);

        return response()->json([
            'success' => true,
            'message' => translate('Item updated successfully.', request('lng')),
            'data' => new ItemResource($itemModel->load(['category', 'currency', 'uomGroup', 'galleries', 'image', 'optionGroups.values', 'variants.optionValues'])),
        ], 200);
    }

    public function delete(string $item)
    {
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $this->items->deleteForAdmin($itemModel);

        return response()->json([
            'success' => true,
            'message' => translate('Item deleted successfully.', request('lng')),
        ], 200);
    }

    protected function validateItem(Request $request, ?Item $item = null): array
    {
        $itemId = $item?->id;
        $requiredRule = $item ? 'sometimes' : 'required';
        $itemTable = $this->itemValidationTable();
        $branchTable = $this->branchValidationTable();
        $categoryTable = $this->categoryValidationTable();
        $currencyTable = $this->currencyValidationTable();
        $priceListTable = $this->priceListValidationTable();
        $uomGroupTable = $this->uomGroupValidationTable();

        $validator = Validator::make($request->all(), [
            'category_id' => ['nullable', 'integer', Rule::exists($categoryTable, 'id')],
            'item_type' => [$item ? 'sometimes' : 'nullable', Rule::in(['uom', 'variation'])],
            'uom_group_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('item_type') === 'uom'),
                'integer',
                Rule::exists($uomGroupTable, 'id'),
            ],
            'branch_id' => ['nullable', 'integer', Rule::exists($branchTable, 'id')],
            'price_list_id' => ['nullable', 'integer', Rule::exists($priceListTable, 'id')],
            'currency_id' => [
                'nullable',
                'integer',
                Rule::exists($currencyTable, 'id')->where(fn ($query) => $query->where('status', 'Active')),
            ],
            'sku' => [$requiredRule, 'string', 'max:64', Rule::unique($itemTable, 'sku')->ignore($itemId)],
            'name' => [$requiredRule, 'string', 'max:255'],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [$requiredRule, 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'between:0,100'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'stock' => [$requiredRule, 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_premium' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'is_try_on_enabled' => ['nullable', 'boolean'],
            'status' => [$item ? 'sometimes' : 'nullable', Rule::in(['Active', 'Inactive'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'attachment' => ['nullable', new Base64Image()],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', new Base64Image()],
            'option_groups' => ['nullable', 'array', 'max:20'],
            'option_groups.*.key' => ['required', 'string', 'alpha_dash', 'max:64', 'distinct'],
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
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'variants.*.option_value_keys' => ['required', 'array', 'min:1'],
            'variants.*.option_value_keys.*' => ['required', 'string', 'alpha_dash', 'distinct'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $currencyId = $request->input('currency_id');

            if (! $currencyId || ! $request->filled('price')) {
                return;
            }

            $currency = $this->currencies->findActiveById((int) $currencyId);

            if (! $currency) {
                return;
            }

            if (currency_decimal_count($request->input('price')) > (int) $currency->decimal_places) {
                $validator->errors()->add(
                    'price',
                    $currency->code.' allows up to '.$currency->decimal_places.' decimal place(s).'
                );
            }
        });

        $validator->after(function ($validator) use ($request, $item) {
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

                if (($group['selection_type'] ?? null) === 'single' && $values->where('is_default', true)->count() > 1) {
                    $validator->errors()->add("option_groups.{$groupIndex}.values", 'A single-select group can have only one default value.');
                }

                $values->each(function (array $value) use ($valueGroups, $groupKey) {
                    if (! empty($value['key'])) {
                        $valueGroups->put($value['key'], $groupKey);
                    }
                });
            });

            $variants->values()->each(function (array $variant, int $variantIndex) use ($validator, $valueGroups, $variantGroupKeys) {
                $selectedKeys = collect($variant['option_value_keys'] ?? []);

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
                            "Select exactly one value from variant group {$groupKey}."
                        );
                    }
                });

                if ($selectedGroups->diff($variantGroupKeys)->isNotEmpty()) {
                    $validator->errors()->add("variants.{$variantIndex}.option_value_keys", 'Variants may only contain values from variant groups.');
                }
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
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')), true)) {
                $message = translate($message, request('lng'));
            }

            abort(response()->json($message, 422));
        }

        return $validator->validated();
    }

    protected function prepareItemPayload(Request $request, array $validated): array
    {
        if ($request->has('item_type')) {
            $isVariation = $validated['item_type'] === 'variation';
            $validated['uom_group_id'] = $isVariation ? null : ($validated['uom_group_id'] ?? null);
        }

        if (array_key_exists('branch_id', $validated)) {
            if (! empty($validated['branch_id'])) {
                $branch = Branch::query()->find($validated['branch_id']);
                $validated['branch_name'] = $branch?->name;
            } else {
                $validated['branch_name'] = null;
            }
        }

        if ($request->hasFile('image') || $request->filled('attachment')) {
            $validated['image'] = $request->file('image') ?: $request->input('attachment');
        }

        $galleryImages = $request->file('gallery_images', []);
        $galleryAttachments = $request->input('attachments', []);

        if ($galleryImages || $galleryAttachments) {
            $validated['gallery_images'] = array_values(array_merge($galleryImages, is_array($galleryAttachments) ? $galleryAttachments : []));
        }

        if ($request->has('option_groups') || $request->has('variants')) {
            $validated['option_groups'] = collect($validated['option_groups'] ?? [])->values()->map(function (array $group, int $index): array {
                $isVariant = $group['type'] === 'variant';
                $isRequired = $isVariant || (bool) ($group['is_required'] ?? false);

                return array_merge($group, [
                    'key' => $group['key'],
                    'selection_type' => $isVariant ? 'single' : $group['selection_type'],
                    'is_required' => $isRequired,
                    'min_selections' => $isVariant ? 1 : (int) ($group['min_selections'] ?? ($isRequired ? 1 : 0)),
                    'max_selections' => $isVariant ? 1 : ($group['max_selections'] ?? ($group['selection_type'] === 'single' ? 1 : null)),
                    'sort_order' => (int) ($group['sort_order'] ?? $index),
                    'status' => $group['status'] ?? 'Active',
                    'values' => collect($group['values'])->values()->map(fn (array $value, int $valueIndex): array => array_merge($value, [
                        'price_adjustment' => (float) ($value['price_adjustment'] ?? 0),
                        'is_default' => (bool) ($value['is_default'] ?? false),
                        'sort_order' => (int) ($value['sort_order'] ?? $valueIndex),
                        'status' => $value['status'] ?? 'Active',
                    ]))->all(),
                ]);
            })->all();

            $validated['variants'] = collect($validated['variants'] ?? [])->values()->map(fn (array $variant, int $index): array => array_merge($variant, [
                'stock' => (int) ($variant['stock'] ?? 0),
                'is_default' => (bool) ($variant['is_default'] ?? false),
                'sort_order' => (int) ($variant['sort_order'] ?? $index),
                'status' => $variant['status'] ?? 'Active',
            ]))->all();
        }

        if ($request->input('item_type') === 'uom') {
            $validated['option_groups'] = [];
            $validated['variants'] = [];
        }

        return $validated;
    }

    protected function itemValidationTable(): string
    {
        $table = (new Item())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    protected function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    protected function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    protected function currencyValidationTable(): string
    {
        $table = (new Currency())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    protected function priceListValidationTable(): string
    {
        $table = (new PriceList())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }

    protected function uomGroupValidationTable(): string
    {
        $table = (new UomGroup())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name.'.'.$table;
        }

        return 'central.'.$table;
    }
}
