<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ItemPurchaseSaleFieldsetTest extends TestCase
{
    use DatabaseTransactions;

    private array $tenantDatabasePaths = [];

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge('tenant');

        foreach ($this->tenantDatabasePaths as $databasePath) {
            if (File::exists($databasePath)) {
                File::delete($databasePath);
            }
        }

        parent::tearDown();
    }

    public function test_item_model_defaults_and_scopes_for_purchase_and_sale(): void
    {
        $tenant = $this->createSqliteTenant('item-ps-' . uniqid());
        tenancy()->initialize($tenant);

        $itemAll = Item::query()->create([
            'sku' => 'ITEM-ALL',
            'name' => 'General Item',
            'price' => 10.00,
            'stock' => 50,
            'status' => 'Active',
        ]);

        $this->assertTrue($itemAll->purchase);
        $this->assertTrue($itemAll->sale);
        $this->assertTrue($itemAll->is_purchase);
        $this->assertTrue($itemAll->is_sale);

        $itemPurchaseOnly = Item::query()->create([
            'sku' => 'ITEM-PURCHASE-ONLY',
            'name' => 'Raw Material',
            'price' => 5.00,
            'stock' => 100,
            'purchase' => true,
            'sale' => false,
            'status' => 'Active',
        ]);

        $itemSaleOnly = Item::query()->create([
            'sku' => 'ITEM-SALE-ONLY',
            'name' => 'Finished Goods',
            'price' => 20.00,
            'stock' => 30,
            'purchase' => false,
            'sale' => true,
            'status' => 'Active',
        ]);

        $this->assertTrue($itemPurchaseOnly->purchase);
        $this->assertFalse($itemPurchaseOnly->sale);
        $this->assertFalse($itemSaleOnly->purchase);
        $this->assertTrue($itemSaleOnly->sale);

        $this->assertCount(2, Item::purchase()->get());
        $this->assertCount(2, Item::sale()->get());
    }

    public function test_admin_item_form_displays_fieldset_and_persists_purchase_and_sale(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('item-adm-ps-' . uniqid());
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $uomGroup = \App\Models\UomGroup::query()->create([
            'code' => 'GRP-DEFAULT',
            'name' => 'Default Group',
            'status' => 'Active',
        ]);
        $uom = \App\Models\UnitOfMeasure::query()->create([
            'code' => 'PCS',
            'name' => 'Piece',
            'status' => 'Active',
        ]);
        $uomGroup->units()->create([
            'unit_of_measure_id' => $uom->id,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        // 1. Visit Create page -> should see Item Scope fieldset with checkboxes
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.create'))
            ->assertOk()
            ->assertSee('Item Scope')
            ->assertSee('name="purchase"', false)
            ->assertSee('name="sale"', false)
            ->assertSee('checked', false);

        // 2. Store an item with purchase = true and sale = false
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.store'), [
                'item_type' => 'uom',
                'uom_group_id' => $uomGroup->id,
                'sku' => 'SKU-PURCHASE-MATERIAL',
                'name' => 'Raw Coffee Beans',
                'price' => 12.50,
                'stock' => 100,
                'currency_id' => $currency->id,
                'status' => 'Active',
                'scope_submitted' => '1',
                'purchase' => '1',
                // sale checkbox omitted -> should be false
            ])
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $createdItem = Item::query()->where('sku', 'SKU-PURCHASE-MATERIAL')->firstOrFail();
        $this->assertTrue($createdItem->purchase);
        $this->assertFalse($createdItem->sale);
        tenancy()->end();
        DB::purge('tenant');

        // 3. Visit Index -> should display Purchase badge
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('SKU-PURCHASE-MATERIAL')
            ->assertSee('Purchase');

        // 4. Update the item to enable Sale as well
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.update', ['item' => $createdItem->id]), [
                '_method' => 'PUT',
                'item_type' => 'uom',
                'uom_group_id' => $uomGroup->id,
                'sku' => 'SKU-PURCHASE-MATERIAL',
                'name' => 'Raw Coffee Beans Retail',
                'price' => 15.00,
                'stock' => 100,
                'currency_id' => $currency->id,
                'status' => 'Active',
                'scope_submitted' => '1',
                'purchase' => '1',
                'sale' => '1',
            ])
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $createdItem->refresh();
        $this->assertTrue($createdItem->purchase);
        $this->assertTrue($createdItem->sale);
        tenancy()->end();
        DB::purge('tenant');
    }

    private function createAdminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin User',
            'username' => 'test-admin-' . uniqid(),
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'phone' => (string) rand(10000000, 99999999),
            'status' => 'Active',
        ]);
    }

    private function createSqliteTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);

        DB::purge('tenant');

        Tenant::query()->where('id', $id)->delete();

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        File::put($databasePath, '');
        $this->tenantDatabasePaths[] = $databasePath;

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
