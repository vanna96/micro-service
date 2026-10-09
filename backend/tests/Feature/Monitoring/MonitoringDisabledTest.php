<?php

namespace Tests\Feature\Monitoring;

use App\Monitoring\Services\MetricsRepository;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringDisabledTest extends TestCase
{
    public function test_disabled_monitoring_does_not_contact_prometheus(): void
    {
        config()->set('monitoring.enabled', false);
        config()->set('monitoring.prometheus.enabled', true);
        Http::fake();

        $dashboard = app(MonitoringService::class)->dashboard();

        $this->assertFalse($dashboard['monitoringEnabled']);
        $this->assertSame('disabled', $dashboard['statuses'][0]['state']);
        Http::assertNothingSent();
    }

    public function test_internal_metrics_are_absent_when_monitoring_is_disabled(): void
    {
        config()->set('monitoring.enabled', false);

        $this->get('/internal/metrics')->assertNotFound();
    }

    public function test_monitoring_dashboard_requires_authentication(): void
    {
        $this->get('/admin/monitoring')->assertRedirect('/');
        $this->get('/admin/monitoring/live')->assertRedirect('/');
        $this->put('/admin/monitoring/live-preference', ['enabled' => true])->assertRedirect('/');
        $this->get('/pulse')->assertForbidden();
    }

    public function test_enabled_metrics_endpoint_exports_prometheus_and_honors_component_switches(): void
    {
        $path = storage_path('framework/testing/monitoring-endpoint.json');
        File::delete($path);
        config()->set('monitoring.metrics_file', $path);
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.components.laravel', true);
        config()->set('monitoring.prometheus.enabled', true);
        try {
            app(MetricsRepository::class)->recordQueueJob('default', 'processed');
            $this->get('/internal/metrics')->assertOk()
                ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
                ->assertSee('laravel_queue_jobs_total', false);
            $this->assertArrayNotHasKey('laravel_http_requests_total', app(MetricsRepository::class)->snapshot()['metrics']);
            config()->set('monitoring.components.laravel', false);
            $this->get('/internal/metrics')->assertNotFound();
            config()->set('monitoring.components.laravel', true);
            config()->set('monitoring.prometheus.enabled', false);
            $this->get('/internal/metrics')->assertNotFound();
        } finally {
            File::delete($path);
        }
    }
}
