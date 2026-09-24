<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RESOURCES = [
        'roles',
        'currencies',
        'rate_indexes',
        'tenant_users',
        'addresses',
        'customers',
        'branches',
        'categories',
        'units_of_measure',
        'items',
        'promotions',
        'price_lists',
        'pos',
        'sliders',
        'file_manager',
    ];

    private const INHERITED_RESOURCES = [
        'uom_groups' => 'units_of_measure',
        'item_variations' => 'items',
        'item_options' => 'items',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $definitions = collect(config('tenant_permissions.permissions', []))->keyBy('name');

        foreach (self::RESOURCES as $resource) {
            $legacyPermissionId = DB::table('permissions')
                ->where('name', "{$resource}.manage")
                ->value('id');
            $legacyRoleIds = $legacyPermissionId
                ? DB::table('permission_role')->where('permission_id', $legacyPermissionId)->pluck('role_id')
                : collect();

            foreach (['create', 'edit', 'delete'] as $action) {
                $name = "{$resource}.{$action}";
                $definition = $definitions->get($name);

                if (! $definition) {
                    continue;
                }

                $permissionId = DB::table('permissions')->where('name', $name)->value('id');

                if (! $permissionId) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'name' => $name,
                        'label' => $definition['label'],
                        'group_name' => $definition['group'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($legacyRoleIds as $roleId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permissionId, 'role_id' => $roleId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }

            if ($legacyPermissionId) {
                DB::table('permission_role')->where('permission_id', $legacyPermissionId)->delete();
                DB::table('permissions')->where('id', $legacyPermissionId)->delete();
            }
        }

        foreach (self::INHERITED_RESOURCES as $resource => $parentResource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissionId = $this->ensurePermission(
                    "{$resource}.{$action}",
                    $definitions,
                    $now
                );
                $parentPermissionId = DB::table('permissions')
                    ->where('name', "{$parentResource}.{$action}")
                    ->value('id');

                if (! $permissionId || ! $parentPermissionId) {
                    continue;
                }

                $roleIds = DB::table('permission_role')
                    ->where('permission_id', $parentPermissionId)
                    ->pluck('role_id');

                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permissionId, 'role_id' => $roleId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        $tenantAdminRoleId = DB::table('roles')->where('name', 'tenant-admin')->value('id');

        foreach (['general_settings.view', 'general_settings.edit'] as $name) {
            $permissionId = $this->ensurePermission($name, $definitions, $now);

            if ($tenantAdminRoleId && $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $tenantAdminRoleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $definitions = collect(config('tenant_permissions.permissions', []))->keyBy('name');

        foreach (self::RESOURCES as $resource) {
            $actionPermissionIds = DB::table('permissions')
                ->whereIn('name', ["{$resource}.create", "{$resource}.edit", "{$resource}.delete"])
                ->pluck('id');
            $roleIds = $actionPermissionIds->isEmpty()
                ? collect()
                : DB::table('permission_role')->whereIn('permission_id', $actionPermissionIds)->pluck('role_id')->unique();
            $viewDefinition = $definitions->get("{$resource}.view", []);
            $label = 'Manage '.str_replace('_', ' ', $resource);
            $legacyPermissionId = DB::table('permissions')->where('name', "{$resource}.manage")->value('id');

            if (! $legacyPermissionId) {
                $legacyPermissionId = DB::table('permissions')->insertGetId([
                    'name' => "{$resource}.manage",
                    'label' => ucfirst($label),
                    'group_name' => $viewDefinition['group'] ?? 'General',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $legacyPermissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            if ($actionPermissionIds->isNotEmpty()) {
                DB::table('permission_role')->whereIn('permission_id', $actionPermissionIds)->delete();
                DB::table('permissions')->whereIn('id', $actionPermissionIds)->delete();
            }
        }

        $addedPermissionNames = collect(array_keys(self::INHERITED_RESOURCES))
            ->flatMap(fn (string $resource) => collect(['view', 'create', 'edit', 'delete'])
                ->map(fn (string $action) => "{$resource}.{$action}"))
            ->merge(['general_settings.view', 'general_settings.edit']);
        $addedPermissionIds = DB::table('permissions')
            ->whereIn('name', $addedPermissionNames)
            ->pluck('id');

        if ($addedPermissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $addedPermissionIds)->delete();
            DB::table('permissions')->whereIn('id', $addedPermissionIds)->delete();
        }
    }

    private function ensurePermission(string $name, $definitions, $now): ?int
    {
        $definition = $definitions->get($name);

        if (! $definition) {
            return null;
        }

        $permissionId = DB::table('permissions')->where('name', $name)->value('id');

        if ($permissionId) {
            return (int) $permissionId;
        }

        return DB::table('permissions')->insertGetId([
            'name' => $name,
            'label' => $definition['label'],
            'group_name' => $definition['group'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
