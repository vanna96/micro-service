<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\TenantUserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantUserController extends Controller
{
    protected TenantUserRepository $users;
    protected RoleRepository $roles;

    public function __construct(TenantUserRepository $users, RoleRepository $roles)
    {
        $this->users = $users;
        $this->roles = $roles;
        $this->middleware('admin.permission:tenant_users.view')->only(['index']);
        $this->middleware('admin.permission:tenant_users.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.tenant-users.index', [
            'users' => $this->users->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.tenant-users.create', [
            'user' => new User([
                'status' => 'Active',
            ]),
            'selectedTenant' => $selectedTenant,
            'showTenantAssignments' => false,
            'tenants' => collect(),
            'selectedTenants' => [],
            'roleOptions' => $this->roles->getRoleOptions(),
            'selectedRoles' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateUser($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);

        $this->users->createForAdmin($validated);

        return redirect()
            ->route('admin.tenant-users.index')
            ->with('status', 'Tenant user created successfully.');
    }

    public function edit(Request $request, string $tenantUser): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.tenant-users.edit', [
            'user' => $this->users->loadForAdminEdit((int) $tenantUser),
            'selectedTenant' => $selectedTenant,
            'showTenantAssignments' => false,
            'tenants' => collect(),
            'selectedTenants' => [],
            'roleOptions' => $this->roles->getRoleOptions(),
            'selectedRoles' => $this->users->loadForAdminEdit((int) $tenantUser)->roles->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, string $tenantUser): RedirectResponse
    {
        $this->requiredTenant($request);
        $user = $this->users->loadForAdminEdit((int) $tenantUser);
        $validated = $this->validateUser($request, $user);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->users->updateForAdmin($user, $validated);

        return redirect()
            ->route('admin.tenant-users.index')
            ->with('status', 'Tenant user updated successfully.');
    }

    public function destroy(Request $request, string $tenantUser): RedirectResponse
    {
        $this->requiredTenant($request);
        $user = $this->users->loadForAdminEdit((int) $tenantUser);
        $this->users->deleteForAdmin($user);

        return redirect()
            ->route('admin.tenant-users.index')
            ->with('status', 'Tenant user deleted successfully.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;
        $userTable = $this->tenantUserValidationTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique($userTable, 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($userTable, 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique($userTable, 'phone')->ignore($userId)],
            'profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', Rule::exists($this->roleValidationTable(), 'id')],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function tenantUserValidationTable(): string
    {
        $table = (new User())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function roleValidationTable(): string
    {
        $table = (new \App\Models\Role())->getTable();

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

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        return ltrim((string) $normalized, '0');
    }
}
