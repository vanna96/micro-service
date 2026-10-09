<?php

namespace App\Monitoring\Services;

use App\Models\Tenant;
use App\Monitoring\Contracts\MonitoringDriver;
use App\Monitoring\DTO\ComponentStatus;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Pulse\Pulse;
use Throwable;

class MonitoringService
{
    private MonitoringDriver $driver;

    private MetricsRepository $metrics;

    private bool $queryFailed = false;

    private bool $connectionFailed = false;

    private bool $lastQueryFailed = false;

    public function __construct(MonitoringDriver $driver, MetricsRepository $metrics)
    {
        $this->driver = $driver;
        $this->metrics = $metrics;
    }

    public function dashboard(): array
    {
        $this->queryFailed = false;
        $this->connectionFailed = false;
        $enabled = (bool) config('monitoring.enabled');
        $prometheusAvailable = $enabled && $this->driver->isConfigured() && $this->driver->isAvailable();
        if ($enabled && $this->driver->isConfigured() && ! $prometheusAvailable) {
            $this->logWarning('Prometheus monitoring is unavailable; the dashboard is running in degraded mode.');
        }
        $targets = $prometheusAvailable ? $this->targetStates() : [];
        $snapshot = $this->metrics->snapshot();

        $data = [
            'monitoringEnabled' => $enabled,
            'statuses' => $this->statuses($prometheusAvailable, $targets),
            'server' => $this->emptyServer(),
            'charts' => $this->emptyCharts(),
            'containers' => [],
            'http' => [
                'total_requests' => null,
                'requests_per_second' => null,
                'active_requests' => null,
                'average_duration_ms' => null,
                'average_response_bytes' => null,
                'status_codes' => [],
            ],
            'api' => [
                'requests_per_minute' => null,
                'average_duration_ms' => null,
                'error_rate_percent' => null,
                'active_requests' => null,
                'peak_memory_bytes' => null,
                'outgoing_requests_per_minute' => null,
                'outgoing_average_duration_ms' => null,
                'outgoing_failures_per_minute' => null,
                'slow_endpoints' => [],
            ],
            'database' => [
                'slow_queries_per_minute' => null,
                'recent' => (array) data_get($snapshot, 'recent.slow_queries', []),
            ],
            'queues' => [
                'processed_per_minute' => null,
                'failed_per_minute' => null,
                'pending' => null,
                'failed_total' => null,
                'connection' => (string) config('queue.default', 'unknown'),
                'unavailable_databases' => 0,
            ],
            'scheduler' => [
                'state' => $enabled ? 'unknown' : 'disabled',
                'seconds_since_last_run' => null,
                'tasks' => [],
                'recent' => (array) data_get($snapshot, 'recent.scheduled_tasks', []),
            ],
            'services' => $this->emptyServices(),
            'errors' => [
                'per_minute' => null,
                'recent' => (array) data_get($snapshot, 'recent.exceptions', []),
            ],
            'recentSlowRequests' => (array) data_get($snapshot, 'recent.slow_requests', []),
        ];

        if ($enabled) {
            $data['services'] = $this->serviceChecks();
            $data['queues'] = array_merge($data['queues'], $this->queueDatabaseMetrics());
            $data['scheduler']['tasks'] = $this->scheduledTasks();
            $heartbeat = collect(data_get($snapshot, 'metrics.laravel_scheduler_last_run_timestamp_seconds', []))->first();
            if (is_numeric($heartbeat['value'] ?? null)) {
                $age = max(0, time() - (float) $heartbeat['value']);
                $data['scheduler']['state'] = $age <= 120 ? 'healthy' : 'overdue';
                $data['scheduler']['seconds_since_last_run'] = $age;
            }
        }

        if (! $prometheusAvailable) {
            return $data;
        }

        try {
            $connected = fn (string $name) => collect($data['statuses'])->firstWhere('name', $name)['state'] === ComponentStatus::CONNECTED;
            if ($connected('Node Exporter')) {
                $data['server'] = $this->serverMetrics();
            }
            if ($connected('cAdvisor')) {
                $data['containers'] = $this->containerMetrics();
            }
            if ($connected('Caddy Metrics')) {
                $data['http'] = $this->caddyMetrics();
            }
            $data['charts'] = $this->chartMetrics($connected('Node Exporter'), $connected('Laravel Metrics'));
            if ($connected('Laravel Metrics')) {
                $data['api'] = $this->apiMetrics();
                $data['database']['slow_queries_per_minute'] = $this->scalar(
                    'sum(rate(laravel_slow_sql_queries_total[5m])) * 60'
                );
                $data['queues']['processed_per_minute'] = $this->scalar(
                    'sum(rate(laravel_queue_jobs_total{status="processed"}[5m])) * 60'
                );
                if (! $this->lastQueryFailed) {
                    $data['queues']['processed_per_minute'] ??= 0.0;
                }
                $data['queues']['failed_per_minute'] = $this->scalar(
                    'sum(rate(laravel_queue_jobs_total{status="failed"}[5m])) * 60'
                );
                if (! $this->lastQueryFailed) {
                    $data['queues']['failed_per_minute'] ??= 0.0;
                }
                $schedulerAge = $this->scalar('time() - laravel_scheduler_last_run_timestamp_seconds');
                if ($schedulerAge !== null) {
                    $data['scheduler'] = [
                        'state' => $schedulerAge === null ? 'unknown' : ($schedulerAge <= 120 ? 'healthy' : 'overdue'),
                        'seconds_since_last_run' => $schedulerAge,
                        'tasks' => $data['scheduler']['tasks'],
                        'recent' => $data['scheduler']['recent'],
                    ];
                }
                $data['errors']['per_minute'] = $this->scalar('sum(rate(laravel_exceptions_total[5m])) * 60');
            }
        } catch (Throwable $exception) {
            // Preserve the page and the successful status probe if one metric is absent.
            $this->logWarning('A Prometheus dashboard query failed; partial monitoring data was returned.', [
                'exception' => get_class($exception),
            ]);
        }

        return $data;
    }

