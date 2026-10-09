<?php

namespace Tests\Unit\Monitoring;

use App\Monitoring\Drivers\PrometheusDriver;
use App\Monitoring\Services\MetricsRepository;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrometheusFailureTest extends TestCase
{
    public function test_unreachable_prometheus_is_reported_without_throwing(): void
    {
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.prometheus.enabled', true);
        config()->set('monitoring.prometheus.url', 'http://prometheus.invalid');
        config()->set('monitoring.components.node_exporter', true);
        Http::fake(fn () => throw new ConnectionException('unreachable'));

        $service = new MonitoringService(new PrometheusDriver, app(MetricsRepository::class));
        $dashboard = $service->dashboard();

        $this->assertTrue($dashboard['monitoringEnabled']);
        $this->assertSame('unavailable', $dashboard['statuses'][0]['state']);
        $this->assertSame('unavailable', $dashboard['statuses'][1]['state']);
        $this->assertSame('disabled', $dashboard['statuses'][5]['state']);
        $this->assertNull($dashboard['server']['cpu_percent']);
    }

    public function test_installed_pulse_is_connected_when_enabled_even_if_prometheus_is_unavailable(): void
    {
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.prometheus.enabled', true);
        config()->set('monitoring.prometheus.url', 'http://prometheus.invalid');
        config()->set('monitoring.components.pulse', true);
        Http::fake(fn () => throw new ConnectionException('unreachable'));

        $service = new MonitoringService(new PrometheusDriver, app(MetricsRepository::class));
        $dashboard = $service->dashboard();

        $this->assertSame('Laravel Pulse', $dashboard['statuses'][5]['name']);
        $this->assertSame('connected', $dashboard['statuses'][5]['state']);
    }

    public function test_prometheus_range_query_returns_chart_samples(): void
    {
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.prometheus.enabled', true);
        config()->set('monitoring.prometheus.url', 'http://prometheus.test');
        Http::fake([
            'prometheus.test/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'result' => [[
                        'metric' => [],
                        'values' => [[1000, '25.5'], [1060, '31.25']],
                    ]],
                ],
            ]),
        ]);

        $result = (new PrometheusDriver)->queryRange('test_metric', 1000, 1060, 60);

        $this->assertCount(1, $result);
        $this->assertSame('31.25', $result[0]['values'][1][1]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v1/query_range')
            && $request['step'] === 60);
    }
}
