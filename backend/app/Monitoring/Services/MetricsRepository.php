<?php

namespace App\Monitoring\Services;

use Throwable;

class MetricsRepository
{
    private const HISTOGRAM_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    public function enabled(): bool
    {
        return (bool) config('monitoring.enabled')
            && (bool) config('monitoring.components.laravel');
    }

    public function beginRequest(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->mutate(function (array &$state): void {
            $this->changeGauge($state, 'laravel_http_requests_active', [], 1);
        });
    }

    public function recordRequest(string $method, string $route, int $status, float $seconds, int $peakMemory): void
    {
        if (! $this->enabled()) {
            return;
        }

        $labels = [
            'method' => strtoupper($this->label($method, 12)),
            'route' => $this->label($route, 160),
            'status' => (string) $status,
        ];

        $this->mutate(function (array &$state) use ($labels, $seconds, $peakMemory): void {
            $this->changeGauge($state, 'laravel_http_requests_active', [], -1);
            $this->increment($state, 'laravel_http_requests_total', $labels);
            $this->observe($state, 'laravel_http_request_duration_seconds', $labels, $seconds);
            $this->setGaugeMax($state, 'laravel_memory_peak_bytes', [], $peakMemory);

            if ($seconds * 1000 >= (float) config('monitoring.slow_request_ms', 1000)) {
                $this->addRecent($state, 'slow_requests', [
                    'at' => gmdate(DATE_ATOM),
                    'method' => $labels['method'],
                    'route' => $labels['route'],
                    'status' => (int) $labels['status'],
                    'duration_ms' => round($seconds * 1000, 2),
                ]);
            }
        });
    }

    public function recordException(Throwable $exception): void
    {
        if (! $this->enabled()) {
            return;
        }

        $class = $this->label(get_class($exception), 160);
        $this->mutate(function (array &$state) use ($class): void {
            $this->increment($state, 'laravel_exceptions_total', ['class' => $class]);
            $this->addRecent($state, 'exceptions', [
                'at' => gmdate(DATE_ATOM),
                'class' => $class,
            ]);
        });
    }

    public function recordSlowQuery(string $connection, string $sql, float $milliseconds): void
    {
        if (! $this->enabled() || $milliseconds < (float) config('monitoring.slow_query_ms', 500)) {
            return;
        }

        $connection = $this->label($connection ?: 'default', 60);
        $safeSql = preg_replace("/'(?:''|[^'])*'/", "'?'", $sql) ?? $sql;
        $safeSql = preg_replace('/\\b\\d{4,}\\b/', '?', $safeSql) ?? $safeSql;
        $safeSql = $this->label(preg_replace('/\\s+/', ' ', $safeSql) ?? $safeSql, 500);

        $this->mutate(function (array &$state) use ($connection, $safeSql, $milliseconds): void {
            $this->increment($state, 'laravel_slow_sql_queries_total', ['connection' => $connection]);
            $this->observe($state, 'laravel_slow_sql_query_duration_seconds', ['connection' => $connection], $milliseconds / 1000);
            $this->addRecent($state, 'slow_queries', [
                'at' => gmdate(DATE_ATOM),
                'connection' => $connection,
                'duration_ms' => round($milliseconds, 2),
                'query' => $safeSql,
            ]);
        });
    }

    public function recordQueueJob(string $queue, string $status, float $seconds = 0): void
    {
        if (! $this->enabled()) {
            return;
        }

        $labels = [
            'queue' => $this->label($queue ?: 'default', 80),
            'status' => $this->label($status, 20),
        ];

        $this->mutate(function (array &$state) use ($labels, $seconds): void {
            $this->increment($state, 'laravel_queue_jobs_total', $labels);
            if ($seconds > 0) {
                $this->observe($state, 'laravel_queue_job_duration_seconds', ['queue' => $labels['queue']], $seconds);
            }
        });
    }