    /**
     * Return safe scheduler metadata without exposing command arguments.
     *
     * @return array<int, array{name: string, expression: string, next_run_at: string, timezone: string}>
     */
    private function scheduledTasks(): array
    {
        try {
            $timezone = (string) config('app.timezone', 'UTC');

            return collect(app(Schedule::class)->events())
                ->take(50)
                ->map(function (ScheduledEvent $event) use ($timezone): array {
                    $eventTimezone = (string) ($event->timezone ?: $timezone);

                    return [
                        'name' => $this->scheduledTaskName($event),
                        'expression' => (string) $event->expression,
                        'next_run_at' => $event->nextRunDate()->setTimezone($eventTimezone)->format('Y-m-d H:i:s T'),
                        'timezone' => $eventTimezone,
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable $exception) {
            $this->logWarning('Laravel scheduled tasks could not be listed.', [
                'exception' => get_class($exception),
            ]);

            return [];
        }
    }

    private function scheduledTaskName(ScheduledEvent $event): string
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

    private function statuses(bool $prometheusAvailable, array $targets): array
    {
        $enabled = (bool) config('monitoring.enabled');
        if (! $enabled) {
            return array_map(
                fn (string $name) => (new ComponentStatus($name, ComponentStatus::DISABLED))->toArray(),
                ['Prometheus', 'Node Exporter', 'cAdvisor', 'Caddy Metrics', 'Laravel Metrics', 'Laravel Pulse']
            );
        }

        $statuses = [];
        if (! config('monitoring.prometheus.enabled')) {
            $prometheus = new ComponentStatus('Prometheus', ComponentStatus::DISABLED);
        } elseif (! $this->driver->isConfigured()) {
            $prometheus = new ComponentStatus('Prometheus', ComponentStatus::NOT_CONFIGURED, 'PROMETHEUS_URL is empty.');
        } elseif (! $prometheusAvailable) {
            $prometheus = new ComponentStatus('Prometheus', ComponentStatus::UNAVAILABLE, 'The application remains available.');
        } else {
            $prometheus = new ComponentStatus('Prometheus', ComponentStatus::CONNECTED);
        }
        $statuses[] = $prometheus->toArray();

        $components = [
            ['Node Exporter', 'node_exporter', 'node-exporter'],
            ['cAdvisor', 'cadvisor', 'cadvisor'],
            ['Caddy Metrics', 'caddy', 'caddy'],
            ['Laravel Metrics', 'laravel', 'laravel'],
        ];

        foreach ($components as [$name, $configKey, $job]) {
            if (! config('monitoring.components.'.$configKey)) {
                $state = ComponentStatus::DISABLED;
                $detail = null;
            } elseif (! config('monitoring.prometheus.enabled')) {
                $state = ComponentStatus::NOT_CONFIGURED;
                $detail = 'Prometheus is disabled.';
            } elseif (! $prometheusAvailable) {
                $state = ComponentStatus::UNAVAILABLE;
                $detail = 'Prometheus cannot be reached.';
            } else {
                $state = ($targets[$job] ?? false) ? ComponentStatus::CONNECTED : ComponentStatus::UNAVAILABLE;
                $detail = $state === ComponentStatus::UNAVAILABLE ? 'The scrape target is down.' : null;
            }

            $statuses[] = (new ComponentStatus($name, $state, $detail))->toArray();
        }

        if (! config('monitoring.components.pulse')) {
            $pulse = new ComponentStatus('Laravel Pulse', ComponentStatus::DISABLED, 'Recording is disabled by PULSE_ENABLED.');
        } elseif (! class_exists(Pulse::class)) {
            $pulse = new ComponentStatus('Laravel Pulse', ComponentStatus::UNAVAILABLE, 'The Laravel Pulse package is unavailable.');
        } else {
            try {
                $connection = DB::connection(config('pulse.storage.database.connection'));
                $ready = collect(['pulse_values', 'pulse_entries', 'pulse_aggregates'])
                    ->every(fn (string $table) => $connection->getSchemaBuilder()->hasTable($table));
                $pulse = new ComponentStatus('Laravel Pulse', $ready ? ComponentStatus::CONNECTED : ComponentStatus::UNAVAILABLE,
                    $ready ? null : 'Pulse storage tables are missing.');
            } catch (Throwable) {
                $pulse = new ComponentStatus('Laravel Pulse', ComponentStatus::UNAVAILABLE, 'Pulse storage cannot be reached.');
            }
        }
        $statuses[] = $pulse->toArray();

        return $statuses;
    }

    private function targetStates(): array
    {
        $states = [];
        try {
            foreach ($this->query('up') as $result) {
                $job = (string) data_get($result, 'metric.job', '');
                if ($job !== '') {
                    $states[$job] = ((float) data_get($result, 'value.1', 0)) === 1.0;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $states;
    }

    private function serverMetrics(): array
    {
        return [
            'cpu_percent' => $this->scalar('100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)'),
            'cpu_temperature_celsius' => $this->scalar('max(node_hwmon_temp_celsius{chip=~".*(coretemp|k10temp).*"}) or max(node_thermal_zone_temp{type=~".*(pkg|cpu|soc).*"})'),
            'cpu_frequency_hertz' => $this->scalar('avg(node_cpu_scaling_frequency_hertz)'),
            'fan_rpm' => $this->scalar('max(node_hwmon_fan_rpm)'),
            'ram_used_bytes' => $this->scalar('node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes'),
            'ram_total_bytes' => $this->scalar('node_memory_MemTotal_bytes'),
            'swap_used_bytes' => $this->scalar('node_memory_SwapTotal_bytes - node_memory_SwapFree_bytes'),
            'swap_total_bytes' => $this->scalar('node_memory_SwapTotal_bytes'),
            'disk_used_bytes' => $this->scalar('node_filesystem_size_bytes{mountpoint="/",fstype!~"tmpfs|overlay"} - node_filesystem_avail_bytes{mountpoint="/",fstype!~"tmpfs|overlay"}'),
            'disk_total_bytes' => $this->scalar('node_filesystem_size_bytes{mountpoint="/",fstype!~"tmpfs|overlay"}'),
            'disk_read_bytes_per_second' => $this->scalar('sum(rate(node_disk_read_bytes_total[5m]))'),
            'disk_write_bytes_per_second' => $this->scalar('sum(rate(node_disk_written_bytes_total[5m]))'),
            'network_receive_bytes_per_second' => $this->scalar('sum(rate(node_network_receive_bytes_total{device!="lo"}[5m]))'),
            'network_transmit_bytes_per_second' => $this->scalar('sum(rate(node_network_transmit_bytes_total{device!="lo"}[5m]))'),
            'network_receive_errors_per_second' => $this->scalar('sum(rate(node_network_receive_errs_total{device!="lo"}[5m]))'),
            'network_transmit_errors_per_second' => $this->scalar('sum(rate(node_network_transmit_errs_total{device!="lo"}[5m]))'),
            'network_receive_drops_per_second' => $this->scalar('sum(rate(node_network_receive_drop_total{device!="lo"}[5m]))'),
            'network_transmit_drops_per_second' => $this->scalar('sum(rate(node_network_transmit_drop_total{device!="lo"}[5m]))'),
            'filesystem_inode_used_percent' => $this->scalar('100 * (1 - (node_filesystem_files_free{mountpoint="/",fstype!~"tmpfs|overlay"} / node_filesystem_files{mountpoint="/",fstype!~"tmpfs|overlay"}))'),
            'load_1' => $this->scalar('node_load1'),
            'load_5' => $this->scalar('node_load5'),
            'load_15' => $this->scalar('node_load15'),
            'uptime_seconds' => $this->scalar('time() - node_boot_time_seconds'),
            'temperature_sensors' => $this->temperatureSensors(),
        ];
    }

    private function temperatureSensors(): array
    {
        $sensors = [];
        $query = 'node_hwmon_temp_celsius{chip=~".*(coretemp|k10temp).*"} * on(chip, sensor) group_left(label) node_hwmon_sensor_label';

        foreach ($this->query($query) as $result) {
            $temperature = data_get($result, 'value.1');
            if (! is_numeric($temperature)) {
                continue;
            }

            $sensors[] = [
                'name' => (string) data_get($result, 'metric.label', data_get($result, 'metric.sensor', 'CPU')),
                'temperature_celsius' => (float) $temperature,
            ];
        }

        usort($sensors, fn (array $left, array $right) => $right['temperature_celsius'] <=> $left['temperature_celsius']);

        return $sensors;
    }

    private function chartMetrics(bool $serverConnected, bool $laravelConnected): array
    {
        $end = time();
        $start = $end - 3600;
        $step = 60;
        $temperatureQuery = 'max(node_hwmon_temp_celsius{chip=~".*(coretemp|k10temp).*"}) or max(node_thermal_zone_temp{type=~".*(pkg|cpu|soc).*"})';

        $queries = [
            'cpu_percent' => '100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)',
            'ram_percent' => '(1 - (node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes)) * 100',
            'cpu_temperature_celsius' => $temperatureQuery,
            'network_receive_bytes_per_second' => 'sum(rate(node_network_receive_bytes_total{device!="lo"}[5m]))',
            'network_transmit_bytes_per_second' => 'sum(rate(node_network_transmit_bytes_total{device!="lo"}[5m]))',
            'api_requests_per_minute' => 'sum(rate(laravel_http_requests_total[5m])) * 60',
            'api_average_duration_ms' => 'sum(rate(laravel_http_request_duration_seconds_sum[5m])) / clamp_min(sum(rate(laravel_http_request_duration_seconds_count[5m])), 0.000001) * 1000',
            'http_4xx_per_minute' => 'sum(rate(laravel_http_requests_total{status=~"4.."}[5m])) * 60',
            'http_5xx_per_minute' => 'sum(rate(laravel_http_requests_total{status=~"5.."}[5m])) * 60',
            'queue_processed_per_minute' => 'sum(rate(laravel_queue_jobs_total{status="processed"}[5m])) * 60',
            'queue_failed_per_minute' => 'sum(rate(laravel_queue_jobs_total{status="failed"}[5m])) * 60',
        ];
        $charts = $this->emptyCharts();
        foreach ($queries as $key => $query) {
            if (str_contains($query, 'node_') ? $serverConnected : $laravelConnected) {
                $charts[$key] = $this->rangePoints($query, $start, $end, $step);
            }
        }

        return $charts;
    }

    private function rangePoints(string $query, int $start, int $end, int $step): array
    {
        if ($this->connectionFailed) {
            return [];
        }
        try {
            $result = $this->driver->queryRange($query, $start, $end, $step)[0] ?? null;
        } catch (Throwable $exception) {
            $this->recordQueryFailure($exception);

            return [];
        }
        if (! is_array($result)) {
            return [];
        }

        $points = [];
        foreach ((array) data_get($result, 'values', []) as $value) {
            if (! isset($value[0], $value[1]) || ! is_numeric($value[0]) || ! is_numeric($value[1])) {
                continue;
            }

            $points[] = [
                'x' => (int) round((float) $value[0] * 1000),
                'y' => round((float) $value[1], 3),
            ];
        }

        return $points;
    }

    private function containerMetrics(): array
    {
        $containers = [];
        $queries = [
            'cpu_percent' => 'sum(rate(container_cpu_usage_seconds_total{image!=""}[5m])) by (name) * 100',
            'memory_bytes' => 'sum(container_memory_working_set_bytes{image!=""}) by (name)',
            'network_receive_bytes_per_second' => 'sum(rate(container_network_receive_bytes_total{image!=""}[5m])) by (name)',
            'network_transmit_bytes_per_second' => 'sum(rate(container_network_transmit_bytes_total{image!=""}[5m])) by (name)',
            'disk_read_bytes_per_second' => 'sum(rate(container_fs_reads_bytes_total{image!=""}[5m])) by (name)',
            'disk_write_bytes_per_second' => 'sum(rate(container_fs_writes_bytes_total{image!=""}[5m])) by (name)',
            'last_seen' => 'max(container_last_seen{image!=""}) by (name)',
            'started_at' => 'max(container_start_time_seconds{image!=""}) by (name)',
        ];

        foreach ($queries as $field => $query) {
            foreach ($this->query($query) as $result) {
                $name = ltrim((string) data_get($result, 'metric.name', ''), '/');
                if ($name === '') {
                    continue;
                }
                $containers[$name] ??= ['name' => $name];
                $containers[$name][$field] = (float) data_get($result, 'value.1', 0);
            }
        }

        foreach ($containers as &$container) {
            $container['status'] = isset($container['last_seen']) && time() - $container['last_seen'] < 90 ? 'running' : 'stale';
            $container['uptime_seconds'] = isset($container['started_at']) ? max(0, time() - $container['started_at']) : null;
        }
        unset($container);
        ksort($containers);

        return array_values($containers);
    }

    private function caddyMetrics(): array
    {
        $statuses = [];
        // encode wraps the public routes in our Caddyfile. Counting that outer
        // handler includes every public request once and excludes metrics scrapes.
        foreach ($this->query('sum(rate(caddy_http_request_duration_seconds_count{handler="encode"}[5m])) by (code)') as $result) {
            $statuses[(string) data_get($result, 'metric.code', 'unknown')] = (float) data_get($result, 'value.1', 0);
        }

        $duration = $this->scalar('sum(rate(caddy_http_request_duration_seconds_sum{handler="encode"}[5m])) / clamp_min(sum(rate(caddy_http_request_duration_seconds_count{handler="encode"}[5m])), 0.000001)');

        return [
            'total_requests' => $this->scalar('sum(caddy_http_requests_total{handler="encode"})'),
            'requests_per_second' => $this->scalar('sum(rate(caddy_http_requests_total{handler="encode"}[5m]))'),
            'active_requests' => $this->scalar('sum(caddy_http_requests_in_flight{handler="encode"})'),
            'average_duration_ms' => $duration === null ? null : $duration * 1000,
            'average_response_bytes' => $this->scalar('sum(rate(caddy_http_response_size_bytes_sum{handler="encode"}[5m])) / clamp_min(sum(rate(caddy_http_response_size_bytes_count{handler="encode"}[5m])), 0.000001)'),
            'status_codes' => $statuses,
        ];
    }

    private function apiMetrics(): array
    {
        $slowEndpoints = [];
        $query = 'topk(10, sum by (route, method) (rate(laravel_http_request_duration_seconds_sum[5m])) / clamp_min(sum by (route, method) (rate(laravel_http_request_duration_seconds_count[5m])), 0.000001))';
        foreach ($this->query($query) as $result) {
            $slowEndpoints[] = [
                'route' => (string) data_get($result, 'metric.route', 'unknown'),
                'method' => (string) data_get($result, 'metric.method', ''),
                'duration_ms' => (float) data_get($result, 'value.1', 0) * 1000,
            ];
        }

        $duration = $this->scalar('sum(rate(laravel_http_request_duration_seconds_sum[5m])) / clamp_min(sum(rate(laravel_http_request_duration_seconds_count[5m])), 0.000001)');
        $outgoingDuration = $this->scalar('sum(rate(laravel_outgoing_http_request_duration_seconds_sum[5m])) / clamp_min(sum(rate(laravel_outgoing_http_request_duration_seconds_count[5m])), 0.000001)');

        return [
            'requests_per_minute' => $this->scalar('sum(rate(laravel_http_requests_total[5m])) * 60'),
            'average_duration_ms' => $duration === null ? null : $duration * 1000,
            'error_rate_percent' => $this->scalar('sum(rate(laravel_http_requests_total{status=~"5.."}[5m])) / clamp_min(sum(rate(laravel_http_requests_total[5m])), 0.000001) * 100'),
            'active_requests' => $this->scalar('sum(laravel_http_requests_active)'),
            'peak_memory_bytes' => $this->scalar('max(laravel_memory_peak_bytes)'),
            'outgoing_requests_per_minute' => $this->scalar('sum(rate(laravel_outgoing_http_requests_total[5m])) * 60'),
            'outgoing_average_duration_ms' => $outgoingDuration === null ? null : $outgoingDuration * 1000,
            'outgoing_failures_per_minute' => $this->scalar('sum(rate(laravel_outgoing_http_requests_total{status=~"0|5.."}[5m])) * 60'),
            'slow_endpoints' => $slowEndpoints,
        ];
    }

    private function serviceChecks(): array
    {
        $services = $this->emptyServices();

        try {
            DB::connection(config('tenancy.database.central_connection', 'central'))->selectOne('select 1');
            $services['database'] = ['state' => 'connected', 'detail' => 'Central database'];
        } catch (Throwable) {
            $services['database'] = ['state' => 'unavailable', 'detail' => 'Central database'];
        }

        $cacheDriver = (string) config('cache.default', 'file');
        if ($cacheDriver === 'redis') {
            try {
                app('redis')->connection()->ping();
                $services['redis'] = ['state' => 'connected', 'detail' => 'Cache connection'];
            } catch (Throwable) {
                $services['redis'] = ['state' => 'unavailable', 'detail' => 'Cache connection'];
            }
        } else {
            $services['redis'] = ['state' => 'not_configured', 'detail' => 'Cache driver: '.$cacheDriver];
        }

        return $services;
    }

    private function queueDatabaseMetrics(): array
    {
        $values = [
            'pending' => null,
            'failed_total' => null,
            'connection' => (string) config('queue.default', 'unknown'),
            'unavailable_databases' => 0,
        ];

        $count = function ($connection) use (&$values): void {
            foreach (['pending' => 'jobs', 'failed_total' => 'failed_jobs'] as $metric => $table) {
                if ($connection->getSchemaBuilder()->hasTable($table)) {
                    $values[$metric] = ($values[$metric] ?? 0) + $connection->table($table)->count();
                }
            }
        };

        try {
            $central = DB::connection(config('tenancy.database.central_connection', 'central'));
            $count($central);
            if (! $central->getSchemaBuilder()->hasTable('tenants')) {
                return $values;
            }

            $tenants = Tenant::query()->get();
        } catch (Throwable) {
            $values['unavailable_databases']++;

            return $values;
        }

        foreach ($tenants as $tenant) {
            $connection = null;
            try {
                // Inspect tenant databases without bootstrapping tenancy, which
                // would change queue, filesystem, and authentication context.
                $connection = DB::build($tenant->database()->connection());
                $count($connection);
            } catch (Throwable) {
                $values['unavailable_databases']++;
            } finally {
                if ($connection !== null) {
                    DB::purge($connection->getName());
                }
            }
        }

        return $values;
    }

    private function scalar(string $query): ?float
    {
        $results = $this->query($query);
        if ($results === []) {
            return null;
        }

        $value = data_get($results, '0.value.1');

        return is_numeric($value) ? (float) $value : null;
    }

    private function query(string $query): array
    {
        $this->lastQueryFailed = false;
        if ($this->connectionFailed) {
            $this->lastQueryFailed = true;

            return [];
        }
        try {
            return $this->driver->query($query);
        } catch (Throwable $exception) {
            $this->lastQueryFailed = true;
            $this->recordQueryFailure($exception);

            return [];
        }
    }

    private function recordQueryFailure(Throwable $exception): void
    {
        if (! $this->queryFailed) {
            $this->logWarning('A Prometheus dashboard query failed; partial monitoring data was returned.', [
                'exception' => get_class($exception),
            ]);
        }
        $this->queryFailed = true;
        $this->connectionFailed = $exception instanceof ConnectionException
            || $exception->getPrevious() instanceof ConnectionException;
    }

    private function emptyServer(): array
    {
        $server = array_fill_keys([
            'cpu_percent', 'cpu_temperature_celsius', 'cpu_frequency_hertz', 'fan_rpm',
            'ram_used_bytes', 'ram_total_bytes', 'swap_used_bytes', 'swap_total_bytes',
            'disk_used_bytes', 'disk_total_bytes', 'disk_read_bytes_per_second', 'disk_write_bytes_per_second',
            'network_receive_bytes_per_second', 'network_transmit_bytes_per_second', 'load_1', 'load_5',
            'network_receive_errors_per_second', 'network_transmit_errors_per_second',
            'network_receive_drops_per_second', 'network_transmit_drops_per_second',
            'filesystem_inode_used_percent', 'load_15', 'uptime_seconds',
        ], null);

        $server['temperature_sensors'] = [];

        return $server;
    }

    private function emptyCharts(): array
    {
        return array_fill_keys([
            'cpu_percent', 'ram_percent', 'cpu_temperature_celsius',
            'network_receive_bytes_per_second', 'network_transmit_bytes_per_second',
            'api_requests_per_minute', 'api_average_duration_ms', 'http_4xx_per_minute',
            'http_5xx_per_minute', 'queue_processed_per_minute', 'queue_failed_per_minute',
        ], []);
    }

    private function emptyServices(): array
    {
        return [
            'database' => ['state' => 'disabled', 'detail' => 'Central database'],
            'redis' => ['state' => 'disabled', 'detail' => 'Cache connection'],
        ];
    }

    private function logWarning(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            // Logging failures must not make monitoring a dependency.
        }
    }
}
