<?php

namespace Tests\Unit\Monitoring;

use App\Monitoring\Contracts\MonitoringDriver;
use App\Monitoring\Exceptions\MonitoringUnavailableException;
use App\Monitoring\Services\MetricsRepository;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MonitoringServiceTest extends TestCase
{
    private string $metricsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsFile = storage_path('framework/testing/monitoring-service.json');
        File::delete($this->metricsFile);
        config()->set('database.connections.central', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('central');
        config()->set('tenancy.database.central_connection', 'central');
        config()->set('pulse.storage.database.connection', 'central');
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.prometheus.enabled', true);
        config()->set('monitoring.metrics_file', $this->metricsFile);
        config()->set('monitoring.components', ['node_exporter' => true, 'cadvisor' => true, 'caddy' => true, 'laravel' => true, 'pulse' => false]);
    }

    protected function tearDown(): void
    {
        DB::purge('central');
        File::delete($this->metricsFile);
        parent::tearDown();
    }

    public function test_caddy_uses_the_public_outer_handler_for_all_http_readings(): void
    {
        $driver = $this->driver(fn ($query) => str_contains($query, 'caddy_') && str_contains($query, 'handler="encode"')
            ? [['metric' => ['code' => '200'], 'value' => [time(), '2']]] : []);
        $http = (new MonitoringService($driver, app(MetricsRepository::class)))->dashboard()['http'];
        $this->assertSame(2.0, $http['total_requests']);
        $this->assertSame(2.0, $http['requests_per_second']);
        $this->assertSame(2000.0, $http['average_duration_ms']);
        $this->assertSame(2.0, $http['average_response_bytes']);
        $this->assertSame([200 => 2.0], $http['status_codes']);
    }

    public function test_one_bad_query_does_not_hide_other_readings_or_charts(): void
    {
        $driver = $this->driver(function ($query) {
            if (str_starts_with($query, '100 - ')) {
                throw new MonitoringUnavailableException('bad query');
            }

            return [['metric' => [], 'value' => [time(), '42']]];
        });
        $driver->shouldReceive('queryRange')->andReturnUsing(function ($query) {
            if (str_starts_with($query, '100 - ')) {
                throw new MonitoringUnavailableException('bad chart');
            }

            return [['values' => [[1000, '42']]]];
        });
        $data = (new MonitoringService($driver, app(MetricsRepository::class)))->dashboard();
        $this->assertNull($data['server']['cpu_percent']);
        $this->assertSame(42.0, $data['server']['ram_total_bytes']);
        $this->assertSame(42.0, $data['api']['requests_per_minute']);
        $this->assertSame(42.0, $data['queues']['processed_per_minute']);
        $this->assertSame([], $data['charts']['cpu_percent']);
        $this->assertSame([['x' => 1000000, 'y' => 42.0]], $data['charts']['ram_percent']);
    }

    public function test_connection_failure_stops_further_queries_and_does_not_invent_zero_rates(): void
    {
        $driver = $this->mock(MonitoringDriver::class);
        $driver->shouldReceive('isConfigured', 'isAvailable')->andReturn(true);
        $driver->shouldReceive('query')->once()->with('up')
            ->andThrow(new MonitoringUnavailableException('lost connection', 0, new ConnectionException('offline')));
        $driver->shouldNotReceive('queryRange');
        $data = (new MonitoringService($driver, app(MetricsRepository::class)))->dashboard();
        $this->assertNull($data['queues']['processed_per_minute']);
        $this->assertNull($data['queues']['failed_per_minute']);
        $this->assertSame('unavailable', $data['statuses'][4]['state']);
    }

    public function test_disabled_or_down_components_do_not_show_stale_values(): void
    {
        config()->set('monitoring.components', ['node_exporter' => false, 'cadvisor' => false, 'caddy' => false, 'laravel' => false, 'pulse' => false]);
        $driver = $this->mock(MonitoringDriver::class);
        $driver->shouldReceive('isConfigured', 'isAvailable')->andReturn(true);
        $driver->shouldReceive('query')->once()->with('up')->andReturn([]);
        $driver->shouldNotReceive('queryRange');
        $data = (new MonitoringService($driver, app(MetricsRepository::class)))->dashboard();
        $this->assertNull($data['server']['cpu_percent']);
        $this->assertNull($data['http']['total_requests']);
        $this->assertNull($data['api']['requests_per_minute']);
        $this->assertSame([], $data['containers']);
        $this->assertSame([], $data['charts']['ram_percent']);
    }

    public function test_pulse_reports_missing_storage_instead_of_connected(): void
    {
        config()->set('monitoring.components.pulse', true);
        $service = new MonitoringService($this->driver(), app(MetricsRepository::class));
        $this->assertSame('unavailable', $service->dashboard()['statuses'][5]['state']);
        foreach (['pulse_values', 'pulse_entries', 'pulse_aggregates'] as $table) {
            DB::connection('central')->getSchemaBuilder()->create($table, fn ($schema) => $schema->id());
        }
        $this->assertSame('connected', $service->dashboard()['statuses'][5]['state']);
    }

    public function test_scheduler_uses_local_heartbeat_when_prometheus_is_unavailable(): void
    {
        app(MetricsRepository::class)->recordSchedulerHeartbeat();
        $driver = $this->mock(MonitoringDriver::class);
        $driver->shouldReceive('isConfigured')->andReturn(true);
        $driver->shouldReceive('isAvailable')->andReturn(false);
        $driver->shouldNotReceive('query', 'queryRange');
        $data = (new MonitoringService($driver, app(MetricsRepository::class)))->dashboard();
        $this->assertSame('healthy', $data['scheduler']['state']);
        $this->assertLessThanOrEqual(2, $data['scheduler']['seconds_since_last_run']);
        $this->assertNotEmpty($data['scheduler']['tasks']);
    }

    private function driver(?callable $answer = null): MonitoringDriver
    {
        $driver = $this->mock(MonitoringDriver::class);
        $driver->shouldReceive('isConfigured', 'isAvailable')->andReturn(true);
        $driver->shouldReceive('query')->andReturnUsing(fn ($query) => $query === 'up'
            ? array_map(fn ($job) => ['metric' => ['job' => $job], 'value' => [time(), '1']], ['node-exporter', 'cadvisor', 'caddy', 'laravel'])
            : ($answer ? $answer($query) : []));
        $driver->shouldReceive('queryRange')->byDefault()->andReturn([]);

        return $driver;
    }
}
