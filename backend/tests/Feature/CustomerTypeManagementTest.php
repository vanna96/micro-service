<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CustomerTypeManagementTest extends TestCase
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

    public function test_customer_model_supports_type_constants_and_scopes(): void
    {
        $tenant = $this->createSqliteTenant('cust-types-' . uniqid());
        tenancy()->initialize($tenant);

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'John Customer',
            'type' => Customer::TYPE_CUSTOMER,
            'status' => 'Active',
        ]);

        $vendor = Customer::query()->create([
            'code' => 'VEN-001',
            'name' => 'Acme Supplier',
            'type' => Customer::TYPE_VENDOR,
            'status' => 'Active',
        ]);

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isVendor());
        $this->assertTrue($vendor->isVendor());
        $this->assertFalse($vendor->isCustomer());

        $this->assertCount(1, Customer::customers()->get());
        $this->assertEquals('CUS-001', Customer::customers()->first()->code);

        $this->assertCount(1, Customer::vendors()->get());
        $this->assertEquals('VEN-001', Customer::vendors()->first()->code);

        $this->assertCount(2, Customer::all());
    }

    public function test_admin_can_create_and_filter_customers_and_vendors(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createSqliteTenant('adm-cust-' . uniqid());
        $admin->tenants()->sync([$tenant->id]);

        // 1. Create a Customer via Admin
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.customers.store'), [
                'type' => Customer::TYPE_CUSTOMER,
                'code' => 'CUS-ADMIN-01',
                'name' => 'Retail Buyer',
                'email' => 'buyer@example.com',
                'phone' => '012345678',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.customers.index'));

        // 2. Create a Vendor via Admin
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.customers.store'), [
                'type' => Customer::TYPE_VENDOR,
                'code' => 'VEN-ADMIN-01',
                'name' => 'Beverage Wholesaler',
                'email' => 'supplier@example.com',
                'phone' => '098765432',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.customers.index', ['type' => 'vendor']));

        // 3. Create without specifying type -> should default to Customer
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.customers.store'), [
                'code' => 'CUS-ADMIN-DEFAULT',
                'name' => 'Default Buyer',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.customers.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-ADMIN-01',
            'type' => Customer::TYPE_CUSTOMER,
        ], 'tenant');
        $this->assertDatabaseHas('customers', [
            'code' => 'VEN-ADMIN-01',
            'type' => Customer::TYPE_VENDOR,
        ], 'tenant');
        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-ADMIN-DEFAULT',
            'type' => Customer::TYPE_CUSTOMER,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        // 4. Test admin index with All Contacts
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Retail Buyer')
            ->assertSee('Beverage Wholesaler')
            ->assertSee('Default Buyer')
            ->assertSee('Vendor')
            ->assertSee('Customer');

        // 5. Test admin index filtered by type=customer
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.customers.index', ['type' => 'customer']))
            ->assertOk()
            ->assertSee('Retail Buyer')
            ->assertSee('Default Buyer')
            ->assertDontSee('Beverage Wholesaler');

        // 6. Test admin index filtered by type=vendor
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.customers.index', ['type' => 'vendor']))
            ->assertOk()
            ->assertSee('Beverage Wholesaler')
            ->assertDontSee('Retail Buyer')
            ->assertDontSee('Default Buyer');
    }

    public function test_pos_customer_api_defaults_to_customers_and_excludes_vendors(): void
    {
        $tenant = $this->createSqliteTenant('pos-cust-' . uniqid());
        tenancy()->initialize($tenant);

        Customer::query()->create([
            'code' => 'CUS-RETAIL-1',
            'name' => 'Alice Customer',
            'type' => Customer::TYPE_CUSTOMER,
            'status' => 'Active',
        ]);

        Customer::query()->create([
            'code' => 'VEN-WHOLESALE-1',
            'name' => 'Bob Supplier',
            'type' => Customer::TYPE_VENDOR,
            'status' => 'Active',
        ]);

        $response = $this->getJson('/v1/api/pos/customers?tenant=' . $tenant->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $customers = collect($response->json('data.customers'));

        $this->assertTrue($customers->contains('name', 'Alice Customer'));
        $this->assertFalse($customers->contains('name', 'Bob Supplier'));

        // Querying with type=vendor should return only vendors
        $vendorResponse = $this->getJson('/v1/api/pos/customers?tenant=' . $tenant->id . '&type=vendor');
        $vendorResponse->assertOk();
        $vendorList = collect($vendorResponse->json('data.customers'));
        $this->assertFalse($vendorList->contains('name', 'Alice Customer'));
        $this->assertTrue($vendorList->contains('name', 'Bob Supplier'));
    }

    private function createAdminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin User',
            'username' => 'test-admin-' . uniqid(),
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
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
