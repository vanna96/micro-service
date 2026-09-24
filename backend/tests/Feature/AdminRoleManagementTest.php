<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-roles.sqlite');

        if (! File::exists(dirname($this->databasePath))) {
            File::makeDirectory(dirname($this->databasePath), 0755, true);
        }

        if (! File::exists($this->databasePath)) {
            File::put($this->databasePath, '');
        }

        $sqliteConnection = [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        config()->set('database.default', 'central');
        config()->set('database.connections.central', $sqliteConnection);
        config()->set('database.connections.mysql', $sqliteConnection);

        DB::purge('central');
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--database' => 'central']);

        Event::fake([
            TenantCreated::class,
            TenantDeleted::class,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        DB::disconnect('mysql');
        DB::purge('central');
        DB::purge('mysql');

        if (isset($this->databasePath) && File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        foreach ($this->tenantDatabasePaths as $tenantDatabasePath) {
            if (File::exists($tenantDatabasePath)) {
                File::delete($tenantDatabasePath);
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_view_roles_index_with_stat_cards_and_roles_list(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-idx');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        Role::query()->create([
            'name' => 'cashier',
            'label' => 'Cashier Staff',
            'description' => 'Handles POS and payments',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.roles.index'));

        $response->assertOk()
            ->assertSee('Roles & Permissions')
            ->assertSee('Cashier Staff')
            ->assertSee('cashier')
            ->assertSee('Handles POS and payments')
            ->assertSee('Create New Role');
    }

    public function test_admin_can_view_role_create_page(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-create');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        Permission::query()->firstOrCreate([
            'name' => 'items.view',
        ], [
            'label' => 'View Items',
            'group_name' => 'Catalog',
        ]);
        Permission::query()->firstOrCreate([
            'name' => 'items.create',
        ], [
            'label' => 'Create Items',
            'group_name' => 'Catalog',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.roles.create'));

        $response->assertOk()
            ->assertSee('Create New Role')
            ->assertSee('Role Display Name')
            ->assertSee('Role Key / Slug')
            ->assertSee('Permissions Granted')
            ->assertSee('Catalog')
            ->assertSee('items.view')
            ->assertSee('items.create')
            ->assertSee('Save Role');
    }

    public function test_admin_can_store_role_with_permissions(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-store');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $p1 = Permission::query()->firstOrCreate(['name' => 'items.view'], ['label' => 'View Items', 'group_name' => 'Catalog']);
        $p2 = Permission::query()->firstOrCreate(['name' => 'pos.view'], ['label' => 'View POS', 'group_name' => 'Sales']);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.roles.store'), [
                'name' => 'store-lead',
                'label' => 'Store Lead',
                'description' => 'Supervises store operations',
                'permissions' => [$p1->id, $p2->id],
            ]);

        $response->assertSessionHas('status', 'Role created successfully.');

        tenancy()->initialize($tenant);
        $role = Role::query()->where('name', 'store-lead')->first();
        $this->assertNotNull($role);
        $this->assertEquals('Store Lead', $role->label);
        $this->assertEquals('Supervises store operations', $role->description);
        $this->assertEquals(2, $role->permissions()->count());
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_update_role_and_permissions(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-update');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $p1 = Permission::query()->firstOrCreate(['name' => 'items.view'], ['label' => 'View Items', 'group_name' => 'Catalog']);
        $p2 = Permission::query()->firstOrCreate(['name' => 'items.edit'], ['label' => 'Edit Items', 'group_name' => 'Catalog']);
        $role = Role::query()->create([
            'name' => 'inventory-staff',
            'label' => 'Inventory Staff',
            'description' => 'Initial notes',
        ]);
        $role->permissions()->sync([$p1->id]);
        tenancy()->end();
        DB::purge('tenant');

        // Test edit view loads
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.roles.edit', ['role' => $role->id]));

        $response->assertOk()
            ->assertSee('Edit Role: Inventory Staff')
            ->assertSee('inventory-staff')
            ->assertSee('Update Role');

        // Test update
        $updateResponse = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.roles.update', ['role' => $role->id]), [
                'name' => 'inventory-staff',
                'label' => 'Senior Inventory Staff',
                'description' => 'Updated senior notes',
                'permissions' => [$p1->id, $p2->id],
            ]);

        $updateResponse->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('status', 'Role updated successfully.');

        tenancy()->initialize($tenant);
        $reloaded = Role::query()->find($role->id);
        $this->assertEquals('Senior Inventory Staff', $reloaded->label);
        $this->assertEquals(2, $reloaded->permissions()->count());
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_delete_role(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-del');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $role = Role::query()->create([
            'name' => 'temporary-role',
            'label' => 'Temporary Role',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.roles.destroy', ['role' => $role->id]));

        $response->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('status', 'Role deleted successfully.');

        tenancy()->initialize($tenant);
        $this->assertNull(Role::query()->find($role->id));
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_role_validation_prevents_duplicate_name_and_missing_permissions(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('roles-val');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        Role::query()->create([
            'name' => 'existing-role',
            'label' => 'Existing Role',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.roles.store'), [
                'name' => 'existing-role',
                'label' => '',
                'permissions' => [],
            ]);

        $response->assertSessionHasErrors(['name', 'label', 'permissions']);
    }

    private function createAdminUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => 'role-admin-' . uniqid(),
            'name' => 'Role Admin',
            'email' => 'role-admin-' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'Active',
        ], $attributes));
    }

    private function createSqliteTenant(string $id): Tenant
    {
        $databaseName = 'tenant-roles-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);
        $alternateDatabasePath = database_path($id);

        DB::purge('tenant');

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($alternateDatabasePath)) {
            File::delete($alternateDatabasePath);
        }

        File::put($databasePath, '');

        $this->tenantDatabasePaths[] = $databasePath;
        $this->tenantDatabasePaths[] = $alternateDatabasePath;

        $tenant = Tenant::query()->create([
            'id' => $id,
            'db_connection' => 'sqlite',
            'db_port' => '',
            'db_name' => $databaseName,
            'db_host' => '',
            'db_username' => '',
            'db_password' => '',
            'status' => 'Active',
        ]);

        tenancy()->initialize($tenant);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        return $tenant;
    }
}
