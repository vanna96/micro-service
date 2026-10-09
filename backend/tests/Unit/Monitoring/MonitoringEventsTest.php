<?php

namespace Tests\Unit\Monitoring;

use App\Monitoring\Services\MetricsRepository;
use App\Providers\MonitoringServiceProvider;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MonitoringEventsTest extends TestCase
{
    private string $metricsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsFile = storage_path('framework/testing/monitoring-events.json');
        File::delete($this->metricsFile);
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.components.laravel', true);
        config()->set('monitoring.metrics_file', $this->metricsFile);
        config()->set('monitoring.slow_query_ms', 1);
        $this->app->getProvider(MonitoringServiceProvider::class)->boot(app(MetricsRepository::class));
    }

    protected function tearDown(): void
    {
        File::delete($this->metricsFile);
        parent::tearDown();
    }

    public function test_queue_success_failure_and_slow_query_events_are_recorded(): void
    {
        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn([]);
        Event::dispatch(new JobProcessing('database', $job));
        Event::dispatch(new JobProcessed('database', $job));
        Event::dispatch(new JobProcessing('database', $job));
        Event::dispatch(new JobFailed('database', $job, new \RuntimeException('private message')));
        Event::dispatch(new QueryExecuted("select * from users where token = 'secret'", [], 25, DB::connection('central')));
        $metrics = app(MetricsRepository::class);
        $output = $metrics->renderPrometheus();
        $this->assertStringContainsString('status="processed"} 1', $output);
        $this->assertStringContainsString('status="failed"} 1', $output);
        $this->assertStringContainsString('laravel_slow_sql_queries_total', $output);
        $this->assertStringContainsString('laravel_exceptions_total', $output);
        $this->assertStringNotContainsString('secret', json_encode($metrics->snapshot()));
        $this->assertStringNotContainsString('private message', json_encode($metrics->snapshot()));
    }

    public function test_outgoing_success_and_failed_connection_keep_durations_with_new_wrappers(): void
    {
        $psr = new PsrRequest('GET', 'https://example.test/private?token=secret');
        $request = new Request($psr);
        Event::dispatch(new RequestSending($request));
        Event::dispatch(new ResponseReceived($request, new Response(new PsrResponse(200))));
        Event::dispatch(new RequestSending($request));
        usleep(10000);
        Event::dispatch(new ConnectionFailed(new Request($psr), new ConnectionException('offline')));
        $metrics = app(MetricsRepository::class);
        $output = $metrics->renderPrometheus();
        $this->assertStringContainsString('host="example.test",method="GET",status="200"} 1', $output);
        $this->assertStringContainsString('host="example.test",method="GET",status="0"} 1', $output);
        $duration = collect($metrics->snapshot()['metrics']['laravel_outgoing_http_request_duration_seconds_sum'])
            ->first(fn ($series) => $series['labels']['status'] === '0')['value'];
        $this->assertGreaterThanOrEqual(0.009, $duration);
        $this->assertStringNotContainsString('secret', json_encode($metrics->snapshot()));
    }

    public function test_scheduled_task_events_keep_safe_names_and_statuses(): void
    {
        $task = app(Schedule::class)->command('inspire --token=secret');
        Event::dispatch(new ScheduledTaskFinished($task, 0.5));
        Event::dispatch(new ScheduledTaskSkipped($task));
        Event::dispatch(new ScheduledTaskFailed($task, new \RuntimeException('failed')));
        $metrics = app(MetricsRepository::class);
        $output = $metrics->renderPrometheus();
        foreach (['completed', 'skipped', 'failed'] as $status) {
            $this->assertStringContainsString('status="'.$status.'",task="artisan inspire"} 1', $output);
        }
        $this->assertStringNotContainsString('secret', json_encode($metrics->snapshot()));
        $this->assertCount(3, $metrics->snapshot()['recent']['scheduled_tasks']);
    }
}