    public function recordOutgoingRequest(string $method, string $host, int $status, float $seconds): void
    {
        if (! $this->enabled()) {
            return;
        }

        $labels = [
            'host' => $this->label($host ?: 'unknown', 120),
            'method' => strtoupper($this->label($method, 12)),
            'status' => (string) $status,
        ];

        $this->mutate(function (array &$state) use ($labels, $seconds): void {
            $this->increment($state, 'laravel_outgoing_http_requests_total', $labels);
            $this->observe($state, 'laravel_outgoing_http_request_duration_seconds', $labels, max(0, $seconds));
        });
    }

    public function recordSchedulerHeartbeat(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->mutate(function (array &$state): void {
            $this->setGauge($state, 'laravel_scheduler_last_run_timestamp_seconds', [], time());
        });
    }

    public function recordScheduledTask(string $task, string $status, float $seconds = 0): void
    {
        if (! $this->enabled()) {
            return;
        }

        $labels = [
            'task' => $this->label($task, 120),
            'status' => $this->label($status, 20),
        ];

        $this->mutate(function (array &$state) use ($labels, $seconds): void {
            $this->increment($state, 'laravel_scheduled_tasks_total', $labels);
            $this->setGauge($state, 'laravel_scheduled_task_last_run_timestamp_seconds', ['task' => $labels['task']], time());
            if ($seconds > 0) {
                $this->observe($state, 'laravel_scheduled_task_duration_seconds', ['task' => $labels['task']], $seconds);
            }
            $this->addRecent($state, 'scheduled_tasks', [
                'at' => gmdate(DATE_ATOM),
                'task' => $labels['task'],
                'status' => $labels['status'],
                'duration_ms' => round(max(0, $seconds) * 1000, 2),
            ]);
        });
    }

    public function snapshot(): array
    {
        if (! $this->enabled()) {
            return ['metrics' => [], 'recent' => []];
        }

        return $this->read();
    }

