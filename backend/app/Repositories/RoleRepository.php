<?php

namespace App\Repositories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Role';

    public function getAdminListing(): Collection
    {
        return $this->roleModel()
            ->newQuery()
            ->withCount(['permissions', 'users'])
            ->orderBy('label')
            ->get();
    }

    public function loadForAdminEdit(int $roleId): Role
    {
        return $this->roleModel()
            ->newQuery()
            ->with(['permissions'])
            ->whereKey($roleId)
            ->firstOrFail();
    }

    public function createForAdmin(array $attributes): Role
    {
        $permissionIds = $attributes['permissions'] ?? [];
        unset($attributes['permissions']);

        $role = $this->roleModel();
        $role->fill($attributes);
        $role->save();
        $role->permissions()->sync($permissionIds);

        $this->flushRelatedCaches();

        return $role->load('permissions');
    }

    public function updateForAdmin(Role $role, array $attributes): Role
    {
        $permissionIds = $attributes['permissions'] ?? [];
        unset($attributes['permissions']);

        $role->fill($attributes);
        $role->save();
        $role->permissions()->sync($permissionIds);

        $this->flushRelatedCaches();

        return $role->load('permissions');
    }

    public function deleteForAdmin(Role $role): void
    {
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        $this->flushRelatedCaches();
    }

    public function getPermissionGroups(): Collection
    {
        return $this->permissionModel()
            ->newQuery()
            ->orderBy('group_name')
            ->orderBy('label')
            ->get()
            ->groupBy('group_name');
    }

    public function getRoleOptions(): Collection
    {
        return $this->roleModel()
            ->newQuery()
            ->orderBy('label')
            ->get();
    }

    protected function roleModel(): Role
    {
        /** @var \App\Models\Role $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name ?: 'tenant');
        }

        return $model;
    }

    protected function permissionModel(): Permission
    {
        $model = new Permission();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name ?: 'tenant');
        }

        return $model;
    }

    protected function flushRelatedCaches(): void
    {
        Role::flushQueryCache();
        Permission::flushQueryCache();
        User::flushQueryCache();
    }
}
