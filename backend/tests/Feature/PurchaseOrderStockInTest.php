<?php

namespace Tests\Feature;

use App\Events\PosStockUpdatedEvent;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PurchaseOrder;
use App\Models\RateIndexValue;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseOrderStockInTest extends TestCase
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

    public function test_admin_can_create_purchase_order_with_items_and_vendor(): void
    {
        $tenant = $this->createSqliteTenant('po-create-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $vendor = Customer::query()->create([
            'code' => 'VEND-001',
            'type' => Customer::TYPE_VENDOR,
            'name' => 'Acme Supplies Co.',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'PUR-ITEM-1',
            'name' => 'Arabica Coffee Beans',
            'price' => 12.50,
            'stock' => 20,
            'status' => 'Active',
            'purchase' => true,
            'sale' => false,
            'stock_control' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.store'), [
                'po_number' => 'PO-TEST-0001',
                'vendor_id' => $vendor->id,
                'order_date' => now()->toDateString(),
                'status' => 'Draft',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 10,
                        'unit_cost' => 8.00,
                    ],
                ],
            ]);

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $order = PurchaseOrder::query()->where('po_number', 'PO-TEST-0001')->first();

        $this->assertNotNull($order);
        $this->assertSame('Draft', $order->status);
        $this->assertSame($vendor->id, $order->vendor_id);
        $this->assertEquals(80.0, (float) $order->subtotal);
        $this->assertEquals(80.0, (float) $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertNull($order->stock_received_at);

        // Initial stock should remain unchanged in Draft
        $this->assertSame(20, (int) $item->fresh()->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_stock_in_increments_stock_when_stock_control_is_on(): void
    {
        $tenant = $this->createSqliteTenant('po-stock-on-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'TRACKED-ITEM',
            'name' => 'Matcha Powder',
            'price' => 15.00,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => true,
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-STOCK-001',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        $order->items()->create([
            'item_id' => $item->id,
            'quantity' => 25,
            'unit_cost' => 10.00,
            'subtotal' => 250.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Trigger receive action via controller
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.receive', ['purchase_order' => $order->id]));

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $order->refresh();
        $item->refresh();

        $this->assertSame('Received', $order->status);
        $this->assertNotNull($order->stock_received_at);
        // 10 + 25 = 35
        $this->assertSame(35, (int) $item->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_stock_in_does_not_increment_stock_when_stock_control_is_off(): void
    {
        $tenant = $this->createSqliteTenant('po-stock-off-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'UNTRACKED-ITEM',
            'name' => 'Cleaning Service',
            'price' => 50.00,
            'stock' => 0,
            'status' => 'Active',
            'purchase' => true,
            'sale' => false,
            'stock_control' => false,
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-STOCK-002',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        $order->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 40.00,
            'subtotal' => 400.00,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.receive', ['purchase_order' => $order->id]));

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $order->refresh();
        $item->refresh();

        $this->assertSame('Received', $order->status);
        $this->assertNotNull($order->stock_received_at);
        // Stock control is OFF, so stock remains 0
        $this->assertSame(0, (int) $item->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_stock_in_multiplies_quantity_by_uom_conversion_factor(): void
    {
        $tenant = $this->createSqliteTenant('po-uom-' . uniqid());

        tenancy()->initialize($tenant);

        $baseUnit = UnitOfMeasure::query()->create([
            'code' => 'CAN',
            'name' => 'Can',
            'symbol' => 'can',
            'status' => 'Active',
        ]);

        $boxUnit = UnitOfMeasure::query()->create([
            'code' => 'BOX',
            'name' => 'Box of 12',
            'symbol' => 'box',
            'status' => 'Active',
        ]);

        $uomGroup = UomGroup::query()->create([
            'code' => 'BEV-GRP',
            'name' => 'Beverage Group',
            'base_unit_id' => $baseUnit->id,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $baseUnit->id,
            'conversion_factor_to_base' => 1.0,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);

        UomGroupUnit::query()->create([
            'uom_group_id' => $uomGroup->id,
            'unit_of_measure_id' => $boxUnit->id,
            'conversion_factor_to_base' => 12.0,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'sku' => 'SODA-CAN',
            'name' => 'Sparkling Soda',
            'price' => 1.50,
            'stock' => 5,
            'uom_group_id' => $uomGroup->id,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => true,
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-UOM-001',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        // Purchasing 3 Boxes (3 * 12 = 36 cans)
        $order->items()->create([
            'item_id' => $item->id,
            'uom_id' => $boxUnit->id,
            'quantity' => 3,
            'unit_cost' => 12.00,
            'subtotal' => 36.00,
        ]);

        $service = app(PurchaseOrderService::class);
        $service->receive($order, $tenant->id);

        $item->refresh();
        // Initial 5 + (3 * 12) = 41 cans
        $this->assertSame(41, (int) $item->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_stock_in_increments_variant_stock_when_variant_is_specified(): void
    {
        $tenant = $this->createSqliteTenant('po-variant-' . uniqid());

        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'TSHIRT-BASE',
            'name' => 'Cotton T-Shirt',
            'price' => 20.00,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => true,
        ]);

        $variant = ItemVariant::query()->create([
            'item_id' => $item->id,
            'sku' => 'TSHIRT-BLACK-L',
            'stock' => 4,
            'status' => 'Active',
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-VAR-001',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        $order->items()->create([
            'item_id' => $item->id,
            'item_variant_id' => $variant->id,
            'quantity' => 6,
            'unit_cost' => 10.00,
            'subtotal' => 60.00,
        ]);

        $service = app(PurchaseOrderService::class);
        $service->receive($order, $tenant->id);

        $item->refresh();
        $variant->refresh();

        // Base item stock: 10 + 6 = 16
        $this->assertSame(16, (int) $item->stock);
        // Variant stock: 4 + 6 = 10
        $this->assertSame(10, (int) $variant->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_stock_in_is_idempotent_and_cannot_be_received_twice(): void
    {
        $tenant = $this->createSqliteTenant('po-idemp-' . uniqid());

        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'ONCE-ITEM',
            'name' => 'Single Receive Item',
            'price' => 10.00,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'stock_control' => true,
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-IDEMP-001',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        $order->items()->create([
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 5.00,
            'subtotal' => 25.00,
        ]);

        $service = app(PurchaseOrderService::class);
        $service->receive($order, $tenant->id);

        $item->refresh();
        $this->assertSame(15, (int) $item->stock);

        // Second receive should fail
        $this->expectException(\DomainException::class);
        $service->receive($order, $tenant->id);

        // Stock should still be 15
        $item->refresh();
        $this->assertSame(15, (int) $item->stock);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_pos_stock_updated_event_dispatched_on_stock_in(): void
    {
        Event::fake([PosStockUpdatedEvent::class]);

        $tenant = $this->createSqliteTenant('po-event-' . uniqid());

        tenancy()->initialize($tenant);

        $item = Item::query()->create([
            'sku' => 'EVENT-ITEM',
            'name' => 'Broadcast Item',
            'price' => 8.00,
            'stock' => 0,
            'status' => 'Active',
            'purchase' => true,
            'stock_control' => true,
        ]);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-EVENT-001',
            'order_date' => now()->toDateString(),
            'status' => 'Ordered',
        ]);

        $order->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 4.00,
            'subtotal' => 40.00,
        ]);

        $service = app(PurchaseOrderService::class);
        $service->receive($order, $tenant->id);

        Event::assertDispatched(PosStockUpdatedEvent::class, function (PosStockUpdatedEvent $event) use ($item, $tenant) {
            return in_array($item->id, $event->itemIds, true)
                && $event->tenantId === $tenant->id;
        });

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_cannot_delete_received_purchase_order(): void
    {
        $tenant = $this->createSqliteTenant('po-del-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-DEL-001',
            'order_date' => now()->toDateString(),
            'status' => 'Received',
            'stock_received_at' => now(),
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.purchase-orders.destroy', ['purchase_order' => $order->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        tenancy()->initialize($tenant);
        $this->assertNotNull(PurchaseOrder::query()->find($order->id));

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_cannot_create_purchase_order_for_item_without_stock_control(): void
    {
        $tenant = $this->createSqliteTenant('po-no-stock-ctrl-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $vendor = Customer::query()->create([
            'code' => 'VEND-002',
            'name' => 'Supplier Co',
            'type' => 'vendor',
            'status' => 'Active',
        ]);

        $itemNoStockCtrl = Item::query()->create([
            'sku' => 'SERVICE-ITEM',
            'name' => 'Consulting Labor',
            'price' => 100.00,
            'stock' => 0,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => false,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.store'), [
                'po_number' => 'PO-INVALID-ITEM',
                'vendor_id' => $vendor->id,
                'order_date' => now()->toDateString(),
                'status' => 'Draft',
                'items' => [
                    [
                        'item_id' => $itemNoStockCtrl->id,
                        'quantity' => 5,
                        'unit_cost' => 50.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['item_id']);

        tenancy()->initialize($tenant);
        $this->assertNull(PurchaseOrder::query()->where('po_number', 'PO-INVALID-ITEM')->first());
    }

    public function test_search_items_endpoint_filters_and_lazy_loads_stock_controlled_items(): void
    {
        $tenant = $this->createSqliteTenant('po-search-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $matchItem = Item::query()->create([
            'name' => 'Searchable Widget Deluxe',
            'sku' => 'SWD-001',
            'price' => 25.00,
            'stock' => 50,
            'status' => 'Active',
            'stock_control' => true,
            'purchase' => true,
            'sale' => true,
        ]);

        $nonStockControlItem = Item::query()->create([
            'name' => 'Searchable Non Stock Widget',
            'sku' => 'NSW-002',
            'price' => 15.00,
            'stock' => 0,
            'status' => 'Active',
            'stock_control' => false,
            'purchase' => true,
            'sale' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->getJson(route('admin.purchase-orders.search-items', ['q' => 'Searchable']));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame($matchItem->id, $items[0]['id']);
        $this->assertSame('Searchable Widget Deluxe', $items[0]['name']);
    }

    public function test_admin_can_cancel_and_manage_purchase_order_lifecycle(): void
    {
        $tenant = $this->createSqliteTenant('po-lifecycle-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $order = PurchaseOrder::query()->create([
            'po_number' => 'PO-LIFE-001',
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        // 1. Mark as Ordered
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.mark-ordered', ['purchase_order' => $order->id]));
        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $order->fresh()->status);
        tenancy()->end();
        DB::purge('tenant');

        // 2. Cancel Order
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.cancel', ['purchase_order' => $order->id]), [
                'reason' => 'Supplier out of stock',
            ]);
        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertStringContainsString('Supplier out of stock', $order->fresh()->notes);
        tenancy()->end();
        DB::purge('tenant');

        // 3. Reopen as Draft
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.mark-draft', ['purchase_order' => $order->id]));
        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->fresh()->status);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_search_items_includes_currency_metadata(): void
    {
        $tenant = $this->createSqliteTenant('po-curr-meta-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $khr = Currency::query()->create([
            'code' => 'KHR',
            'name' => 'Khmer Riel',
            'symbol' => '៛',
            'decimal_places' => 0,
            'status' => 'Active',
            'sort_order' => 1,
        ]);

        $item = Item::query()->create([
            'sku' => 'KHR-ITEM-001',
            'name' => 'Local Snack Pack',
            'price' => 4100,
            'currency_id' => $khr->id,
            'stock' => 50,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->getJson(route('admin.purchase-orders.search-items', ['q' => 'Local Snack']));

        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertSame($khr->id, $items[0]['currency_id']);
        $this->assertSame('KHR', $items[0]['currency_code']);
        $this->assertSame('៛', $items[0]['currency_symbol']);
        $this->assertSame(0, $items[0]['currency_decimals']);
        $this->assertEquals(4100, $items[0]['cost_price']);
    }

    public function test_purchase_order_stores_and_calculates_totals_with_currency(): void
    {
        $tenant = $this->createSqliteTenant('po-curr-calc-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $usd = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'status' => 'Active',
            'sort_order' => 0,
        ]);

        $khr = Currency::query()->create([
            'code' => 'KHR',
            'name' => 'Khmer Riel',
            'symbol' => '៛',
            'decimal_places' => 0,
            'status' => 'Active',
            'sort_order' => 1,
        ]);

        $item = Item::query()->create([
            'sku' => 'KHR-ITEM-002',
            'name' => 'Bottled Spring Water',
            'price' => 4100,
            'currency_id' => $khr->id,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'sale' => true,
            'stock_control' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Create PO in KHR with item's native unit cost (4,100 KHR, without dollar conversion)
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.store'), [
                'po_number' => 'PO-CURR-001',
                'order_date' => now()->toDateString(),
                'status' => 'Draft',
                'currency_id' => $khr->id,
                'currency_code' => 'KHR',
                'tax_amount' => 500,
                'shipping_amount' => 1000,
                'discount_amount' => 200,
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 5,
                        'unit_cost' => 4100, // In item's own currency (KHR)
                    ],
                ],
            ]);

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $order = PurchaseOrder::query()->where('po_number', 'PO-CURR-001')->with('items')->first();

        $this->assertNotNull($order);
        $this->assertSame($khr->id, $order->currency_id);
        $this->assertSame('KHR', $order->currency_code);
        $this->assertEquals(20500.0, $order->subtotal); // 5 * 4,100
        $this->assertEquals(21800.0, $order->total_amount); // 20,500 + 500 + 1,000 - 200
        $this->assertEquals(4100.0, $order->items->first()->unit_cost);
        $this->assertEquals(20500.0, $order->items->first()->subtotal);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_purchase_order_calculates_total_amount_based_on_header_currency_with_mixed_items(): void
    {
        $tenant = $this->createSqliteTenant('po-mixed-curr-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $usd = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'status' => 'Active']
        );
        $khr = Currency::query()->firstOrCreate(
            ['code' => 'KHR'],
            ['name' => 'Khmer Riel', 'symbol' => '៛', 'decimal_places' => 0, 'status' => 'Active']
        );

        // Configure exchange rate for current order date: 1 USD = 4100 KHR
        $orderDate = now();
        \App\Models\RateIndexValue::query()->create([
            'dataset_type' => 'exchange_rate',
            'year' => $orderDate->year,
            'month' => $orderDate->month,
            'day' => $orderDate->day,
            'currency_id' => $khr->id,
            'value' => 4100.0,
        ]);

        $itemKhr = Item::query()->create([
            'sku' => '001',
            'name' => 'Khmer Item 001',
            'price' => 4100,
            'currency_id' => $khr->id,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'stock_control' => true,
        ]);

        $itemUsd = Item::query()->create([
            'sku' => 'FT-DBL-031',
            'name' => 'Adjustable Dumbbell Set',
            'price' => 59.99,
            'currency_id' => $usd->id,
            'stock' => 5,
            'status' => 'Active',
            'purchase' => true,
            'stock_control' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Create PO in USD (header currency = USD) with mixed items
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.store'), [
                'po_number' => 'PO-MIXED-001',
                'order_date' => $orderDate->toDateString(),
                'status' => 'Draft',
                'currency_id' => $usd->id,
                'currency_code' => 'USD',
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'discount_amount' => 0,
                'items' => [
                    [
                        'item_id' => $itemKhr->id,
                        'quantity' => 1,
                        'unit_cost' => 4100, // Native KHR
                    ],
                    [
                        'item_id' => $itemUsd->id,
                        'quantity' => 1,
                        'unit_cost' => 59.99, // Native USD
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $order = PurchaseOrder::query()->where('po_number', 'PO-MIXED-001')->with('items.item')->first();

        $this->assertNotNull($order);
        $this->assertSame($usd->id, $order->currency_id);
        $this->assertSame('USD', $order->currency_code);

        // Item 1 is 4100 KHR = 1.00 USD
        // Item 2 is 59.99 USD = 59.99 USD
        // Subtotal and Total in Header Currency (USD) = 1.00 + 59.99 = 60.99
        $this->assertEquals(60.99, round((float) $order->subtotal, 2));
        $this->assertEquals(60.99, round((float) $order->total_amount, 2));

        // Line items retain their native unit cost and subtotal
        $khrLine = $order->items->firstWhere('item_id', $itemKhr->id);
        $this->assertEquals(4100.0, (float) $khrLine->unit_cost);
        $this->assertEquals(4100.0, (float) $khrLine->subtotal);

        $usdLine = $order->items->firstWhere('item_id', $itemUsd->id);
        $this->assertEquals(59.99, (float) $usdLine->unit_cost);
        $this->assertEquals(59.99, (float) $usdLine->subtotal);

        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_purchase_order_rejects_mixed_currencies_if_rate_is_not_configured_for_order_date(): void
    {
        $tenant = $this->createSqliteTenant('po-missing-rate-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $usd = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'status' => 'Active']
        );
        $khr = Currency::query()->firstOrCreate(
            ['code' => 'KHR'],
            ['name' => 'Khmer Riel', 'symbol' => '៛', 'decimal_places' => 0, 'status' => 'Active']
        );

        // Configure rate only for 2 days ago (NOT today)
        $twoDaysAgo = now()->subDays(2);
        \App\Models\RateIndexValue::query()->create([
            'dataset_type' => 'exchange_rate',
            'year' => $twoDaysAgo->year,
            'month' => $twoDaysAgo->month,
            'day' => $twoDaysAgo->day,
            'currency_id' => $khr->id,
            'value' => 4100.0,
        ]);

        $itemKhr = Item::query()->create([
            'sku' => '001',
            'name' => 'Khmer Item 001',
            'price' => 4100,
            'currency_id' => $khr->id,
            'stock' => 10,
            'status' => 'Active',
            'purchase' => true,
            'stock_control' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        // Attempting to create order for today must fail validation because today's rate is missing
        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.purchase-orders.store'), [
                'po_number' => 'PO-FAIL-001',
                'order_date' => now()->toDateString(),
                'status' => 'Draft',
                'currency_id' => $usd->id,
                'currency_code' => 'USD',
                'items' => [
                    [
                        'item_id' => $itemKhr->id,
                        'quantity' => 1,
                        'unit_cost' => 4100,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('currency_id');
        $this->assertStringContainsString('The exchange rate for KHR is not configured for', session('errors')->first('currency_id'));
        $this->assertStringContainsString('Exchange rates must be configured for the current day', session('errors')->first('currency_id'));
    }

    public function test_purchase_order_rates_endpoint_returns_configured_rates_for_date(): void
    {
        $tenant = $this->createSqliteTenant('po-rates-api-' . uniqid());
        $admin = $this->createAdminUser();
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $khr = Currency::query()->firstOrCreate(
            ['code' => 'KHR'],
            ['name' => 'Khmer Riel', 'symbol' => '៛', 'decimal_places' => 0, 'status' => 'Active']
        );

        $orderDate = now();
        \App\Models\RateIndexValue::query()->create([
            'dataset_type' => 'exchange_rate',
            'year' => $orderDate->year,
            'month' => $orderDate->month,
            'day' => $orderDate->day,
            'currency_id' => $khr->id,
            'value' => 4150.0,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.purchase-orders.rates', ['date' => $orderDate->toDateString()]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'rates' => [
                'date' => $orderDate->toDateString(),
                'is_today' => true,
                'by_code' => [
                    'KHR' => 4150.0,
                ],
            ],
        ]);
        $json = $response->json();
        $this->assertContains('KHR', $json['rates']['configured_codes']);
        $this->assertStringContainsString('4,150 KHR', $json['rates']['remarks']['KHR']);
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
