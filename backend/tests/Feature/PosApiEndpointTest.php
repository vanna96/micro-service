<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PosSale;
use App\Models\PriceList;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosApiEndpointTest extends TestCase
{
    protected string $databasePath;

    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/pos-api.sqlite');

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
        config()->set('tenancy.database.central_connection', 'central');

        DB::purge('central');
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }

    protected function tearDown(): void
    {
        if (isset($this->databasePath) && File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        foreach ($this->tenantDatabasePaths as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_pos_catalog_endpoints_under_v1_api_pos(): void
    {
        $tenant = $this->createTenant('pos-catalog-test');

        tenancy()->initialize($tenant);

        $branch = Branch::query()->create([
            'code' => 'MAIN',
            'name' => 'Main POS Branch',
            'status' => 'Active',
            'sort_order' => 1,
        ]);

        $category = Category::query()->create([
            'name' => 'Beverages',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'sku' => 'BEV-001',
            'name' => 'Iced Latte',
            'price' => 4.50,
            'stock' => 50,
            'status' => 'Active',
            'is_try_on_enabled' => true,
            'is_featured' => true,
            'is_premium' => false,
            'is_new_arrival' => true,
            'purchase' => true,
            'sale' => true,
        ]);

        $purchaseOnlyItem = Item::query()->create([
            'sku' => 'PUR-001',
            'name' => 'Raw Coffee Beans',
            'price' => 15.00,
            'stock' => 100,
            'status' => 'Active',
            'purchase' => true,
            'sale' => false,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Test GET /v1/api/pos/branches
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/pos/branches')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Main POS Branch');

        // Test GET /v1/api/pos/categories
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/pos/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Beverages');

        // Test GET /v1/api/pos/products - only sale items returned
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/pos/products')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'BEV-001')
            ->assertJsonPath('data.0.is_try_on_enabled', true)
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonPath('data.0.is_premium', false)
            ->assertJsonPath('data.0.is_new_arrival', true);

        // Test filtering by try_on
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/pos/products?try_on=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.sku', 'BEV-001');

        // Test GET /v1/api/pos/products/{item} for sale item succeeds
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson("/v1/api/pos/products/{$item->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Iced Latte')
            ->assertJsonPath('data.is_try_on_enabled', true);

        // Test GET /v1/api/pos/products/{item} for purchase-only item returns 404
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson("/v1/api/pos/products/{$purchaseOnlyItem->id}")
            ->assertNotFound();

        // Test POST /v1/api/pos/cart/price
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/cart/price', [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                    'unit_price' => 4.50,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', 9);

        // Test POST /v1/api/pos/cart/price rejects purchase-only item
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/cart/price', [
            'items' => [
                [
                    'item_id' => $purchaseOnlyItem->id,
                    'quantity' => 1,
                    'unit_price' => 15.00,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.item_id']);
    }

    public function test_pos_customers_and_sales_endpoints_under_v1_api_pos(): void
    {
        $tenant = $this->createTenant('pos-sales-test');

        tenancy()->initialize($tenant);

        $priceList = PriceList::query()->create([
            'code' => 'VIP',
            'name' => 'VIP Club',
            'header_pricing_method' => 'discount',
            'header_discount_percent' => 10,
            'status' => 'Active',
            'is_default' => false,
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Alice Walker',
            'price_list_id' => $priceList->id,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Test GET /v1/api/pos/customers
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/pos/customers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customers.0.name', 'Alice Walker')
            ->assertJsonPath('data.customers.0.price_list_id', $priceList->id)
            ->assertJsonPath('data.price_lists.0.pricing_method', 'discount')
            ->assertJsonPath('data.price_lists.0.discount_percent', 10);

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        // Test PUT /v1/api/pos/sales/current
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->putJson('/v1/api/pos/sales/current', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [],
                'totals' => ['totalPayable' => 25.0],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Test GET /v1/api/pos/sales
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson("/v1/api/pos/sales?client_token={$clientToken}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current.sale_id', $saleId);

        // Test Customer Display Sync: POST /v1/api/pos/display/sync
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/display/sync', [
            'token' => $clientToken,
            'action' => 'cart_updated',
            'cart' => [],
            'totals' => ['totalPayable' => 25.0],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Test Customer Display State: GET /v1/api/pos/display/state
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson("/v1/api/pos/display/state?token={$clientToken}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'cart_updated');
    }

    public function test_pos_sale_checkout_rejects_non_sale_item(): void
    {
        $tenant = $this->createTenant('pos-sale-non-sale-test');

        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
            'is_default' => true,
        ]);

        $item = Item::query()->create([
            'sku' => 'PUR-ONLY',
            'name' => 'Warehouse Raw Ingredient',
            'price' => 10.00,
            'stock' => 50,
            'status' => 'Active',
            'purchase' => true,
            'sale' => false,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        $response = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'reference' => 'INV-TEST-001',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => 'Warehouse Raw Ingredient',
                            'sku' => 'PUR-ONLY',
                            'currency' => ['code' => 'USD'],
                        ],
                        'quantity' => 1,
                        'unitPrice' => 10.00,
                    ],
                ],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 10.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 10.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 10.00,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['snapshot.cart.0.product.id']);
    }

    public function test_pos_checkout_reprices_server_side_and_rejects_injected_totals_and_promotion(): void
    {
        $tenant = $this->createTenant('pos-secure-checkout');

        tenancy()->initialize($tenant);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
            'is_default' => true,
        ]);
        $item = Item::query()->create([
            'sku' => 'SECURE-001',
            'name' => 'Secure Checkout Item',
            'price' => 10,
            'stock' => 10,
            'status' => 'Active',
            'sale' => true,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $saleId = (string) Str::uuid();
        $payload = [
            'client_token' => (string) Str::uuid(),
            'sale_id' => $saleId,
            'reference' => 'INV-SECURE-001',
            'sale_from' => 'admin',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [[
                    'product' => [
                        'id' => $item->id,
                        'name' => 'Injected Name',
                        'sku' => 'INJECTED-SKU',
                        'currency' => ['code' => 'USD'],
                    ],
                    'quantity' => 1,
                    'unitPrice' => 0.01,
                ]],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
                'appliedPromotion' => [
                    'id' => 999999,
                    'name' => 'Injected Promotion',
                ],
            ],
            'totals' => [
                'sub_total' => 0.01,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 0.01,
            ],
            'tenders' => [[
                'currency_code' => 'USD',
                'amount' => 10,
            ]],
        ];

        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'totals.sub_total',
                'totals.total_payable',
                'snapshot.appliedPromotion',
            ]);

        tenancy()->initialize($tenant);
        $this->assertSame(10, (int) $item->fresh()->stock);
        $this->assertSame(0, PosSale::query()->count());
        tenancy()->end();
        DB::purge('tenant');

        unset($payload['snapshot']['appliedPromotion']);
        $payload['totals']['sub_total'] = 10;
        $payload['totals']['total_payable'] = 10;

        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', $payload)
            ->assertOk()
            ->assertJsonPath('data.total_base', 10);

        tenancy()->initialize($tenant);
        $sale = PosSale::query()->with('items')->firstOrFail();
        $this->assertSame('pos', $sale->sale_from);
        $this->assertSame(10.0, (float) $sale->items->first()->unit_price_base);
        $this->assertSame('Secure Checkout Item', $sale->items->first()->name);
        $this->assertSame(9, (int) $item->fresh()->stock);
        $this->assertNotNull($sale->stock_deducted_at);
        tenancy()->end();
        DB::purge('tenant');
    }

    protected function createTenant(string $id): Tenant
    {
        $databaseName = 'tenant-'.$id.'.sqlite';
        $databasePath = database_path($databaseName);
        $fallbackDatabasePath = database_path($id);

        DB::purge('tenant');

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($fallbackDatabasePath)) {
            File::delete($fallbackDatabasePath);
        }

        File::put($databasePath, '');

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
