<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportDatabaseIntegrationTest extends TestCase
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

    public function test_admin_reports_sales_queries_database_and_computes_kpis(): void
    {
        $tenant = $this->createSqliteTenant('rep-sales-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $customer = Customer::query()->create([
            'code' => 'CUST-001',
            'type' => Customer::TYPE_CUSTOMER,
            'name' => 'Alice Walker',
            'phone' => '012345678',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'ITEM-001',
            'name' => 'Fresh Orange Juice',
            'price' => 5.00,
            'stock' => 100,
            'stock_control' => true,
            'purchase' => true,
            'sale' => true,
            'status' => 'Active',
        ]);

        $sale = $this->createPosSale([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'customer_code' => $customer->code,
            'customer_name' => $customer->name,
            'order_type' => 'dine_in',
            'status' => 'completed',
            'item_count' => 2,
            'subtotal_base' => 10.00,
            'discount_base' => 1.00,
            'tax_base' => 0.90,
            'total_base' => 9.90,
            'payment_method' => 'cash',
            'completed_at' => Carbon::now()->subHours(2),
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => 2,
            'currency_code' => 'USD',
            'unit_price' => 5.00,
            'unit_price_base' => 5.00,
            'line_subtotal_base' => 10.00,
            'discount_base' => 1.00,
            'tax_base' => 0.90,
            'line_total_base' => 9.90,
        ]);

        PosSalePayment::query()->create([
            'pos_sale_id' => $sale->id,
            'method' => 'cash',
            'currency_code' => 'USD',
            'amount' => 9.90,
            'amount_base' => 9.90,
        ]);

        tenancy()->end();

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.reports.sales'));

        $response->assertStatus(200);
        $response->assertViewHas('gross_revenue', 9.90);
        $response->assertViewHas('completed_orders', 1);
        $response->assertViewHas('total_items', 2);
        $response->assertSee('INV-TEST-001');
        $response->assertSee('Alice Walker');
    }

    public function test_admin_reports_products_queries_database(): void
    {
        $tenant = $this->createSqliteTenant('rep-prod-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $category = Category::query()->create([
            'name' => 'Beverages',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'category_id' => $category->id,
            'sku' => 'PROD-002',
            'name' => 'Cold Brew Coffee',
            'price' => 4.50,
            'stock' => 50,
            'status' => 'Active',
        ]);

        $sale = $this->createPosSale([
            'invoice_number' => 'INV-TEST-002',
            'status' => 'completed',
            'total_base' => 9.00,
            'completed_at' => Carbon::now()->subDay(),
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => 2,
            'currency_code' => 'USD',
            'unit_price' => 4.50,
            'unit_price_base' => 4.50,
            'line_subtotal_base' => 9.00,
            'line_total_base' => 9.00,
        ]);

        tenancy()->end();

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.reports.products'));

        $response->assertStatus(200);
        $response->assertViewHas('total_units', 2.0);
        $response->assertViewHas('total_revenue', 9.0);
        $response->assertSee('Cold Brew Coffee');
    }

    public function test_admin_reports_inventory_queries_database(): void
    {
        $tenant = $this->createSqliteTenant('rep-inv-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        Item::query()->create([
            'sku' => 'INV-HEALTHY',
            'name' => 'Healthy Stock Item',
            'price' => 10.00,
            'stock' => 50,
            'status' => 'Active',
        ]);

        Item::query()->create([
            'sku' => 'INV-LOW',
            'name' => 'Low Stock Item',
            'price' => 20.00,
            'stock' => 5,
            'status' => 'Active',
        ]);

        Item::query()->create([
            'sku' => 'INV-OUT',
            'name' => 'Out of Stock Item',
            'price' => 15.00,
            'stock' => 0,
            'status' => 'Active',
        ]);

        tenancy()->end();

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.reports.inventory'));

        $response->assertStatus(200);
        $response->assertViewHas('total_active_items', 3);
        $response->assertViewHas('healthy_items', 1);
        $response->assertViewHas('low_stock_items', 1);
        $response->assertViewHas('out_of_stock_items', 1);
        $response->assertViewHas('total_stock_value', (float) (50 * 10.00 + 5 * 20.00));
        $response->assertSee('Healthy Stock Item');
        $response->assertSee('Low Stock Item');
        $response->assertSee('Out of Stock Item');
    }

    public function test_admin_reports_payments_queries_database(): void
    {
        $tenant = $this->createSqliteTenant('rep-pay-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $this->createPosSale([
            'invoice_number' => 'INV-PAY-001',
            'status' => 'completed',
            'payment_method' => 'card',
            'total_base' => 55.00,
            'completed_at' => Carbon::now()->subHours(5),
        ]);

        tenancy()->end();

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.reports.payments'));

        $response->assertStatus(200);
        $response->assertViewHas('total_revenue', 55.00);
        $response->assertViewHas('total_transactions', 1);
    }

    public function test_admin_reports_sale_detail_json_returns_database_record(): void
    {
        $tenant = $this->createSqliteTenant('rep-detail-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $sale = $this->createPosSale([
            'invoice_number' => 'INV-DETAIL-999',
            'status' => 'completed',
            'total_base' => 123.45,
            'completed_at' => Carbon::now(),
        ]);

        tenancy()->end();

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->getJson(route('admin.reports.sales.detail', ['sale' => $sale->id]));

        $response->assertStatus(200);
        $response->assertJsonPath('sale.invoice_number', 'INV-DETAIL-999');
        $response->assertJsonPath('sale.total_base', '123.45000000');
    }

    private function createPosSale(array $attributes = []): PosSale
    {
        return PosSale::query()->create(array_merge([
            'client_token' => (string) \Illuminate\Support\Str::uuid(),
            'sale_key' => (string) \Illuminate\Support\Str::uuid(),
            'snapshot' => [],
            'status' => 'completed',
        ], $attributes));
    }

    private function createAdminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin User',
            'username' => 'admin-' . uniqid(),
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

        $tenant->forceFill([
            'general_settings' => [
                'currency' => 'USD',
            ],
        ])->save();

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
