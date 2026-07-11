<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Repositories\RoleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    protected RoleRepository $roles;

    public function __construct(RoleRepository $roles)
    {
        $this->roles = $roles;
        $this->middleware('admin.permission:roles.view')->only(['index']);
        $this->middleware('admin.permission:roles.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        return view('admin.roles.index', [
            'roles' => $this->roles->getAdminListing(),
            'selectedTenant' => $this->requiredTenant($request),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.roles.create', [
            'role' => new Role(),
            'permissionGroups' => $this->roles->getPermissionGroups(),
            'selectedPermissions' => [],
            'selectedTenant' => $this->requiredTenant($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $role = $this->roles->createForAdmin($this->validateRole($request));

        return redirect()
            ->route('admin.roles.edit', ['role' => $role->id])
            ->with('status', 'Role created successfully.');
    }

    public function edit(Request $request, string $role): View
    {
        $roleModel = $this->roles->loadForAdminEdit((int) $role);

        return view('admin.roles.edit', [
            'role' => $roleModel,
            'permissionGroups' => $this->roles->getPermissionGroups(),
            'selectedPermissions' => $roleModel->permissions->pluck('id')->all(),
            'selectedTenant' => $this->requiredTenant($request),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->requiredTenant($request);
        $roleModel = $this->roles->loadForAdminEdit((int) $role);
        $this->roles->updateForAdmin($roleModel, $this->validateRole($request, $roleModel));

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role updated successfully.');
    }

    public function destroy(Request $request, string $role): RedirectResponse
    {
        $this->requiredTenant($request);
        $roleModel = $this->roles->loadForAdminEdit((int) $role);
        $this->roles->deleteForAdmin($roleModel);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role deleted successfully.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $roleId = $role?->id;
        $roleTable = $this->roleValidationTable();
        $permissionTable = $this->permissionValidationTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($roleTable, 'name')->ignore($roleId)],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', Rule::exists($permissionTable, 'id')],
        ]);
    }

    private function roleValidationTable(): string
    {
        $table = (new Role())->getTable();

        if (tenant()) {
            return (tenant()->database_connection_name ?: 'tenant') . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function permissionValidationTable(): string
    {
        $table = (new \App\Models\Permission())->getTable();

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
