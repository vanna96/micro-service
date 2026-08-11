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
                'units_of_measure.view',
                'units_of_measure.manage',
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
            'tenant-admin' => ['units_of_measure.view', 'units_of_measure.manage'],
            'catalog-manager' => ['units_of_measure.view', 'units_of_measure.manage'],
            'tenant-viewer' => ['units_of_measure.view'],
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['units_of_measure.view', 'units_of_measure.manage'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
