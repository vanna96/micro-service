<?php

namespace Tests\Feature;

use App\Jobs\DeductPosSaleStockJob;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosStockDeductionTest extends TestCase
{
    protected string $databasePath;

    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/pos-stock.sqlite');

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

    public function test_pos_sale_reserves_stock_atomically_and_ignores_client_price(): void
    {
        Queue::fake([DeductPosSaleStockJob::class]);

        $tenant = $this->createTenant('pos-stock-dispatch');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

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
            'currency_id' => $currency->id,
            'sku' => 'ITEM-DISPATCH',
            'name' => 'Green Tea',
            'price' => 3.00,
            'stock' => 50,
            'status' => 'Active',
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
            'reference' => 'INV-001',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => 'Green Tea',
                            'sku' => 'ITEM-DISPATCH',
                            'currency' => ['code' => 'USD'],
                        ],
                        'quantity' => 2,
                        'unitPrice' => 0.01,
                    ],
                ],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 6.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 6.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 6.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertNotPushed(DeductPosSaleStockJob::class);

        tenancy()->initialize($tenant);
        $sale = PosSale::query()->with('items')->where('sale_key', $saleId)->firstOrFail();
        $this->assertSame(48, (int) $item->fresh()->stock);
        $this->assertNotNull($sale->stock_deducted_at);
        $this->assertSame(3.0, (float) $sale->items->first()->unit_price_base);
        $this->assertSame(6.0, (float) $sale->total_base);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_decrements_standard_item_stock(): void
    {
        $tenant = $this->createTenant('pos-stock-decrement');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

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
            'currency_id' => $currency->id,
            'sku' => 'ITEM-COFFEE',
            'name' => 'Americano',
            'price' => 3.50,
            'stock' => 50,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-002',
            'invoice_number' => 'INV-002',
            'snapshot' => [],
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'sku' => 'ITEM-COFFEE',
            'name' => 'Americano',
            'quantity' => 5,
            'currency_code' => 'USD',
            'unit_price' => 3.50,
            'line_total_base' => 17.50,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Run the background job
        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);
        $job->handle();

        // Reconnect and check stock
        tenancy()->initialize($tenant);
        $this->assertEquals(45, $item->fresh()->stock);

        $freshSale = $sale->fresh();
        $this->assertNotNull($freshSale->stock_deducted_at);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_handles_uom_conversion_factor(): void
    {
        $tenant = $this->createTenant('pos-stock-uom');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

        $baseUnit = UnitOfMeasure::query()->create([
            'code' => 'CAN',
            'name' => 'Can',
            'symbol' => 'CAN',
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);

        $packUnit = UnitOfMeasure::query()->create([
            'code' => 'PACK6',
            'name' => 'Pack of 6',
            'symbol' => 'PACK6',
            'conversion_factor_to_base' => 6,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);

        $uomGroup = UomGroup::query()->create([
            'code' => 'SODA_UOM',
            'name' => 'Soda UOM Group',
            'base_unit_id' => $baseUnit->id,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $baseUnit->id,
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $packUnit->id,
            'conversion_factor_to_base' => 6,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'SODA-01',
            'name' => 'Cola Can',
            'price' => 1.50,
            'stock' => 60, // 60 individual cans in stock
            'uom_group_id' => $uomGroup->id,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-003',
            'snapshot' => [],
        ]);

        // Sell 3 Packs of 6 => should deduct 3 * 6 = 18 cans from base stock
        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'sku' => 'SODA-01',
            'name' => 'Cola Can',
            'uom_code' => 'PACK6',
            'uom_name' => 'Pack of 6',
            'quantity' => 3,
            'currency_code' => 'USD',
            'unit_price' => 8.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);
        $job->handle();

        tenancy()->initialize($tenant);
        // 60 - 18 = 42
        $this->assertEquals(42, $item->fresh()->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_decrements_variant_stock(): void
    {
        $tenant = $this->createTenant('pos-stock-variant');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'SHIRT-01',
            'name' => 'T-Shirt',
            'price' => 20.00,
            'stock' => 100,
            'status' => 'Active',
        ]);

        $variant = ItemVariant::query()->create([
            'item_id' => $item->id,
            'sku' => 'SHIRT-M',
            'name' => 'T-Shirt - Size M',
            'price' => 20.00,
            'stock' => 25,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-004',
            'snapshot' => [],
        ]);

        // Sell 4 units of Variant M
        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'item_variant_id' => $variant->id,
            'sku' => 'SHIRT-M',
            'name' => 'T-Shirt - Size M',
            'quantity' => 4,
            'currency_code' => 'USD',
            'unit_price' => 20.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);
        $job->handle();

        tenancy()->initialize($tenant);
        // Variant stock: 25 - 4 = 21
        $this->assertEquals(21, $variant->fresh()->stock);
        // Parent item stock: 100 - 4 = 96
        $this->assertEquals(96, $item->fresh()->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_is_idempotent_and_does_not_double_deduct(): void
    {
        $tenant = $this->createTenant('pos-stock-idempotent');
        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'IDEM-01',
            'name' => 'Notebook',
            'price' => 5.00,
            'stock' => 20,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-005',
            'snapshot' => [],
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'sku' => 'IDEM-01',
            'name' => 'Notebook',
            'quantity' => 3,
            'currency_code' => 'USD',
            'unit_price' => 5.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);

        // First run
        $job->handle();

        tenancy()->initialize($tenant);
        $this->assertEquals(17, $item->fresh()->stock);
        $this->assertNotNull($sale->fresh()->stock_deducted_at);
        tenancy()->end();
        DB::purge('tenant');

        // Second run with same job / sale ID
        $job->handle();

        tenancy()->initialize($tenant);
        // Stock must still be 17, not 14
        $this->assertEquals(17, $item->fresh()->stock);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_clamps_to_zero_without_underflow_error(): void
    {
        $tenant = $this->createTenant('pos-stock-clamp');
        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'CLAMP-01',
            'name' => 'Pen',
            'price' => 1.00,
            'stock' => 2,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-006',
            'snapshot' => [],
        ]);

        // Sell 5 units when only 2 exist
        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'sku' => 'CLAMP-01',
            'name' => 'Pen',
            'quantity' => 5,
            'currency_code' => 'USD',
            'unit_price' => 1.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);
        $job->handle();

        tenancy()->initialize($tenant);
        // Stock should be safely clamped to 0
        $this->assertEquals(0, $item->fresh()->stock);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_pos_sale_rejects_over_stock_when_stock_control_is_enabled(): void
    {
        $tenant = $this->createTenant('pos-stock-control-on');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'CTRL-ON',
            'name' => 'Limited Bag',
            'price' => 25.00,
            'stock' => 2,
            'stock_control' => true,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        // Attempt to purchase 5 when only 2 in stock
        $response = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'reference' => 'INV-FAIL-01',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => 'Limited Bag',
                            'sku' => 'CTRL-ON',
                            'currency' => ['code' => 'USD'],
                        ],
                        'quantity' => 5,
                        'unitPrice' => 25.00,
                    ],
                ],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 125.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 125.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 125.00,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('snapshot.cart');
    }

    public function test_pos_sale_allows_over_stock_and_skips_queue_deduction_when_stock_control_is_disabled(): void
    {
        $tenant = $this->createTenant('pos-stock-control-off');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'CTRL-OFF',
            'name' => 'Custom Service',
            'price' => 10.00,
            'stock' => 2,
            'stock_control' => false,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        // Attempt to purchase 10 when stock is 2, but stock_control is false
        $response = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'reference' => 'INV-PASS-01',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => 'Custom Service',
                            'sku' => 'CTRL-OFF',
                            'currency' => ['code' => 'USD'],
                        ],
                        'quantity' => 10,
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
                'sub_total' => 100.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 100.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 100.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Run the background deduction job
        tenancy()->initialize($tenant);
        $savedSale = PosSale::query()->where('sale_key', $saleId)->first();
        tenancy()->end();
        DB::purge('tenant');

        $this->assertNotNull($savedSale);
        $job = new DeductPosSaleStockJob($savedSale->id, $tenant->id);
        $job->handle();

        // Stock should remain 2 because stock_control is false
        tenancy()->initialize($tenant);
        $this->assertEquals(2, $item->fresh()->stock);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_deduct_stock_job_recovers_and_deducts_variant_when_variant_id_is_missing_by_matching_sku(): void
    {
        $tenant = $this->createTenant('pos-stock-variant-recovery');
        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'SHIRT-ROOT',
            'name' => 'T-Shirt Master',
            'item_type' => 'variation',
            'price' => 25.00,
            'stock' => 50,
            'stock_control' => true,
            'status' => 'Active',
        ]);

        $variant = ItemVariant::query()->create([
            'item_id' => $item->id,
            'sku' => 'SHIRT-L',
            'name' => 'T-Shirt - Size L',
            'price' => 25.00,
            'stock' => 15,
            'status' => 'Active',
        ]);

        $sale = PosSale::query()->create([
            'client_token' => (string) Str::uuid(),
            'sale_key' => (string) Str::uuid(),
            'status' => 'completed',
            'reference' => 'INV-REC-01',
            'snapshot' => [],
        ]);

        // PosSaleItem has item_variant_id as null, but sku as SHIRT-L
        $saleItem = PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'item_id' => $item->id,
            'item_variant_id' => null,
            'sku' => 'SHIRT-L',
            'name' => 'T-Shirt - Size L',
            'quantity' => 3,
            'currency_code' => 'USD',
            'unit_price' => 25.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $job = new DeductPosSaleStockJob($sale->id, $tenant->id);
        $job->handle();

        tenancy()->initialize($tenant);
        // Variant stock should be deducted: 15 - 3 = 12
        $this->assertEquals(12, $variant->fresh()->stock);
        // Parent item stock should be deducted: 50 - 3 = 47
        $this->assertEquals(47, $item->fresh()->stock);
        // PosSaleItem should have its item_variant_id recovered
        $this->assertEquals($variant->id, $saleItem->fresh()->item_variant_id);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_pos_sale_rejects_stale_client_variant_id_without_deducting_stock(): void
    {
        $tenant = $this->createTenant('pos-stock-stale-variant-id');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

        $branch = Branch::query()->create([
            'code' => 'MAIN',
            'name' => 'Main POS Branch',
            'status' => 'Active',
            'sort_order' => 1,
        ]);

        $category = Category::query()->create([
            'name' => 'Fitness',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'currency_id' => $currency->id,
            'sku' => 'FT-DBL-031',
            'name' => 'Adjustable Dumbbell Set',
            'item_type' => 'variation',
            'price' => 59.99,
            'stock' => 24,
            'stock_control' => true,
            'status' => 'Active',
        ]);

        $variant = ItemVariant::query()->create([
            'item_id' => $item->id,
            'sku' => 'FT-DBL-031-32KG',
            'name' => 'Pair 32kg (2x16kg)',
            'price' => 99.99,
            'stock' => 8,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        // Client sends an outdated variant ID (e.g. 99999), but matching SKU and options
        $response = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'reference' => 'VP-STALE-001',
            'snapshot' => [
                'saleId' => $saleId,
                'invoiceNumber' => 'VP-STALE-001',
                'orderType' => 'Takeaway',
                'customer' => ['id' => null],
                'cart' => [
                    [
                        'id' => "{$item->id}-VARIANT:99999",
                        'product' => [
                            'id' => (string) $item->id,
                            'sku' => $item->sku,
                            'name' => $item->name,
                            'price' => 59.99,
                            'currency' => ['code' => 'USD'],
                        ],
                        'quantity' => 1,
                        'unitPrice' => 99.99,
                        'selectedVariant' => [
                            'id' => 99999, // Stale ID!
                            'sku' => 'FT-DBL-031-32KG', // Matching SKU
                            'price' => 99.99,
                            'stock' => 8,
                        ],
                        'selectedVariants' => [
                            'Weight Specification' => [
                                'id' => 126,
                                'label' => 'Pair 32kg (2x16kg)',
                            ],
                        ],
                    ],
                ],
                'discountType' => 'percentage',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 99.99,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 99.99,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 99.99,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id']);

        tenancy()->initialize($tenant);
        $this->assertNull(PosSale::query()->where('sale_key', $saleId)->first());
        $this->assertSame(8, (int) $variant->fresh()->stock);
        $this->assertSame(24, (int) $item->fresh()->stock);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_pos_sale_enforces_uom_stock_control_across_multiple_cart_lines(): void
    {
        $tenant = $this->createTenant('pos-stock-uom-multi-line');
        tenancy()->initialize($tenant);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);

        $baseUnit = UnitOfMeasure::query()->create([
            'code' => 'EA',
            'name' => 'Each',
            'symbol' => 'ea',
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);

        $packUnit = UnitOfMeasure::query()->create([
            'code' => 'PACK3',
            'name' => 'Pack of 3',
            'symbol' => 'pk3',
            'conversion_factor_to_base' => 3,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);

        $uomGroup = UomGroup::query()->create([
            'code' => 'BENCH_UOM',
            'name' => 'Bench UOM Group',
            'base_unit_id' => $baseUnit->id,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $baseUnit->id,
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $packUnit->id,
            'conversion_factor_to_base' => 3,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'FT-BCH-033',
            'name' => 'Multi-Function Bench Press Set',
            'item_type' => 'uom',
            'price' => 100.00,
            'stock' => 6, // 6 base units in stock
            'stock_control' => true,
            'uom_group_id' => $uomGroup->id,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $clientToken = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        // 1. Selling 1 Pack of 3 (3 units) + 4 Each (4 units) = 7 base units. Should FAIL (only 6 in stock).
        $overResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => $clientToken,
            'sale_id' => $saleId,
            'reference' => 'INV-UOM-FAIL',
            'snapshot' => [
                'saleId' => $saleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'currency' => ['code' => 'USD'],
                        ],
                        'selectedUOM' => [
                            'id' => $packUnit->id,
                            'code' => 'PACK3',
                            'shortCode' => 'pk3',
                            'name' => 'Pack of 3',
                        ],
                        'quantity' => 1,
                        'unitPrice' => 300.00,
                    ],
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'currency' => ['code' => 'USD'],
                        ],
                        'selectedUOM' => [
                            'id' => $baseUnit->id,
                            'code' => 'EA',
                            'shortCode' => 'ea',
                            'name' => 'Each',
                        ],
                        'quantity' => 4,
                        'unitPrice' => 100.00,
                    ],
                ],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 700.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 700.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 700.00,
                ],
            ],
        ]);

        $overResponse->assertStatus(422)
            ->assertJsonValidationErrors('snapshot.cart');

        // 2. Selling 1 Pack of 3 (3 units) + 3 Each (3 units) = 6 base units. Exactly matches stock of 6. Should SUCCEED!
        $validSaleId = (string) Str::uuid();
        $validResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/pos/sales/complete-cash', [
            'client_token' => (string) Str::uuid(),
            'sale_id' => $validSaleId,
            'reference' => 'INV-UOM-PASS',
            'snapshot' => [
                'saleId' => $validSaleId,
                'cart' => [
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'currency' => ['code' => 'USD'],
                        ],
                        'selectedUOM' => [
                            'id' => $packUnit->id,
                            'code' => 'PACK3',
                            'shortCode' => 'pk3',
                            'name' => 'Pack of 3',
                        ],
                        'quantity' => 1,
                        'unitPrice' => 300.00,
                    ],
                    [
                        'product' => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'currency' => ['code' => 'USD'],
                        ],
                        'selectedUOM' => [
                            'id' => $baseUnit->id,
                            'code' => 'EA',
                            'shortCode' => 'ea',
                            'name' => 'Each',
                        ],
                        'quantity' => 3,
                        'unitPrice' => 100.00,
                    ],
                ],
                'orderType' => 'Takeaway',
                'discountType' => 'fixed',
                'discountValue' => 0,
                'taxPercent' => 0,
                'serviceFee' => 0,
            ],
            'totals' => [
                'sub_total' => 600.00,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_fee' => 0,
                'total_payable' => 600.00,
            ],
            'tenders' => [
                [
                    'currency_code' => 'USD',
                    'amount' => 600.00,
                ],
            ],
        ]);

        $validResponse->assertOk()
            ->assertJsonPath('success', true);

        // Run queue deduction job
        tenancy()->initialize($tenant);
        $savedSale = PosSale::query()->where('sale_key', $validSaleId)->first();
        tenancy()->end();
        DB::purge('tenant');

        $this->assertNotNull($savedSale);
        $job = new DeductPosSaleStockJob($savedSale->id, $tenant->id);
        $job->handle();

        // Check that stock was deducted from 6 to 0
        tenancy()->initialize($tenant);
        $this->assertEquals(0, $item->fresh()->stock);
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
