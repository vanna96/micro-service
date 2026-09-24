<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    protected CurrencyRepository $currencies;

    public function __construct(CurrencyRepository $currencies)
    {
        $this->currencies = $currencies;
        $this->middleware('admin.permission:currencies.view')->only(['index']);
        $this->middleware('admin.permission:currencies.create')->only(['create', 'store']);
        $this->middleware('admin.permission:currencies.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:currencies.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.currencies.index', [
            'currencies' => $this->currencies->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.currencies.create', [
            'currency' => new Currency([
                'decimal_places' => 2,
                'status' => 'Active',
                'sort_order' => 0,
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateCurrency($request);
        $this->currencies->createForAdmin($validated);

        return redirect()
            ->route('admin.currencies.index')
            ->with('status', 'Currency created successfully.');
    }

    public function edit(Request $request, string $currency): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.currencies.edit', [
            'currency' => $this->currencies->loadForAdminEdit((int) $currency),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $currency): RedirectResponse
    {
        $this->requiredTenant($request);
        $currencyModel = $this->currencies->loadForAdminEdit((int) $currency);
        $validated = $this->validateCurrency($request, $currencyModel);
        $this->currencies->updateForAdmin($currencyModel, $validated);

        return redirect()
            ->route('admin.currencies.index')
            ->with('status', 'Currency updated successfully.');
    }

    public function destroy(Request $request, string $currency): RedirectResponse
    {
        $this->requiredTenant($request);
        $currencyModel = $this->currencies->loadForAdminEdit((int) $currency);
        $this->currencies->deleteForAdmin($currencyModel);

        return redirect()
            ->route('admin.currencies.index')
            ->with('status', 'Currency deleted successfully.');
    }

    private function validateCurrency(Request $request, ?Currency $currency = null): array
    {
        $currencyId = $currency?->id;
        $table = $this->currencyValidationTable();

        return $request->validate([
            'code' => ['required', 'string', 'size:3', Rule::unique($table, 'code')->ignore($currencyId)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['required', 'integer', 'between:0,8'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function currencyValidationTable(): string
    {
        $table = (new Currency())->getTable();

        if (tenant()) {
            return (tenant()->database_connection_name ?: 'tenant') . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
