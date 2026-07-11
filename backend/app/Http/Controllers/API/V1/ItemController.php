<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\PriceList;
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
            'data' => new ItemResource($item->load(['category', 'galleries', 'image'])),
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
            'data' => new ItemResource($itemModel->load(['category', 'currency', 'galleries', 'image'])),
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

        $validator = Validator::make($request->all(), [
            'category_id' => ['nullable', 'integer', Rule::exists($categoryTable, 'id')],
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
                    $currency->code . ' allows up to ' . $currency->decimal_places . ' decimal place(s).'
                );
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

        return $validated;
    }

    protected function itemValidationTable(): string
    {
        $table = (new Item())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    protected function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    protected function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    protected function currencyValidationTable(): string
    {
        $table = (new Currency())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    protected function priceListValidationTable(): string
    {
        $table = (new PriceList())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }
}
