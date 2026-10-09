<?php

return [
    'enabled' => env('MONITORING_ENABLED', false),

    'prometheus' => [
        'enabled' => env('PROMETHEUS_ENABLED', false),
        'url' => env('PROMETHEUS_URL', 'http://prometheus:9090'),
        'connect_timeout' => (int) env('PROMETHEUS_CONNECT_TIMEOUT', 1),
        'timeout' => (int) env('PROMETHEUS_TIMEOUT', 2),
    ],

    'components' => [
        'node_exporter' => env('NODE_EXPORTER_ENABLED', false),
        'cadvisor' => env('CADVISOR_ENABLED', false),
        'caddy' => env('CADDY_METRICS_ENABLED', false),
        'laravel' => env('LARAVEL_METRICS_ENABLED', false),
        'pulse' => env('PULSE_ENABLED', false),
    ],

    'metrics_file' => env('MONITORING_METRICS_FILE', storage_path('app/monitoring/metrics.json')),
    'refresh_seconds' => (int) env('MONITORING_REFRESH_SECONDS', 15),
    'slow_request_ms' => (float) env('MONITORING_SLOW_REQUEST_MS', 1000),
    'slow_query_ms' => (float) env('MONITORING_SLOW_QUERY_MS', 500),
    'recent_event_limit' => (int) env('MONITORING_RECENT_EVENT_LIMIT', 50),
    'max_series' => (int) env('MONITORING_MAX_SERIES', 500),
];
