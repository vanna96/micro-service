<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\PriceList;
use App\Models\Tenant;
use App\Repositories\BranchRepository;
use App\Repositories\CurrencyRepository;
use App\Repositories\ItemRepository;
use App\Repositories\PriceListRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    protected ItemRepository $items;
    protected BranchRepository $branches;
    protected CurrencyRepository $currencies;
    protected PriceListRepository $priceLists;

    public function __construct(
        ItemRepository $items,
        BranchRepository $branches,
        CurrencyRepository $currencies,
        PriceListRepository $priceLists
    )
    {
        $this->items = $items;
        $this->branches = $branches;
        $this->currencies = $currencies;
        $this->priceLists = $priceLists;
        $this->middleware('admin.permission:items.view')->only(['index']);
        $this->middleware('admin.permission:items.manage')->except(['index']);
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
                'is_try_on_enabled' => true,
                'currency_id' => $baseCurrency?->id,
            ]),
            'branchOptions' => $this->branches->getOptions(),
            'priceListOptions' => $this->priceLists->getOptions(),
            'currencyOptions' => $this->currencies->getActiveOptions(),
            'categoryOptions' => Category::query()->orderBy('name')->get(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $this->normalizeFlags($request);
        $validated = $this->validateItem($request);
        $this->items->createForAdmin($validated);

        return redirect()
            ->route('admin.items.index')
            ->with('status', 'Item created successfully.');
    }

    public function edit(Request $request, string $item): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);

        return view('admin.items.edit', [
            'item' => $itemModel,
            'branchOptions' => $this->branches->getOptions($itemModel->branch_id),
            'priceListOptions' => $this->priceLists->getOptions($itemModel->price_list_id),
            'currencyOptions' => $this->currencies->getActiveOptions(),
            'categoryOptions' => Category::query()->orderBy('name')->get(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        $this->requiredTenant($request);
        $this->normalizeFlags($request);
        $itemModel = $this->items->loadForAdminEdit((int) $item);
        $validated = $this->validateItem($request, $itemModel);
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

        $validator = Validator::make($request->all(), [
            'category_id' => ['nullable', 'integer', Rule::exists($categoryTable, 'id')],
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
            'discount_percent' => ['nullable', 'integer', 'between:0,100'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_premium' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'is_try_on_enabled' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validator->after(function ($validator) use ($request) {
            $currency = $this->currencies->findActiveById((int) $request->input('currency_id'));

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

        return $validator->validate();
    }

    private function itemValidationTable(): string
    {
        $table = (new Item())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function priceListValidationTable(): string
    {
        $table = (new PriceList())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function currencyValidationTable(): string
    {
        $table = app(\App\Models\Currency::class)->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }

    private function normalizeFlags(Request $request): void
    {
        $request->merge([
            'is_premium' => $request->boolean('is_premium'),
            'is_featured' => $request->boolean('is_featured'),
            'is_new_arrival' => $request->boolean('is_new_arrival'),
            'is_try_on_enabled' => $request->boolean('is_try_on_enabled'),
            'discount_percent' => $request->input('discount_percent', 0),
            'review_count' => $request->input('review_count', 0),
            'sort_order' => $request->input('sort_order', 0),
        ]);
    }

    private function baseCurrency(): ?\App\Models\Currency
    {
        return $this->currencies->findActiveByCode(
            data_get(admin_current_tenant()?->general_settings, 'currency')
        );
    }
}
