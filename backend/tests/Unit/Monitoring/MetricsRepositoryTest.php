<?php

namespace Tests\Unit\Monitoring;

use App\Http\Middleware\TrackMonitoringRequest;
use App\Monitoring\Services\MetricsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MetricsRepositoryTest extends TestCase
{
    private string $metricsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metricsFile = storage_path('framework/testing/monitoring-metrics.json');
        File::delete($this->metricsFile);
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.components.laravel', true);
        config()->set('monitoring.metrics_file', $this->metricsFile);
        config()->set('monitoring.slow_query_ms', 1);
    }

    protected function tearDown(): void
    {
        File::delete($this->metricsFile);
        parent::tearDown();
    }

    public function test_it_exports_aggregated_metrics_without_sensitive_sql_literals(): void
    {
        $metrics = app(MetricsRepository::class);
        $metrics->beginRequest();
        $metrics->recordRequest('GET', 'api.orders.show', 200, 0.125, 1024);
        $metrics->recordSlowQuery('central', "select * from users where token = 'very-secret-token'", 25);
        $metrics->recordSchedulerHeartbeat();

        $output = $metrics->renderPrometheus();

        $this->assertStringContainsString('laravel_http_requests_total', $output);
        $this->assertStringContainsString('route="api.orders.show"', $output);
        $this->assertStringContainsString('laravel_slow_sql_queries_total', $output);
        $this->assertStringContainsString('laravel_scheduler_last_run_timestamp_seconds', $output);
        $this->assertStringNotContainsString('very-secret-token', json_encode($metrics->snapshot()));
    }

    public function test_series_limit_preserves_new_dashboard_counters_and_heartbeats(): void
    {
        config()->set('monitoring.max_series', 50);
        $metrics = app(MetricsRepository::class);
        for ($i = 0; $i < 10; $i++) {
            $metrics->beginRequest();
            $metrics->recordRequest('GET', 'route.'.$i, 200, 0.001, 1024);
        }
        $this->assertSame(50, array_sum(array_map('count', $metrics->snapshot()['metrics'])));
        $metrics->recordQueueJob('default', 'failed', 0.25);
        $metrics->recordScheduledTask('test-task', 'completed', 0.1);
        $metrics->recordSchedulerHeartbeat();
        $metrics->recordException(new \RuntimeException('test'));
        $output = $metrics->renderPrometheus();
        $this->assertStringContainsString('laravel_queue_jobs_total{queue="default",status="failed"} 1', $output);
        $this->assertStringContainsString('laravel_scheduled_tasks_total', $output);
        $this->assertStringContainsString('laravel_scheduler_last_run_timestamp_seconds', $output);
        $this->assertStringContainsString('laravel_exceptions_total', $output);
        $this->assertLessThanOrEqual(50, array_sum(array_map('count', $metrics->snapshot()['metrics'])));
    }

    public function test_http_exceptions_keep_their_status_and_balance_active_requests(): void
    {
        $metrics = app(MetricsRepository::class);
        try {
            (new TrackMonitoringRequest($metrics))->handle(Request::create('/missing'), fn () => throw new HttpException(404));
            $this->fail('The HTTP exception should be rethrown.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $output = $metrics->renderPrometheus();
        $this->assertStringContainsString('status="404"', $output);
        $this->assertStringNotContainsString('status="500"', $output);
        $this->assertStringContainsString('laravel_http_requests_active 0', $output);
    }

    public function test_scrapes_do_not_record_themselves_and_disabled_metrics_do_not_write(): void
    {
        $metrics = app(MetricsRepository::class);
        $middleware = new TrackMonitoringRequest($metrics);
        $middleware->handle(Request::create('/internal/metrics'), fn () => response('ok'));
        $this->assertFileDoesNotExist($this->metricsFile);
        config()->set('monitoring.enabled', false);
        $middleware->handle(Request::create('/anything'), fn () => response('ok'));
        $metrics->recordQueueJob('default', 'failed');
        $this->assertFileDoesNotExist($this->metricsFile);
    }

    public function test_corrupt_or_unwritable_storage_does_not_break_recording(): void
    {
        File::ensureDirectoryExists(dirname($this->metricsFile));
        File::put($this->metricsFile, 'invalid json');
        $metrics = app(MetricsRepository::class);
        $this->assertSame(['metrics' => [], 'recent' => []], $metrics->snapshot());
        $metrics->recordQueueJob('default', 'processed');
        $this->assertStringContainsString('laravel_queue_jobs_total', $metrics->renderPrometheus());
        config()->set('monitoring.metrics_file', '/proc/codex-monitoring/metrics.json');
        $metrics->recordQueueJob('default', 'failed');
        $this->assertSame(['metrics' => [], 'recent' => []], $metrics->snapshot());
    }

    public function test_histogram_buckets_are_cumulative_and_recent_events_are_bounded(): void
    {
        config()->set('monitoring.recent_event_limit', 2);
        $metrics = app(MetricsRepository::class);
        foreach ([0.01, 0.5, 2.0] as $seconds) {
            $metrics->recordQueueJob('default', 'processed', $seconds);
            $metrics->recordException(new \RuntimeException('message'));
        }
        $output = $metrics->renderPrometheus();
        $this->assertStringContainsString('laravel_queue_job_duration_seconds_bucket{le="0.01",queue="default"} 1', $output);
        $this->assertStringContainsString('laravel_queue_job_duration_seconds_bucket{le="0.5",queue="default"} 2', $output);
        $this->assertStringContainsString('laravel_queue_job_duration_seconds_bucket{le="+Inf",queue="default"} 3', $output);
        $this->assertCount(2, $metrics->snapshot()['recent']['exceptions']);
    }
}
