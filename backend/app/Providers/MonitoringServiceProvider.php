<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdministratorAccess;
use App\Http\Middleware\PreventAccessFromTenantDomains;
use App\Monitoring\Contracts\MonitoringDriver;
use App\Monitoring\Drivers\PrometheusDriver;
use App\Monitoring\Services\MetricsRepository;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MonitoringServiceProvider extends ServiceProvider
{
    /** @var \WeakMap<object, float>|null */
    private static ?\WeakMap $queueStarts = null;

    /** @var \WeakMap<object, float>|null */
    private static ?\WeakMap $httpStarts = null;

    public function register(): void
    {
        $this->app->singleton(MetricsRepository::class);
        $this->app->singleton(MonitoringDriver::class, PrometheusDriver::class);
        $this->app->singleton(MonitoringService::class);
    }

    public function boot(MetricsRepository $metrics): void
    {
        Livewire::addPersistentMiddleware([
            PreventAccessFromTenantDomains::class,
            EnsureAdministratorAccess::class,
        ]);

        if (! $metrics->enabled()) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($metrics): void {
            $metrics->recordSlowQuery($query->connectionName, $query->sql, (float) $query->time);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            self::$queueStarts ??= new \WeakMap;
            self::$queueStarts[$event->job] = microtime(true);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) use ($metrics): void {
            $key = $event->job;
            $duration = isset(self::$queueStarts[$key]) ? microtime(true) - self::$queueStarts[$key] : 0;
            unset(self::$queueStarts[$key]);
            $metrics->recordQueueJob((string) $event->job->getQueue(), 'processed', $duration);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use ($metrics): void {
            $key = $event->job;
            $duration = isset(self::$queueStarts[$key]) ? microtime(true) - self::$queueStarts[$key] : 0;
            unset(self::$queueStarts[$key]);
            $metrics->recordQueueJob((string) $event->job->getQueue(), 'failed', $duration);
            $metrics->recordException($event->exception);
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) use ($metrics): void {
            $metrics->recordScheduledTask(self::scheduledTaskName($event->task), 'completed', $event->runtime);
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) use ($metrics): void {
            $metrics->recordScheduledTask(self::scheduledTaskName($event->task), 'failed');
        });

        Event::listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $event) use ($metrics): void {
            $metrics->recordScheduledTask(self::scheduledTaskName($event->task), 'skipped');
        });

        Event::listen(RequestSending::class, function (RequestSending $event): void {
            self::$httpStarts ??= new \WeakMap;
            self::$httpStarts[$event->request->toPsrRequest()] = microtime(true);
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event) use ($metrics): void {
            $key = $event->request->toPsrRequest();
            $startedAt = self::$httpStarts[$key] ?? microtime(true);
            unset(self::$httpStarts[$key]);
            $host = (string) (parse_url($event->request->url(), PHP_URL_HOST) ?: 'unknown');
            $metrics->recordOutgoingRequest(
                $event->request->method(),
                $host,
                $event->response->status(),
                microtime(true) - $startedAt
            );
        });

        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event) use ($metrics): void {
            // Laravel creates a new Request wrapper for connection failures.
            // The underlying PSR request identifies the original send attempt.
            $key = $event->request->toPsrRequest();
            $startedAt = self::$httpStarts[$key] ?? microtime(true);
            unset(self::$httpStarts[$key]);
            $host = (string) (parse_url($event->request->url(), PHP_URL_HOST) ?: 'unknown');
            $metrics->recordOutgoingRequest($event->request->method(), $host, 0, microtime(true) - $startedAt);
        });
    }

    private static function scheduledTaskName(ScheduledEvent $event): string
    {
        if (is_string($event->description) && trim($event->description) !== '') {
            return mb_substr(trim($event->description), 0, 120);
        }

        if (is_string($event->command)
            && preg_match('/(?:^|\s)[\'\"]?artisan[\'\"]?\s+([^\s\'\"]+)/', $event->command, $matches) === 1) {
            return 'artisan '.mb_substr($matches[1], 0, 100);
        }

        return $event->command === null ? 'Scheduled callback' : 'Scheduled command';
    }
}
