<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $permissionDefinitions = collect(config('tenant_permissions.permissions', []))
            ->whereIn('name', [
                'currencies.view',
                'currencies.manage',
                'rate_indexes.view',
                'rate_indexes.manage',
            ])
            ->values();

        $permissionIds = $permissionDefinitions->mapWithKeys(function (array $permission) use ($now) {
            $permissionId = DB::table('permissions')->where('name', $permission['name'])->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permission['name'],
                    'label' => $permission['label'],
                    'group_name' => $permission['group'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return [$permission['name'] => $permissionId];
        });

        $rolePermissions = [
            'tenant-admin' => ['currencies.view', 'currencies.manage', 'rate_indexes.view', 'rate_indexes.manage'],
            'catalog-manager' => ['currencies.view', 'currencies.manage', 'rate_indexes.view', 'rate_indexes.manage'],
            'tenant-viewer' => ['currencies.view', 'rate_indexes.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($permissions as $permissionName) {
                $permissionId = $permissionIds->get($permissionName);

                if (! $permissionId) {
                    continue;
                }

                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $legacyPermissionIds = DB::table('permissions')
            ->whereIn('name', ['exchange_rates.view', 'exchange_rates.manage'])
            ->pluck('id');

        if ($legacyPermissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $legacyPermissionIds)->delete();
            DB::table('permissions')->whereIn('id', $legacyPermissionIds)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['currencies.view', 'currencies.manage', 'rate_indexes.view', 'rate_indexes.manage'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
