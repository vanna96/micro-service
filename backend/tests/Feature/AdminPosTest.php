<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPosTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-pos.sqlite');

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
    }

    protected function tearDown(): void
    {
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

    public function test_pos_menu_is_hidden_until_tenant_selection_and_available_afterward(): void
    {
        $admin = $this->createUser([
            'username' => 'pos-admin',
            'email' => 'pos-admin@example.com',
            'phone' => '81111111',
        ]);
        $tenant = $this->createTenant('pos-menu-a');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('admin.pos.index'), false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('admin.pos.index'), false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertDontSee('page-topbar', false)
            ->assertSee('Menu Catalog')
            ->assertSee('New Order');
    }

    public function test_tenant_viewer_cannot_see_or_open_pos(): void
    {
        $tenant = $this->createTenant('pos-viewer-a');

        tenancy()->initialize($tenant);
        $user = User::query()->create([
            'name' => 'POS Viewer',
            'username' => 'pos-viewer',
            'email' => 'pos-viewer@example.com',
            'phone' => '82222222',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        $user->roles()->sync([$this->tenantRoleId($tenant, 'tenant-viewer', false)]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($user)
            ->withSession([
                'auth_user_scope' => 'tenant',
                'auth_tenant_id' => $tenant->id,
                'admin_selected_tenant_id' => $tenant->id,
            ])
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('admin.pos.index'), false);

        $this->actingAs($user)
            ->withSession([
                'auth_user_scope' => 'tenant',
                'auth_tenant_id' => $tenant->id,
                'admin_selected_tenant_id' => $tenant->id,
            ])
            ->get(route('admin.pos.index'))
            ->assertRedirect(route('home'));
    }

    public function test_pos_page_filters_items_by_branch(): void
    {
        $admin = $this->createUser([
            'username' => 'pos-filter-admin',
            'email' => 'pos-filter@example.com',
            'phone' => '83333333',
        ]);
        $tenant = $this->createTenant('pos-filter-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $branchA = Branch::query()->create([
            'code' => 'BR-A',
            'name' => 'Front Counter',
            'status' => 'Active',
        ]);
        $branchB = Branch::query()->create([
            'code' => 'BR-B',
            'name' => 'Garden Bar',
            'status' => 'Active',
        ]);
        $category = Category::query()->create([
            'name' => 'Mains',
            'status' => 'Active',
        ]);
        $itemA = \App\Models\Item::query()->create([
            'branch_id' => $branchA->id,
            'category_id' => $category->id,
            'sku' => 'NOODLE-001',
            'name' => 'Noodle Bowl',
            'price' => 8.5,
            'stock' => 12,
            'status' => 'Active',
        ]);
        $itemB = \App\Models\Item::query()->create([
            'branch_id' => $branchB->id,
            'category_id' => $category->id,
            'sku' => 'BURGER-001',
            'name' => 'Burger Stack',
            'price' => 9.75,
            'stock' => 10,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.pos.index', ['branch_id' => $branchA->id]));

        $response->assertOk()
            ->assertSee('Menu Catalog')
            ->assertSee('data-item-id="' . $itemA->id . '"', false)
            ->assertDontSee('data-item-id="' . $itemB->id . '"', false);
    }

    public function test_pos_page_shows_empty_state_when_no_items_exist(): void
    {
        $admin = $this->createUser([
            'username' => 'pos-empty-admin',
            'email' => 'pos-empty@example.com',
            'phone' => '84444444',
        ]);
        $tenant = $this->createTenant('pos-empty-a');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('No active items yet')
            ->assertSee('Create products and categories for this tenant');
    }

    public function test_pos_page_allows_active_items_with_zero_stock(): void
    {
        $admin = $this->createUser([
            'username' => 'pos-zero-stock-admin',
            'email' => 'pos-zero-stock@example.com',
            'phone' => '84444445',
        ]);
        $tenant = $this->createTenant('pos-zero-stock-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $item = \App\Models\Item::query()->create([
            'sku' => 'ZERO-001',
            'name' => 'Zero Stock Plate',
            'price' => 12.5,
            'stock' => 0,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('data-item-id="' . $item->id . '"', false)
            ->assertDontSee('Not Available');
    }

    public function test_pos_pricing_endpoint_returns_promotion_aware_totals_and_rejects_inactive_items(): void
    {
        $admin = $this->createUser([
            'username' => 'pos-price-admin',
            'email' => 'pos-price@example.com',
            'phone' => '85555555',
        ]);
        $tenant = $this->createTenant('pos-price-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $item = \App\Models\Item::query()->create([
            'sku' => 'STEAK-001',
            'name' => 'Steak Plate',
            'price' => 100,
            'stock' => 10,
            'status' => 'Active',
        ]);
        $inactiveItem = \App\Models\Item::query()->create([
            'sku' => 'SOUP-001',
            'name' => 'Soup Bowl',
            'price' => 8,
            'stock' => 10,
            'status' => 'Inactive',
        ]);
        $promotion = Promotion::query()->create([
            'code' => 'POS-FIXED',
            'name' => 'POS Fixed Deal',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Fixed price deal.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'Active',
        ]);
        $promotion->lines()->create([
            'item_id' => $item->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 70,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.pos.price'), [
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('discount_total', 60)
            ->assertJsonPath('final_total', 140)
            ->assertJsonPath('applied_promotion.code', 'POS-FIXED');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.pos.price'), [
                'items' => [
                    ['item_id' => $inactiveItem->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.item_id']);
    }

    protected function createUser(array $attributes): User
    {
        return User::query()->create(array_merge([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'phone' => '12345678',
            'status' => 'Active',
        ], $attributes));
    }

    protected function createTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);
        $fallbackDatabasePath = database_path($id);

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($fallbackDatabasePath)) {
            File::delete($fallbackDatabasePath);
        }

        $this->tenantDatabasePaths[] = $databasePath;
        $this->tenantDatabasePaths[] = $fallbackDatabasePath;

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

    protected function tenantRoleId(Tenant $tenant, string $roleName, bool $resetTenancy = true): int
    {
        if (! tenant() || tenant('id') !== $tenant->id) {
            tenancy()->initialize($tenant);
        }

        $roleId = Role::query()->where('name', $roleName)->value('id');

        if ($resetTenancy) {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->assertNotNull($roleId, 'Expected tenant role [' . $roleName . '] to exist.');

        return (int) $roleId;
    }
}
