<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Repositories\CustomerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    protected CustomerRepository $customers;

    public function __construct(CustomerRepository $customers)
    {
        $this->customers = $customers;
        $this->middleware('admin.permission:customers.view')->only(['index']);
        $this->middleware('admin.permission:customers.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.customers.index', [
            'customers' => $this->customers->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.customers.create', [
            'customer' => new Customer([
                'status' => 'Active',
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateCustomer($request);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);
        $this->customers->createForAdmin($validated);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Customer created successfully.');
    }

    public function edit(Request $request, string $customer): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.customers.edit', [
            'customer' => $this->customers->loadForAdminEdit((int) $customer),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $this->requiredTenant($request);
        $customerModel = $this->customers->loadForAdminEdit((int) $customer);
        $validated = $this->validateCustomer($request, $customerModel);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);
        $this->customers->updateForAdmin($customerModel, $validated);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Customer updated successfully.');
    }

    public function destroy(Request $request, string $customer): RedirectResponse
    {
        $this->requiredTenant($request);
        $customerModel = $this->customers->loadForAdminEdit((int) $customer);
        $this->customers->deleteForAdmin($customerModel);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Customer deleted successfully.');
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $customerId = $customer?->id;
        $customerTable = $this->customerValidationTable();

        return $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique($customerTable, 'code')->ignore($customerId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($customerTable, 'email')->ignore($customerId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique($customerTable, 'phone')->ignore($customerId)],
            'profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function customerValidationTable(): string
    {
        $table = (new Customer())->getTable();

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

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        return ltrim((string) $normalized, '0');
    }
}
