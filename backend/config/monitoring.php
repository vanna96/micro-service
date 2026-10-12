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

    'autoscaling' => [
        'status_file' => env('AUTOSCALER_STATUS_FILE', base_path('autoscaling/runtime/status.json')),
        'stale_after_seconds' => (int) env('AUTOSCALER_STATUS_STALE_SECONDS', 90),
        'up_cpu_percent' => (float) env('AUTOSCALE_UP_CPU', 70),
        'down_cpu_percent' => (float) env('AUTOSCALE_DOWN_CPU', 25),
        'up_seconds' => (int) env('AUTOSCALE_UP_SECONDS', 60),
        'down_seconds' => (int) env('AUTOSCALE_DOWN_SECONDS', 300),
        'cooldown_seconds' => (int) env('AUTOSCALE_COOLDOWN', 300),
        'max_replicas' => 2,
    ],

    'metrics_file' => env('MONITORING_METRICS_FILE', storage_path('app/monitoring/metrics.json')),
    'refresh_seconds' => (int) env('MONITORING_REFRESH_SECONDS', 15),
    'slow_request_ms' => (float) env('MONITORING_SLOW_REQUEST_MS', 1000),
    'slow_query_ms' => (float) env('MONITORING_SLOW_QUERY_MS', 500),
    'recent_event_limit' => (int) env('MONITORING_RECENT_EVENT_LIMIT', 50),
    'recent_requests' => [
        'enabled' => (bool) env('MONITORING_RECENT_REQUESTS_ENABLED', true),
        'limit' => (int) env('MONITORING_RECENT_REQUEST_LIMIT', 100),
        'redis_key' => env('MONITORING_RECENT_REQUEST_REDIS_KEY', 'monitoring:recent-requests'),
    ],
    'max_series' => (int) env('MONITORING_MAX_SERIES', 500),
];
