<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Repositories\PriceListRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceListController extends Controller
{
    protected PriceListRepository $priceLists;
    protected CurrencyRepository $currencies;

    public function __construct(PriceListRepository $priceLists, CurrencyRepository $currencies)
    {
        $this->priceLists = $priceLists;
        $this->currencies = $currencies;
        $this->middleware('admin.permission:price_lists.view')->only(['index']);
        $this->middleware('admin.permission:price_lists.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.price-lists.index', [
            'priceLists' => $this->priceLists->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.price-lists.create', [
            'priceList' => new PriceList([
                'status' => 'Active',
            ]),
            'baseCurrency' => $this->baseCurrency(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validatePriceList($request);
        $this->priceLists->createForAdmin($validated);

        return redirect()
            ->route('admin.price-lists.index')
            ->with('status', 'Price list created successfully.');
    }

    public function edit(Request $request, string $priceList): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);

        return view('admin.price-lists.edit', [
            'priceList' => $priceListModel,
            'itemOptions' => $this->priceLists->getItemOptions(),
            'baseCurrency' => $this->baseCurrency(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $priceList): RedirectResponse
    {
        $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);
        $validated = $this->validatePriceList($request, $priceListModel);
        $this->priceLists->updateForAdmin($priceListModel, $validated);

        return redirect()
            ->route('admin.price-lists.edit', ['price_list' => $priceListModel->id])
            ->with('status', 'Price list updated successfully.');
    }

    public function destroy(Request $request, string $priceList): RedirectResponse
    {
        $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);
        $this->priceLists->deleteForAdmin($priceListModel);

        return redirect()
            ->route('admin.price-lists.index')
            ->with('status', 'Price list deleted successfully.');
    }

    public function storeLine(Request $request, string $priceList): RedirectResponse
    {
        $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);
        $validated = $this->validateStoreLine($request, $priceListModel);
        $this->priceLists->createLineForAdmin($priceListModel, $validated);

        return redirect()
            ->route('admin.price-lists.edit', ['price_list' => $priceListModel->id])
            ->with('status', 'Price list line added successfully.');
    }

    public function updateLine(Request $request, string $priceList, string $line): RedirectResponse
    {
        $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);
        $lineModel = $priceListModel->lines()->whereKey((int) $line)->firstOrFail();
        $validated = $this->validateUpdateLine($request, $lineModel);
        $this->priceLists->updateLineForAdmin($lineModel, $validated);

        return redirect()
            ->route('admin.price-lists.edit', ['price_list' => $priceListModel->id])
            ->with('status', 'Price list line updated successfully.');
    }

    public function destroyLine(Request $request, string $priceList, string $line): RedirectResponse
    {
        $this->requiredTenant($request);
        $priceListModel = $this->priceLists->loadForAdminEdit((int) $priceList);
        $lineModel = $priceListModel->lines()->whereKey((int) $line)->firstOrFail();
        $this->priceLists->deleteLineForAdmin($lineModel);

        return redirect()
            ->route('admin.price-lists.edit', ['price_list' => $priceListModel->id])
            ->with('status', 'Price list line removed successfully.');
    }

    private function validatePriceList(Request $request, ?PriceList $priceList = null): array
    {
        $priceListId = $priceList?->id;
        $table = $this->priceListValidationTable();

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($priceListId)],
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($priceListId)],
            'description' => ['nullable', 'string'],
            'header_pricing_method' => ['nullable', Rule::in(['fixed', 'discount'])],
            'header_fixed_price' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('header_pricing_method') === 'fixed')],
            'header_discount_percent' => ['nullable', 'integer', 'between:0,100', Rule::requiredIf($request->input('header_pricing_method') === 'discount')],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->input('header_pricing_method') !== 'fixed') {
                return;
            }

            $baseCurrency = $this->baseCurrency();

            if (! $baseCurrency) {
                $validator->errors()->add(
                    'header_fixed_price',
                    'Set a base currency in General settings before using header fixed price.'
                );

                return;
            }

            if (currency_decimal_count($request->input('header_fixed_price')) > (int) $baseCurrency->decimal_places) {
                $validator->errors()->add(
                    'header_fixed_price',
                    $baseCurrency->code . ' allows up to ' . $baseCurrency->decimal_places . ' decimal place(s).'
                );
            }
        });

        return $validator->validate();
    }

    private function validateStoreLine(Request $request, PriceList $priceList): array
    {
        $table = $this->priceListItemValidationTable();
        $itemTable = $this->itemValidationTable();

        $validator = Validator::make($request->all(), [
            'item_id' => [
                'required',
                'integer',
                Rule::exists($itemTable, 'id'),
                Rule::unique($table, 'item_id')->where(fn ($query) => $query->where('price_list_id', $priceList->id)),
            ],
            'pricing_method' => ['required', Rule::in(['fixed', 'discount'])],
            'fixed_price' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('pricing_method') === 'fixed')],
            'discount_percent' => ['nullable', 'integer', 'between:0,100', Rule::requiredIf($request->input('pricing_method') === 'discount')],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validator->after(function ($validator) use ($request) {
            $item = $this->findItemWithCurrency((int) $request->input('item_id'));
            $currency = $item?->currency;

            if (! $currency) {
                $validator->errors()->add('fixed_price', 'Assign an active currency to the selected item first.');

                return;
            }

            if ($request->input('pricing_method') === 'fixed'
                && currency_decimal_count($request->input('fixed_price')) > (int) $currency->decimal_places) {
                $validator->errors()->add(
                    'fixed_price',
                    $currency->code . ' allows up to ' . $currency->decimal_places . ' decimal place(s).'
                );
            }
        });

        return $validator->validate();
    }

    private function validateUpdateLine(Request $request, PriceListItem $line): array
    {
        $validator = Validator::make($request->all(), [
            'pricing_method' => ['required', Rule::in(['fixed', 'discount'])],
            'fixed_price' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('pricing_method') === 'fixed')],
            'discount_percent' => ['nullable', 'integer', 'between:0,100', Rule::requiredIf($request->input('pricing_method') === 'discount')],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validator->after(function ($validator) use ($request, $line) {
            $line->loadMissing('item.currency');
            $currency = $line->item?->currency;

            if (! $currency) {
                $validator->errors()->add('fixed_price', 'Assign an active currency to the linked item first.');

                return;
            }

            if ($request->input('pricing_method') === 'fixed'
                && currency_decimal_count($request->input('fixed_price')) > (int) $currency->decimal_places) {
                $validator->errors()->add(
                    'fixed_price',
                    $currency->code . ' allows up to ' . $currency->decimal_places . ' decimal place(s).'
                );
            }
        });

        return $validator->validate();
    }

    private function priceListValidationTable(): string
    {
        $table = (new PriceList())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function priceListItemValidationTable(): string
    {
        $table = (new PriceListItem())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function itemValidationTable(): string
    {
        $table = app(Item::class)->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function baseCurrency(): ?\App\Models\Currency
    {
        return $this->currencies->findActiveByCode(
            data_get(admin_current_tenant()?->general_settings, 'currency')
        );
    }

    private function findItemWithCurrency(int $itemId): ?Item
    {
        if ($itemId <= 0) {
            return null;
        }

        $item = new Item();

        if (tenant()) {
            $item->setConnection(tenant()->database_connection_name);
        }

        return $item->newQuery()
            ->with('currency')
            ->find($itemId);
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
