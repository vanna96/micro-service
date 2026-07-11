<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('group_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        $now = now();
        $permissionDefinitions = collect(config('tenant_permissions.permissions', []));

        DB::table('permissions')->insert(
            $permissionDefinitions
                ->map(fn (array $permission) => [
                    'name' => $permission['name'],
                    'label' => $permission['label'],
                    'group_name' => $permission['group'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $defaultRoles = collect(config('tenant_permissions.default_roles', []));

        foreach ($defaultRoles as $role) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role['name'],
                'label' => $role['label'],
                'description' => $role['description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissions = $role['permissions'] === '*'
                ? $permissionIds->keys()->all()
                : $role['permissions'];

            DB::table('permission_role')->insert(
                collect($permissions)
                    ->map(fn (string $permissionName) => [
                        'permission_id' => $permissionIds[$permissionName],
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all()
            );
        }

        $tenantAdminRoleId = DB::table('roles')->where('name', 'tenant-admin')->value('id');

        if ($tenantAdminRoleId) {
            DB::table('users')->pluck('id')->each(function ($userId) use ($tenantAdminRoleId, $now) {
                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $tenantAdminRoleId, 'user_id' => $userId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
