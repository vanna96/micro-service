<?php

namespace Tests\Feature;

use App\Models\AdminNotificationRead;
use App\Models\PosSale;
use App\Models\SecurityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AdminNotificationRead::truncate();
    }

    public function test_authenticated_admin_can_mark_notification_as_read(): void
    {
        $admin = User::first() ?? User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.notifications.mark-read'), [
                'key' => 'threat_999',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'threat_999',
            ]);

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => 'threat_999',
        ], 'central');
    }

    public function test_authenticated_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = User::first() ?? User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.notifications.mark-all-read'), [
                'keys' => ['threat_101', 'pos_sale_RechnaDB_202'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'marked' => 2,
            ]);

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => 'threat_101',
        ], 'central');

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => 'pos_sale_RechnaDB_202',
        ], 'central');
    }

    public function test_opening_notification_marks_as_read_and_switches_tenant_context(): void
    {
        $admin = User::first() ?? User::factory()->create();
        $tenant = Tenant::first();

        $url = route('admin.notifications.open', [
            'key' => 'pos_sale_' . $tenant->id . '_55',
            'tenant_id' => $tenant->id,
            'url' => route('admin.reports.sales'),
        ]);

        $response = $this->actingAs($admin)->get($url);

        $response->assertRedirect(route('admin.reports.sales'));
        $this->assertEquals($tenant->id, session('admin_selected_tenant_id'));

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => 'pos_sale_' . $tenant->id . '_55',
        ], 'central');
    }

    public function test_read_notifications_are_hidden_from_layout_view(): void
    {
        $admin = User::first() ?? User::factory()->create();

        // Create a threat log
        $threat = SecurityLog::create([
            'incident_id' => 'SEC-NOTI-TEST',
            'ip_address' => '1.2.3.4',
            'threat_type' => 'sql_injection',
            'severity' => 'critical',
            'store_name' => 'Notification Test Store',
            'request_method' => 'GET',
            'request_url' => 'http://localhost/test-probe',
        ]);

        $key = 'threat_' . $threat->id;

        // Verify it appears initially
        $this->actingAs($admin);
        $viewInitial = view('layouts.app')->render();
        $this->assertStringContainsString($key, $viewInitial);

        // Mark it as read
        AdminNotificationRead::markKeyAsRead($admin->id, $key);

        // Verify it is now hidden
        $viewAfterRead = view('layouts.app')->render();
        $this->assertStringNotContainsString($key, $viewAfterRead);

        $threat->delete();
    }

    public function test_admin_can_view_notifications_center_page(): void
    {
        $admin = User::first() ?? User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.notifications.index'));

        $response->assertOk()
            ->assertViewIs('admin.notifications.index')
            ->assertSee('Notifications Center')
            ->assertSee('Total Tracked Alerts')
            ->assertSee('All Alerts');
    }

    public function test_mark_all_read_by_tab_marks_database_records(): void
    {
        $admin = User::first() ?? User::factory()->create();

        // 1. Mark threats tab
        $response = $this->actingAs($admin)->postJson(route('admin.notifications.mark-all-read'), [
            'tab' => 'threats',
        ]);
        $response->assertOk()->assertJson(['success' => true, 'tab' => 'threats']);

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => '__all_threats__',
        ], 'central');

        // 2. Mark orders tab
        $response = $this->actingAs($admin)->postJson(route('admin.notifications.mark-all-read'), [
            'tab' => 'orders',
        ]);
        $response->assertOk()->assertJson(['success' => true, 'tab' => 'orders']);

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => '__all_orders__',
        ], 'central');

        // 3. Mark all alerts tab (or empty body)
        $response = $this->actingAs($admin)->postJson(route('admin.notifications.mark-all-read'), []);
        $response->assertOk()->assertJson(['success' => true, 'tab' => 'all']);

        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'notification_key' => '__all__',
        ], 'central');
    }

    public function test_notifications_center_tabs_use_database(): void
    {
        $admin = User::first() ?? User::factory()->create();

        // Threats tab
        $threatResponse = $this->actingAs($admin)->get(route('admin.notifications.index', ['tab' => 'threats']));
        $threatResponse->assertOk()
            ->assertViewHas('currentTab', 'threats');

        // Orders tab
        $orderResponse = $this->actingAs($admin)->get(route('admin.notifications.index', ['tab' => 'orders']));
        $orderResponse->assertOk()
            ->assertViewHas('currentTab', 'orders');

        // Unread tab
        $unreadResponse = $this->actingAs($admin)->get(route('admin.notifications.index', ['tab' => 'unread']));
        $unreadResponse->assertOk()
            ->assertViewHas('currentTab', 'unread');
    }

    public function test_concurrent_duplicate_mark_as_read_does_not_throw_integrity_violation(): void
    {
        $admin = User::first() ?? User::factory()->create();
        $key = 'pos_sale_RechnaDB_85';

        // Simulate concurrent marking of the exact same key multiple times
        $read1 = AdminNotificationRead::markKeyAsRead($admin->id, $key);
        $read2 = AdminNotificationRead::markKeyAsRead($admin->id, $key);
        $read3 = AdminNotificationRead::markKeyAsRead($admin->id, $key);

        $this->assertEquals($key, $read1->notification_key);
        $this->assertEquals($key, $read2->notification_key);
        $this->assertEquals($key, $read3->notification_key);

        // There should be exactly one record in the database for this key
        $count = AdminNotificationRead::where('user_id', $admin->id)
            ->where('notification_key', $key)
            ->count();

        $this->assertEquals(1, $count);
    }
}

