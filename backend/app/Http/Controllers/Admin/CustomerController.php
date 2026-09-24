<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Tenant;
use App\Repositories\CustomerRepository;
use App\Repositories\PriceListRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    protected CustomerRepository $customers;

    protected PriceListRepository $priceLists;

    public function __construct(CustomerRepository $customers, PriceListRepository $priceLists)
    {
        $this->customers = $customers;
        $this->priceLists = $priceLists;
        $this->middleware('admin.permission:customers.view')->only(['index']);
        $this->middleware('admin.permission:customers.create')->only(['create', 'store']);
        $this->middleware('admin.permission:customers.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:customers.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $type = trim((string) $request->get('type', ''));
        if (! in_array($type, Customer::TYPES, true)) {
            $type = '';
        }
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.customers.index', [
            'customers' => $this->customers->getAdminListing($search, $type ?: null),
            'typeCounts' => $this->customers->getTypeCounts($search),
            'search' => $search,
            'selectedType' => $type,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $initialType = $request->get('type', Customer::TYPE_CUSTOMER);
        if (! in_array($initialType, Customer::TYPES, true)) {
            $initialType = Customer::TYPE_CUSTOMER;
        }

        return view('admin.customers.create', [
            'customer' => new Customer([
                'type' => $initialType,
                'status' => 'Active',
            ]),
            'priceLists' => $this->priceLists->getOptions(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateCustomer($request);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);
        $customer = $this->customers->createForAdmin($validated);

        $label = $customer->type === Customer::TYPE_VENDOR ? 'Vendor' : 'Customer';

        return redirect()
            ->route('admin.customers.index', $customer->type === Customer::TYPE_VENDOR ? ['type' => 'vendor'] : [])
            ->with('status', "{$label} created successfully.");
    }

    public function edit(Request $request, string $customer): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $customerModel = $this->customers->loadForAdminEdit((int) $customer);

        return view('admin.customers.edit', [
            'customer' => $customerModel,
            'priceLists' => $this->priceLists->getOptions($customerModel->price_list_id),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $this->requiredTenant($request);
        $customerModel = $this->customers->loadForAdminEdit((int) $customer);
        $validated = $this->validateCustomer($request, $customerModel);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);
        $customerModel = $this->customers->updateForAdmin($customerModel, $validated);

        $label = $customerModel->type === Customer::TYPE_VENDOR ? 'Vendor' : 'Customer';

        return redirect()
            ->route('admin.customers.index', $customerModel->type === Customer::TYPE_VENDOR ? ['type' => 'vendor'] : [])
            ->with('status', "{$label} updated successfully.");
    }

    public function destroy(Request $request, string $customer): RedirectResponse
    {
        $this->requiredTenant($request);
        $customerModel = $this->customers->loadForAdminEdit((int) $customer);
        $type = $customerModel->type;
        $label = $type === Customer::TYPE_VENDOR ? 'Vendor' : 'Customer';
        $this->customers->deleteForAdmin($customerModel);

        return redirect()
            ->route('admin.customers.index', $type === Customer::TYPE_VENDOR ? ['type' => 'vendor'] : [])
            ->with('status', "{$label} deleted successfully.");
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $customerId = $customer?->id;
        $customerTable = $this->customerValidationTable();

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(Customer::TYPES)],
            'code' => ['required', 'string', 'max:64', Rule::unique($customerTable, 'code')->ignore($customerId)],
            'price_list_id' => ['nullable', 'integer', Rule::exists($this->priceListValidationTable(), 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($customerTable, 'email')->ignore($customerId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique($customerTable, 'phone')->ignore($customerId)],
            'profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['type'] = $validated['type'] ?? Customer::TYPE_CUSTOMER;

        return $validated;
    }

    private function customerValidationTable(): string
    {
        $table = (new Customer())->getTable();

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

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        return ltrim((string) $normalized, '0');
    }
}
