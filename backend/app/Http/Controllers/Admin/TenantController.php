<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\TenantRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    protected TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        return view('admin.tenants.index', [
            'tenants' => $this->tenants->getAdminListing($search),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.tenants.create', [
            'tenant' => new Tenant(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTenant($request);

        $this->tenants->createForAdmin($validated);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant created successfully.');
    }

    public function edit(Tenant $tenant): View
    {
        $tenant = $this->tenants->loadForAdminEdit($tenant);

        return view('admin.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateTenant($request, false);
        $this->tenants->updateForAdmin($tenant, $validated);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant updated successfully.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->tenants->deleteForAdmin($tenant);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', 'Tenant deleted successfully.');
    }

    private function validateTenant(Request $request, bool $includeId = true): array
    {
        $tenantTable = 'central.' . (new Tenant())->getTable();

        $rules = [
            'alias' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'db_connection' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'string', 'max:20'],
            'db_name' => ['required', 'string', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];

        if ($includeId) {
            $rules['id'] = ['required', 'string', 'max:255', Rule::unique($tenantTable, 'id')];
        }

        $rules['alias'][] = Rule::unique($tenantTable, 'alias')->ignore($request->route('tenant'));

        return $request->validate($rules);
    }
}