    public function renderPrometheus(): string
    {
        $state = $this->snapshot();
        $lines = [];

        foreach (($state['metrics'] ?? []) as $metric => $series) {
            foreach ($series as $item) {
                $labels = $this->renderLabels((array) ($item['labels'] ?? []));
                $value = is_numeric($item['value'] ?? null) ? (string) (float) $item['value'] : '0';
                $lines[] = $metric.$labels.' '.$value;
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function mutate(callable $callback): void
    {
        try {
            $path = (string) config('monitoring.metrics_file');
            $directory = dirname($path);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return;
            }

            $handle = fopen($path, 'c+');
            if ($handle === false) {
                return;
            }

            try {
                if (! flock($handle, LOCK_EX)) {
                    return;
                }

                rewind($handle);
                $raw = stream_get_contents($handle);
                $state = $raw ? json_decode($raw, true) : null;
                if (! is_array($state)) {
                    $state = ['metrics' => [], 'recent' => []];
                }

                $callback($state);
                $state['updated_at'] = gmdate(DATE_ATOM);
                $encoded = json_encode($state, JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    rewind($handle);
                    ftruncate($handle, 0);
                    fwrite($handle, $encoded);
                    fflush($handle);
                }

                flock($handle, LOCK_UN);
            } finally {
                fclose($handle);
            }
        } catch (Throwable) {
            // Metrics must never affect application traffic.
        }
    }

    private function read(): array
    {
        try {
            $path = (string) config('monitoring.metrics_file');
            if (! is_file($path)) {
                return ['metrics' => [], 'recent' => []];
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                return ['metrics' => [], 'recent' => []];
            }

            try {
                flock($handle, LOCK_SH);
                $state = json_decode((string) stream_get_contents($handle), true);
                flock($handle, LOCK_UN);
            } finally {
                fclose($handle);
            }

            return is_array($state) ? $state : ['metrics' => [], 'recent' => []];
        } catch (Throwable) {
            return ['metrics' => [], 'recent' => []];
        }
    }

    private function increment(array &$state, string $metric, array $labels, float $amount = 1): void
    {
        $key = $this->seriesKey($labels);
        if (! isset($state['metrics'][$metric][$key]) && ! $this->reserveSeries($state, $metric)) {
            return;
        }

        $state['metrics'][$metric][$key] ??= ['labels' => $labels, 'value' => 0];
        $state['metrics'][$metric][$key]['value'] += $amount;
    }

    private function changeGauge(array &$state, string $metric, array $labels, float $amount): void
    {
        $key = $this->seriesKey($labels);
        if (! isset($state['metrics'][$metric][$key]) && ! $this->reserveSeries($state, $metric)) {
            return;
        }
        $state['metrics'][$metric][$key] ??= ['labels' => $labels, 'value' => 0];
        $state['metrics'][$metric][$key]['value'] = max(0, (float) $state['metrics'][$metric][$key]['value'] + $amount);
    }

    private function setGaugeMax(array &$state, string $metric, array $labels, float $value): void
    {
        $key = $this->seriesKey($labels);
        if (! isset($state['metrics'][$metric][$key]) && ! $this->reserveSeries($state, $metric)) {
            return;
        }
        $state['metrics'][$metric][$key] ??= ['labels' => $labels, 'value' => 0];
        $state['metrics'][$metric][$key]['value'] = max((float) $state['metrics'][$metric][$key]['value'], $value);
    }

    private function setGauge(array &$state, string $metric, array $labels, float $value): void
    {
        $key = $this->seriesKey($labels);
        if (! isset($state['metrics'][$metric][$key]) && ! $this->reserveSeries($state, $metric)) {
            return;
        }
        $state['metrics'][$metric][$key] = ['labels' => $labels, 'value' => $value];
    }

    private function observe(array &$state, string $metric, array $labels, float $value): void
    {
        $this->increment($state, $metric.'_sum', $labels, $value);
        $this->increment($state, $metric.'_count', $labels);

        foreach (self::HISTOGRAM_BUCKETS as $bucket) {
            if ($value <= $bucket) {
                $this->increment($state, $metric.'_bucket', array_merge($labels, ['le' => (string) $bucket]));
            }
        }

        $this->increment($state, $metric.'_bucket', array_merge($labels, ['le' => '+Inf']));
    }

    private function addRecent(array &$state, string $type, array $entry): void
    {
        $state['recent'][$type] ??= [];
        array_unshift($state['recent'][$type], $entry);
        $state['recent'][$type] = array_slice(
            $state['recent'][$type],
            0,
            max(1, (int) config('monitoring.recent_event_limit', 50))
        );
    }

    private function reserveSeries(array &$state, string $metric): bool
    {
        $count = 0;
        foreach (($state['metrics'] ?? []) as $series) {
            $count += count($series);
        }

        $limit = max(50, (int) config('monitoring.max_series', 500));
        if ($count < $limit) {
            return true;
        }
        if (str_ends_with($metric, '_bucket')) {
            return false;
        }

        // Dashboard counters, duration sums/counts, and heartbeats take priority
        // over optional histogram buckets when cardinality reaches the limit.
        foreach ($state['metrics'] as $name => &$series) {
            if (! str_ends_with($name, '_bucket')) {
                continue;
            }
            foreach ($series as $key => $item) {
                if (($item['labels']['le'] ?? '') === '+Inf') {
                    continue;
                }
                unset($series[$key]);
                if (--$count < $limit) {
                    return true;
                }
            }
        }

        return false;
    }

    private function seriesKey(array $labels): string
    {
        ksort($labels);

        return hash('sha256', (string) json_encode($labels));
    }

    private function renderLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);
        $rendered = [];
        foreach ($labels as $name => $value) {
            $escaped = str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], (string) $value);
            $rendered[] = $name.'="'.$escaped.'"';
        }

        return '{'.implode(',', $rendered).'}';
    }

    private function label(string $value, int $limit): string
    {
        $value = trim(preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $value) ?? '');

        return mb_substr($value === '' ? 'unknown' : $value, 0, $limit);
    }
}
