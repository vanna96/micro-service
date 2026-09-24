<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = ['reports.view', 'reports.export'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $definitions = collect(config('tenant_permissions.permissions', []))
            ->whereIn('name', self::PERMISSIONS)
            ->keyBy('name');
        $permissionIds = collect();

        foreach (self::PERMISSIONS as $name) {
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

            $permissionIds->put($name, $permissionId);
        }

        foreach (config('tenant_permissions.default_roles', []) as $role) {
            $roleId = DB::table('roles')->where('name', $role['name'])->value('id');

            if (! $roleId) {
                continue;
            }

            $rolePermissionNames = $role['permissions'] === '*'
                ? self::PERMISSIONS
                : array_intersect(self::PERMISSIONS, $role['permissions']);

            foreach ($rolePermissionNames as $name) {
                $permissionId = $permissionIds->get($name);

                if ($permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permissionId, 'role_id' => $roleId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
