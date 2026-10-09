<?php

namespace Tests\Feature\Monitoring;

use App\Livewire\Admin\MonitoringDashboard;
use App\Models\MonitoringPreference;
use App\Models\User;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoringDashboardTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/monitoring-dashboard.sqlite');
        File::delete($this->databasePath);
        File::put($this->databasePath, '');
        $connection = [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        config()->set('database.default', 'central');
        config()->set('database.connections.central', $connection);
        config()->set('database.connections.mysql', $connection);
        config()->set('monitoring.enabled', false);
        config()->set('monitoring.refresh_seconds', 15);
        DB::purge('central');
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        File::delete($this->databasePath);
        parent::tearDown();
    }

    public function test_administrator_can_render_monitoring_dashboard(): void
    {
        $user = User::query()->create([
            'name' => 'Monitoring Admin',
            'username' => 'monitoring-admin',
            'email' => 'monitoring@example.test',
            'password' => Hash::make('secret'),
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->withSession(['auth_user_scope' => 'administrator'])
            ->get('/admin/monitoring')
            ->assertOk()
            ->assertSee('Monitoring Status')
            ->assertSee('Prometheus')
            ->assertSee('Docker Containers')
            ->assertSee('CPU temperature')
            ->assertSee('System performance')
            ->assertSee('<details id="graphs"', false)
            ->assertSee('<details id="server"', false)
            ->assertSee('<details id="operations"', false)
            ->assertSee('Historical graphs')
            ->assertSee('Monitoring &amp; service status', false)
            ->assertSee('Cron / scheduler')
            ->assertSee('Jobs &amp; Cron', false)
            ->assertSee('Scheduler / cron')
            ->assertSee('Scheduled tasks')
            ->assertSee('Recent cron runs')
            ->assertSee('Failed jobs total')
            ->assertSee('Refresh now')
            ->assertSee('Updates every 15 seconds only while Live')
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Monitoring is disabled.');

        $this->actingAs($user)
            ->withSession(['auth_user_scope' => 'administrator'])
            ->get('/admin/monitoring/live')
            ->assertStatus(409)
            ->assertJson(['live_enabled' => false]);

        $this->actingAs($user)
            ->withSession(['auth_user_scope' => 'administrator'])
            ->get('/admin/monitoring/live?once=1')
            ->assertOk()
            ->assertSee('Monitoring Status')
            ->assertSee('Monitoring is disabled.')
            ->assertDontSee('<html', false);

        $this->putJson('/admin/monitoring/live-preference', ['enabled' => true])
            ->assertOk()
            ->assertJson(['live_enabled' => true]);

        $this->assertDatabaseHas('monitoring_preferences', [
            'user_id' => $user->id,
            'live_enabled' => true,
        ], 'central');

        $this->get('/admin/monitoring')
            ->assertOk()
            ->assertSee('aria-pressed="false"', false);

        $this->get('/admin/monitoring/live')
            ->assertOk()
            ->assertSee('Monitoring Status');

        $this->putJson('/admin/monitoring/live-preference', ['enabled' => false])
            ->assertOk()
            ->assertJson(['live_enabled' => false]);

        $this->get('/admin/monitoring')
            ->assertOk()
            ->assertSee('aria-pressed="true"', false);

        $this->get('/pulse')
            ->assertOk();

        $this->withSession(['auth_user_scope' => 'tenant'])
            ->get('/pulse')
            ->assertForbidden();
    }

    public function test_monitoring_content_renders_sub_byte_rates_without_crashing(): void
    {
        $data = app(MonitoringService::class)->dashboard();
        $data['containers'] = [[
            'name' => 'quiet-container',
            'status' => 'running',
            'network_receive_bytes_per_second' => 0.25,
            'network_transmit_bytes_per_second' => 0.5,
            'disk_read_bytes_per_second' => 0.1,
            'disk_write_bytes_per_second' => 0.2,
        ]];

        $html = View::make('admin.monitoring._content', $data)->render();

        $this->assertStringContainsString('quiet-container', $html);
        $this->assertStringContainsString('0 B', $html);
        $this->assertStringContainsString('monitor-system-chart', $html);
        $this->assertStringContainsString('monitoring-chart-data', $html);
    }

    public function test_tenant_sessions_cannot_read_monitoring_or_change_preferences(): void
    {
        $user = User::query()->create([
            'name' => 'Tenant Viewer', 'username' => 'tenant-monitoring-viewer',
            'email' => 'tenant-monitoring@example.test', 'password' => Hash::make('secret'), 'status' => 'Active',
        ]);
        $this->actingAs($user)->withSession(['auth_user_scope' => 'tenant']);
        $this->get('/admin/monitoring')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/monitoring/live?once=1')->assertRedirect(route('admin.dashboard'));
        $this->putJson('/admin/monitoring/live-preference', ['enabled' => true])->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseCount('monitoring_preferences', 0, 'central');
        $this->get('/pulse')->assertForbidden();
    }

    public function test_invalid_preferences_are_rejected_and_refresh_interval_is_bounded(): void
    {
        $user = User::query()->create([
            'name' => 'Monitoring Admin', 'username' => 'monitoring-preferences-admin',
            'email' => 'monitoring-preferences@example.test', 'password' => Hash::make('secret'), 'status' => 'Active',
        ]);
        $this->actingAs($user)->withSession(['auth_user_scope' => 'administrator']);
        $this->putJson('/admin/monitoring/live-preference', [])->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $this->putJson('/admin/monitoring/live-preference', ['enabled' => 'yes'])->assertUnprocessable();
        $this->assertDatabaseCount('monitoring_preferences', 0, 'central');
        config()->set('monitoring.refresh_seconds', 0);
        $this->get('/admin/monitoring')->assertOk()->assertSee('Updates every 5 seconds');
        config()->set('monitoring.refresh_seconds', 1000);
        $this->get('/admin/monitoring')->assertOk()->assertSee('Updates every 300 seconds');
    }

    public function test_each_administrator_has_an_independent_live_preference(): void
    {
        $users = [];
        foreach ([1, 2] as $index) {
            $users[] = User::query()->create([
                'name' => 'Admin '.$index, 'username' => 'monitoring-admin-'.$index,
                'email' => 'monitoring-admin-'.$index.'@example.test', 'password' => Hash::make('secret'), 'status' => 'Active',
            ]);
        }
        $this->actingAs($users[0])->withSession(['auth_user_scope' => 'administrator'])
            ->putJson('/admin/monitoring/live-preference', ['enabled' => true])->assertOk();
        $this->actingAs($users[1])->get('/admin/monitoring/live')->assertStatus(409);
        $this->actingAs($users[0])->get('/admin/monitoring/live')->assertOk();
    }

    public function test_enabled_dashboard_renders_every_section_and_chart_payload(): void
    {
        $user = User::query()->create([
            'name' => 'Browser Admin', 'username' => 'browser-admin',
            'email' => 'browser-admin@example.test', 'password' => Hash::make('secret'), 'status' => 'Active',
        ]);
        $data = app(MonitoringService::class)->dashboard();
        $data['monitoringEnabled'] = true;
        $data['server']['cpu_percent'] = 25.5;
        $data['server']['cpu_temperature_celsius'] = 48.0;
        $data['server']['ram_used_bytes'] = 6 * 1024 ** 3;
        $data['server']['ram_total_bytes'] = 16 * 1024 ** 3;
        $data['server']['disk_used_bytes'] = 80 * 1024 ** 3;
        $data['server']['disk_total_bytes'] = 256 * 1024 ** 3;
        $data['api']['requests_per_minute'] = 42;
        $data['api']['average_duration_ms'] = 18.5;
        $data['api']['error_rate_percent'] = 0.0;
        $data['queues']['pending'] = 2;
        $data['queues']['failed_total'] = 0;
        $data['containers'] = [['name' => 'test-container', 'status' => 'running']];
        $data['scheduler']['state'] = 'healthy';
        $data['queues']['unavailable_databases'] = 1;
        foreach ($data['charts'] as $metric => &$chart) {
            $chart = [];
            for ($minute = 0; $minute < 60; $minute++) {
                $value = match ($metric) {
                    'ram_percent' => 37.5 + sin($minute / 12) * 3,
                    'cpu_temperature_celsius' => 48 + sin($minute / 8) * 4,
                    'api_requests_per_minute' => 42 + sin($minute / 5) * 20,
                    'api_average_duration_ms' => 18.5 + sin($minute / 9) * 8,
                    'network_receive_bytes_per_second' => 400000 + sin($minute / 6) * 200000,
                    'network_transmit_bytes_per_second' => 140000 + sin($minute / 8) * 90000,
                    'queue_processed_per_minute' => $minute % 8 === 0 ? 8 : 0,
                    'queue_failed_per_minute', 'http_4xx_per_minute', 'http_5xx_per_minute' => 0,
                    default => 25.5 + sin($minute / 6) * 12,
                };
                $chart[] = ['x' => (time() - (59 - $minute) * 60) * 1000, 'y' => round($value, 3)];
            }
        }
        unset($chart);
        $emptyData = app(MonitoringService::class)->dashboard();
        $this->mock(MonitoringService::class)->shouldReceive('dashboard')->andReturnUsing(function () use (&$data) {
            return $data;
        });
        $this->actingAs($user)->withSession(['auth_user_scope' => 'administrator']);
        $response = $this->get('/admin/monitoring')->assertOk()
            ->assertSee('test-container')->assertSee('25.5%')->assertSee('48.0 °C')
            ->assertSee('Queue totals are incomplete')->assertSee('monitoring-chart-data')
            ->assertSee('Requests &amp; Response Time', false)->assertSee('Disk Storage')->assertSee('Memory Usage');
        $this->get('/admin/monitoring/live?once=1')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        $this->assertSame(['liveEnabled', 'refreshSeconds'], array_keys(json_decode($snapshot, true)['data']));
        $data['server']['cpu_percent'] = 31.5;
        $data['server']['ram_used_bytes'] = 8 * 1024 ** 3;
        $data['queues']['pending'] = 5;
        foreach ($data['charts']['cpu_percent'] as &$point) {
            $point['y'] += 6;
        }
        unset($point);
        // Separate HTTP requests must not share Livewire's per-request middleware cache.
        app('livewire')->flushState();
        $refresh = $this->postJson(app('livewire')->getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['method' => 'refreshDashboard', 'params' => [true]]],
        ]]], ['X-Livewire' => 'true'])->assertOk()->assertJsonPath('components.0.effects.returns.0.refreshed', true);
        $this->assertStringContainsString('31.5%', $refresh->json('components.0.effects.html'));
        app('livewire')->flushState();
        $data = $emptyData;
        $empty = $this->postJson(app('livewire')->getUpdateUri(), ['components' => [[
            'snapshot' => $refresh->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['method' => 'refreshDashboard', 'params' => [true]]],
        ]]], ['X-Livewire' => 'true'])->assertOk();
        app('livewire')->flushState();
        $updatePayload = ['components' => [[
            'snapshot' => $empty->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['method' => 'setLiveEnabled', 'params' => [true]]],
        ]]];
        $this->withSession(['auth_user_scope' => 'tenant'])
            ->postJson(app('livewire')->getUpdateUri(), $updatePayload, ['X-Livewire' => 'true'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseCount('monitoring_preferences', 0, 'central');
        app('livewire')->flushState();
        $this->withSession(['auth_user_scope' => 'administrator'])
            ->postJson('https://tenant.example.test'.app('livewire')->getUpdateUri(), $updatePayload, ['X-Livewire' => 'true'])
            ->assertNotFound();
        if ($directory = getenv('MONITORING_BROWSER_FIXTURES')) {
            File::ensureDirectoryExists($directory);
            File::put($directory.'/index.html', $response->getContent());
            File::put($directory.'/refresh.json', $refresh->getContent());
            File::put($directory.'/empty-refresh.json', $empty->getContent());
        }
    }

    public function test_livewire_skips_metric_queries_while_paused_and_refreshes_when_requested(): void
    {
        $user = $this->livewireAdministrator();
        $data = app(MonitoringService::class)->dashboard();
        $this->mock(MonitoringService::class)->shouldReceive('dashboard')->twice()->andReturn($data);

        $component = Livewire::actingAs($user)->test(MonitoringDashboard::class)
            ->assertSet('liveEnabled', false)->assertSet('refreshSeconds', 15);
        $component->call('refreshDashboard')->assertReturned(['liveEnabled' => false, 'refreshed' => false]);
        $this->assertArrayNotHasKey('html', $component->effects);
        $component->call('refreshDashboard', true)->assertSee('Monitoring Status');
        $this->assertArrayHasKey('html', $component->effects);
    }

    public function test_livewire_preferences_are_persisted_per_administrator_and_server_pause_is_respected(): void
    {
        $user = $this->livewireAdministrator();
        $component = Livewire::actingAs($user)->test(MonitoringDashboard::class);
        $component->call('setLiveEnabled', true)->assertSet('liveEnabled', true);
        $this->assertDatabaseHas('monitoring_preferences', ['user_id' => $user->id, 'live_enabled' => true], 'central');
        Livewire::actingAs($user)->test(MonitoringDashboard::class)->assertSet('liveEnabled', true);
        MonitoringPreference::query()->where('user_id', $user->id)->update(['live_enabled' => false]);
        $component->call('refreshDashboard')->assertReturned(['liveEnabled' => false, 'refreshed' => false]);
        $this->assertArrayNotHasKey('html', $component->effects);
    }

    public function test_livewire_rechecks_administrator_access_on_updates(): void
    {
        $user = $this->livewireAdministrator();
        $component = Livewire::actingAs($user)->test(MonitoringDashboard::class);
        session()->put('auth_user_scope', 'tenant');
        $component->call('refreshDashboard', true)->assertForbidden();
        $this->assertDatabaseCount('monitoring_preferences', 0, 'central');
    }

    public function test_livewire_cannot_mount_for_guests_or_tenant_sessions(): void
    {
        Livewire::test(MonitoringDashboard::class)->assertForbidden();
        $user = $this->livewireAdministrator();
        session()->put('auth_user_scope', 'tenant');
        Livewire::actingAs($user)->test(MonitoringDashboard::class)->assertForbidden();
    }

    public function test_livewire_preferences_cannot_be_changed_through_property_updates(): void
    {
        $user = $this->livewireAdministrator();
        $component = Livewire::actingAs($user)->test(MonitoringDashboard::class);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('liveEnabled', true);
    }

    private function livewireAdministrator(): User
    {
        session()->put('auth_user_scope', 'administrator');

        return User::query()->create([
            'name' => 'Livewire Admin', 'username' => 'livewire-admin',
            'email' => 'livewire-admin@example.test', 'password' => Hash::make('secret'), 'status' => 'Active',
        ]);
    }
}
