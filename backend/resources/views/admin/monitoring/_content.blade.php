@php
    $bytes = function ($value) {
        if ($value === null) return '—';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, (float) $value);
        $power = $value > 0 ? max(0, min((int) floor(log($value, 1024)), count($units) - 1)) : 0;
        return number_format($value / (1024 ** $power), $power > 1 ? 1 : 0) . ' ' . $units[$power];
    };
    $number = fn ($value, $precision = 1) => $value === null ? '—' : number_format((float) $value, $precision);
    $percent = fn ($value) => $value === null ? '—' : number_format((float) $value, 1) . '%';
    $duration = fn ($value) => $value === null ? '—' : number_format((float) $value, 1) . ' ms';
    $temperature = fn ($value) => $value === null ? '—' : number_format((float) $value, 1) . ' °C';
    $frequency = fn ($value) => $value === null ? '—' : number_format((float) $value / 1_000_000_000, 2) . ' GHz';
    $uptime = function ($seconds) {
        if ($seconds === null) return '—';
        $days = intdiv((int) $seconds, 86400);
        $hours = intdiv(((int) $seconds) % 86400, 3600);
        $minutes = intdiv(((int) $seconds) % 3600, 60);
        return $days > 0
            ? $days . 'd ' . $hours . 'h'
            : ($hours > 0 ? $hours . 'h ' . $minutes . 'm' : ($minutes > 0 ? $minutes . 'm' : max(0, (int) $seconds) . 's'));
    };
    $stateClasses = [
        'connected' => 'success',
        'disabled' => 'secondary',
        'not_configured' => 'warning',
        'unavailable' => 'danger',
        'healthy' => 'success',
        'overdue' => 'danger',
        'unknown' => 'warning',
    ];
    $temperatureColor = $server['cpu_temperature_celsius'] === null
        ? 'secondary'
        : ($server['cpu_temperature_celsius'] >= 85 ? 'danger' : ($server['cpu_temperature_celsius'] >= 70 ? 'warning' : 'success'));
    $schedulerColor = $stateClasses[$scheduler['state']] ?? 'secondary';
    $runningContainers = count(array_filter($containers, fn ($container) => ($container['status'] ?? null) === 'running'));
    $connectedComponents = count(array_filter($statuses, fn ($status) => ($status['state'] ?? null) === 'connected'));
    $capacity = function ($used, $total) {
        if ($used === null || $total === null || $total <= 0) return null;
        $used = min(max(0, $used), $total);
        return ['used' => $used, 'free' => $total - $used, 'percent' => round($used / $total * 100, 1)];
    };
    $chartPayload = array_merge($charts, ['overview' => [
        'memory' => $capacity($server['ram_used_bytes'], $server['ram_total_bytes']),
        'disk' => $capacity($server['disk_used_bytes'], $server['disk_total_bytes']),
    ]]);
@endphp

<div class="container-fluid px-0">
    @if (! $monitoringEnabled)
        <div class="alert alert-secondary border-0 shadow-sm d-flex align-items-center" role="status">
            <i class="uil uil-pause-circle fs-4 me-2"></i>
            <div><strong>{{ __('Monitoring is disabled.') }}</strong> {{ __('The application is running normally and will not contact Prometheus.') }}</div>
        </div>
    @endif

    <ul class="nav monitor-nav mb-3">
        @foreach (['overview' => ['Overview', false], 'graphs' => ['Graphs', true], 'server' => ['Server', true], 'requests' => ['Requests', true], 'database' => ['Database', true], 'operations' => ['Jobs & Cron', true], 'containers' => ['Containers', true], 'monitoring-status' => ['Status', true]] as $anchor => [$label, $isDetail])
            <li class="nav-item"><a class="nav-link{{ $isDetail ? ' monitor-detail-link' : '' }}" href="#{{ $anchor }}">{{ __($label) }}</a></li>
        @endforeach
    </ul>

    <section id="overview" class="monitor-section mb-4">
        <div class="row g-3">
            @foreach ([
                ['CPU', $percent($server['cpu_percent']), 'uil-microchip', 'primary'],
                ['CPU temperature', $temperature($server['cpu_temperature_celsius']), 'uil-temperature-half', $temperatureColor],
                ['API requests/min', $number($api['requests_per_minute']), 'uil-exchange', 'success'],
                ['API response', $duration($api['average_duration_ms']), 'uil-stopwatch', 'primary'],
                ['API errors', $percent($api['error_rate_percent']), 'uil-exclamation-octagon', 'danger'],
                ['Cron / scheduler', ucfirst($scheduler['state']), 'uil-clock', $schedulerColor],
            ] as [$label, $value, $icon, $color])
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card monitor-card"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="monitor-label">{{ __($label) }}</div><div class="monitor-kpi mt-2">{{ $value }}</div></div>
                            <span class="rounded-circle bg-soft-{{ $color }} text-{{ $color }} p-2"><i class="uil {{ $icon }} fs-5"></i></span>
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>
        <div class="row g-3 mt-1">
            @foreach ([
                ['resources', 'Resource Usage Trend', 'CPU and memory utilization over time', 'col-xl-8', 'Last 60 minutes'],
                ['memory', 'Memory Usage', 'Current RAM allocation on this server', 'col-xl-4', 'Current snapshot'],
                ['requests', 'Requests & Response Time', 'API traffic volume and average response duration', 'col-xl-8', 'Last 60 minutes'],
                ['disk', 'Disk Storage', 'Used and available space on this server', 'col-xl-4', 'Current snapshot'],
                ['network', 'Network Traffic', 'Incoming and outgoing server throughput', 'col-xl-6', 'Last 60 minutes'],
                ['queues', 'Queue Activity', 'Completed and failed jobs per minute', 'col-xl-6', 'Last 60 minutes'],
            ] as [$chartId, $title, $subtitle, $column, $period])
                <div class="{{ $column }}">
                    <div class="card monitor-card monitor-overview-card">
                        <div class="monitor-chart-heading">
                            <div><h2>{{ __($title) }}</h2><p>{{ __($subtitle) }}</p></div>
                            <span class="monitor-period">{{ __($period) }}</span>
                        </div>
                        <div wire:ignore wire:key="monitor-overview-{{ $chartId }}" id="monitor-overview-{{ $chartId }}" class="monitor-overview-chart" role="img" aria-label="{{ __($title) }}"></div>
                        @if (in_array($chartId, ['memory', 'disk']))
                            @php
                                $total = $server[$chartId === 'memory' ? 'ram_total_bytes' : 'disk_total_bytes'];
                                $allocation = $chartPayload['overview'][$chartId];
                            @endphp
                            <div class="monitor-capacity-legend">
                                <div><span><i class="monitor-legend-dot is-used"></i>{{ __('Used') }}</span><strong>{{ $bytes($allocation['used'] ?? null) }}</strong></div>
                                <div><span><i class="monitor-legend-dot is-free"></i>{{ __('Available') }}</span><strong>{{ $bytes($allocation['free'] ?? null) }}</strong></div>
                                <div class="text-muted"><span>{{ __('Total capacity') }}</span><span>{{ $bytes($total) }}</span></div>
                            </div>
                        @elseif ($chartId === 'queues')
                            <div class="monitor-chart-footer">
                                <span>{{ __('Pending') }} <strong>{{ $number($queues['pending'], 0) }}</strong></span>
                                <span>{{ __('Failed jobs total') }} <strong>{{ $number($queues['failed_total'], 0) }}</strong></span>
                            </div>
                            @if (($queues['unavailable_databases'] ?? 0) > 0)
                                <p class="small text-warning px-4 mb-3">{{ __('Queue totals are incomplete: :count database(s) unavailable.', ['count' => $queues['unavailable_databases']]) }}</p>
                            @endif
                        @else
                            <div class="monitor-chart-footer"><span>{{ __('History from Prometheus') }}</span><a class="monitor-detail-link" href="#{{ ['resources' => 'server', 'requests' => 'requests', 'network' => 'server'][$chartId] }}">{{ __('View details') }} <i class="uil uil-arrow-right"></i></a></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <details id="server" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-server me-2 text-primary"></i>{{ __('Server & Network details') }}</span>
            <span class="monitor-summary-meta"><span>CPU {{ $percent($server['cpu_percent']) }}</span><span>{{ $temperature($server['cpu_temperature_celsius']) }}</span><span>RX {{ $bytes($server['network_receive_bytes_per_second']) }}/s</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="row g-3">
            <div class="col-xl-7">
                <div class="card monitor-card"><div class="card-header">{{ __('Ubuntu Server') }}</div><div class="card-body">
                    <div class="row g-4">
                        <div class="col-sm-4"><div class="monitor-label">{{ __('Load average') }}</div><div class="h5 mt-2">{{ $number($server['load_1'], 2) }} / {{ $number($server['load_5'], 2) }} / {{ $number($server['load_15'], 2) }}</div></div>
                        <div class="col-sm-4"><div class="monitor-label">{{ __('Uptime') }}</div><div class="h5 mt-2">{{ $uptime($server['uptime_seconds']) }}</div></div>
                        <div class="col-sm-4"><div class="monitor-label">{{ __('Swap') }}</div><div class="h5 mt-2">{{ $bytes($server['swap_used_bytes']) }} / {{ $bytes($server['swap_total_bytes']) }}</div></div>
                        <div class="col-sm-4"><div class="monitor-label">{{ __('CPU frequency') }}</div><div class="h5 mt-2">{{ $frequency($server['cpu_frequency_hertz']) }}</div></div>
                        <div class="col-sm-4"><div class="monitor-label">{{ __('Fan speed') }}</div><div class="h5 mt-2">{{ $server['fan_rpm'] === null ? '—' : number_format($server['fan_rpm']) . ' RPM' }}</div></div>
                        <div class="col-sm-4"><div class="monitor-label">{{ __('Inodes used') }}</div><div class="h5 mt-2">{{ $percent($server['filesystem_inode_used_percent']) }}</div></div>
                        <div class="col-sm-6"><div class="monitor-label">{{ __('Disk read / sec') }}</div><div class="h5 mt-2">{{ $bytes($server['disk_read_bytes_per_second']) }}</div></div>
                        <div class="col-sm-6"><div class="monitor-label">{{ __('Disk write / sec') }}</div><div class="h5 mt-2">{{ $bytes($server['disk_write_bytes_per_second']) }}</div></div>
                        @if ($server['temperature_sensors'] !== [])
                            <div class="col-12">
                                <div class="monitor-label mb-2">{{ __('CPU thermal sensors') }}</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($server['temperature_sensors'] as $sensor)
                                        @php $sensorColor = $sensor['temperature_celsius'] >= 85 ? 'danger' : ($sensor['temperature_celsius'] >= 70 ? 'warning' : 'success'); @endphp
                                        <span class="badge bg-soft-{{ $sensorColor }} text-{{ $sensorColor }}">{{ $sensor['name'] }}: {{ $temperature($sensor['temperature_celsius']) }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div></div>
            </div>
            <div class="col-xl-5" id="network">
                <div class="card monitor-card"><div class="card-header">{{ __('Network') }}</div><div class="card-body">
                    <div class="row g-4">
                        <div class="col-6"><div class="monitor-label">{{ __('Host RX / sec') }}</div><div class="h5 mt-2 text-success"><i class="uil uil-arrow-down me-1"></i>{{ $bytes($server['network_receive_bytes_per_second']) }}</div></div>
                        <div class="col-6"><div class="monitor-label">{{ __('Host TX / sec') }}</div><div class="h5 mt-2 text-primary"><i class="uil uil-arrow-up me-1"></i>{{ $bytes($server['network_transmit_bytes_per_second']) }}</div></div>
                        <div class="col-6"><div class="monitor-label">{{ __('HTTP requests / sec') }}</div><div class="h5 mt-2">{{ $number($http['requests_per_second'], 2) }}</div></div>
                        <div class="col-6"><div class="monitor-label">{{ __('Active HTTP') }}</div><div class="h5 mt-2">{{ $number($http['active_requests'], 0) }}</div></div>
                        <div class="col-6"><div class="monitor-label">{{ __('RX errors / drops') }}</div><div class="h6 mt-2 {{ ($server['network_receive_errors_per_second'] ?? 0) + ($server['network_receive_drops_per_second'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $number($server['network_receive_errors_per_second'], 3) }} / {{ $number($server['network_receive_drops_per_second'], 3) }}</div></div>
                        <div class="col-6"><div class="monitor-label">{{ __('TX errors / drops') }}</div><div class="h6 mt-2 {{ ($server['network_transmit_errors_per_second'] ?? 0) + ($server['network_transmit_drops_per_second'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $number($server['network_transmit_errors_per_second'], 3) }} / {{ $number($server['network_transmit_drops_per_second'], 3) }}</div></div>
                    </div>
                </div></div>
            </div>
        </div>
        </div>
    </details>

    <details id="graphs" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-chart-line me-2 text-primary"></i>{{ __('Historical graphs') }}</span>
            <span class="monitor-summary-meta"><span>{{ __('Last 60 minutes') }}</span><span>4 charts</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="row g-3">
            <div class="col-xl-6"><div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('System performance') }}</span><span class="small text-muted">{{ __('Last 60 minutes') }}</span></div><div class="card-body"><div wire:ignore wire:key="monitor-system-chart" id="monitor-system-chart" class="monitor-chart"></div></div></div></div>
            <div class="col-xl-6"><div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('Network throughput') }}</span><span class="small text-muted">Mbps</span></div><div class="card-body"><div wire:ignore wire:key="monitor-network-chart" id="monitor-network-chart" class="monitor-chart"></div></div></div></div>
            <div class="col-xl-6"><div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('API and HTTP status') }}</span><span class="small text-muted">{{ __('Per minute') }}</span></div><div class="card-body"><div wire:ignore wire:key="monitor-api-chart" id="monitor-api-chart" class="monitor-chart"></div></div></div></div>
            <div class="col-xl-6"><div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('Queue activity') }}</span><span class="small text-muted">{{ __('Per minute') }}</span></div><div class="card-body"><div wire:ignore wire:key="monitor-queue-chart" id="monitor-queue-chart" class="monitor-chart"></div></div></div></div>
        </div>
        </div>
    </details>

    <details id="requests" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-exchange me-2 text-primary"></i>{{ __('Request details') }}</span>
            <span class="monitor-summary-meta"><span>{{ $number($api['requests_per_minute']) }}/min</span><span>{{ $duration($api['average_duration_ms']) }}</span><span class="{{ ($api['error_rate_percent'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $percent($api['error_rate_percent']) }} errors</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="row g-3">
            <div class="col-xl-4"><div class="card monitor-card"><div class="card-header">{{ __('Caddy HTTP') }}</div><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7 text-muted">{{ __('Total requests') }}</dt><dd class="col-5 text-end">{{ $number($http['total_requests'], 0) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Average duration') }}</dt><dd class="col-5 text-end">{{ $duration($http['average_duration_ms']) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Average response size') }}</dt><dd class="col-5 text-end">{{ $bytes($http['average_response_bytes']) }}</dd>
                    @forelse ($http['status_codes'] as $code => $rate)
                        <dt class="col-7 text-muted">HTTP {{ $code }}</dt><dd class="col-5 text-end">{{ $number($rate, 2) }}/s</dd>
                    @empty
                        <dd class="col-12 text-muted mb-0">{{ __('No Caddy samples yet.') }}</dd>
                    @endforelse
                </dl>
            </div></div></div>
            <div class="col-xl-4"><div class="card monitor-card"><div class="card-header">{{ __('Outgoing HTTP') }}</div><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7 text-muted">{{ __('Requests / min') }}</dt><dd class="col-5 text-end">{{ $number($api['outgoing_requests_per_minute']) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Average duration') }}</dt><dd class="col-5 text-end">{{ $duration($api['outgoing_average_duration_ms']) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Failures / min') }}</dt><dd class="col-5 text-end {{ ($api['outgoing_failures_per_minute'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $number($api['outgoing_failures_per_minute']) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Active app requests') }}</dt><dd class="col-5 text-end">{{ $number($api['active_requests'], 0) }}</dd>
                    <dt class="col-7 text-muted">{{ __('Peak request memory') }}</dt><dd class="col-5 text-end">{{ $bytes($api['peak_memory_bytes']) }}</dd>
                </dl>
            </div></div></div>
            <div class="col-xl-4"><div class="card monitor-card"><div class="card-header">{{ __('Slow API Endpoints') }}</div><div class="table-responsive">
                <table class="table monitor-table"><thead><tr><th>{{ __('Method') }}</th><th>{{ __('Route') }}</th><th class="text-end">{{ __('Average') }}</th></tr></thead><tbody>
                @forelse ($api['slow_endpoints'] as $endpoint)
                    <tr><td><span class="badge bg-soft-primary text-primary">{{ $endpoint['method'] }}</span></td><td>{{ $endpoint['route'] }}</td><td class="text-end">{{ $duration($endpoint['duration_ms']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No API samples yet.') }}</td></tr>
                @endforelse
                </tbody></table>
            </div></div></div>
            <div class="col-12"><div class="card monitor-card"><div class="card-header">{{ __('Recent Slow Requests') }}</div><div class="table-responsive">
                <table class="table monitor-table"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Method') }}</th><th>{{ __('Route') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Duration') }}</th></tr></thead><tbody>
                @forelse ($recentSlowRequests as $request)
                    <tr><td>{{ $request['at'] }}</td><td>{{ $request['method'] }}</td><td>{{ $request['route'] }}</td><td>{{ $request['status'] }}</td><td class="text-end">{{ $duration($request['duration_ms']) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No slow requests recorded.') }}</td></tr>
                @endforelse
                </tbody></table>
            </div></div></div>
        </div>
        </div>
    </details>

    <details id="database" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-database me-2 text-primary"></i>{{ __('Database & slow SQL') }}</span>
            <span class="monitor-summary-meta"><span class="text-{{ ($services['database']['state'] ?? null) === 'connected' ? 'success' : 'danger' }}">{{ ucfirst($services['database']['state'] ?? 'unknown') }}</span><span>{{ $number($database['slow_queries_per_minute']) }} slow/min</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('Database / Slow SQL') }}</span><span class="badge bg-soft-warning text-warning">{{ $number($database['slow_queries_per_minute']) }}/min</span></div><div class="table-responsive">
            <table class="table monitor-table"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Connection') }}</th><th>{{ __('Query template') }}</th><th class="text-end">{{ __('Duration') }}</th></tr></thead><tbody>
            @forelse ($database['recent'] as $query)
                <tr><td>{{ $query['at'] }}</td><td>{{ $query['connection'] }}</td><td class="text-truncate" style="max-width: 620px">{{ $query['query'] }}</td><td class="text-end">{{ $duration($query['duration_ms']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No slow SQL queries recorded.') }}</td></tr>
            @endforelse
            </tbody></table>
        </div></div>
        </div>
    </details>

    <details id="operations" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-setting me-2 text-primary"></i>{{ __('Errors, queues & cron') }}</span>
            <span class="monitor-summary-meta"><span class="{{ ($queues['failed_total'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $number($queues['failed_total'], 0) }} failed jobs</span><span>{{ $number($queues['pending'], 0) }} pending</span><span class="text-{{ $schedulerColor }}">Cron {{ ucfirst($scheduler['state']) }}</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="row g-3">
        <section id="errors" class="monitor-section col-xl-7">
            <div class="card monitor-card"><div class="card-header d-flex justify-content-between"><span>{{ __('Errors') }}</span><span class="badge bg-soft-danger text-danger">{{ $number($errors['per_minute']) }}/min</span></div><div class="table-responsive">
                <table class="table monitor-table"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Exception class') }}</th></tr></thead><tbody>
                @forelse ($errors['recent'] as $error)
                    <tr><td>{{ $error['at'] }}</td><td>{{ $error['class'] }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-4">{{ __('No exceptions recorded.') }}</td></tr>
                @endforelse
                </tbody></table>
            </div></div>
        </section>
        <section id="queues" class="monitor-section col-xl-5">
            <div class="card monitor-card"><div class="card-header">{{ __('Queues') }}</div><div class="card-body">
                <div class="small text-muted mb-3">{{ __('Totals include central and tenant databases.') }}</div>
                @if (($queues['unavailable_databases'] ?? 0) > 0)
                    <div class="alert alert-warning py-2">{{ __('Queue totals are incomplete: :count database(s) could not be checked.', ['count' => $queues['unavailable_databases']]) }}</div>
                @endif
                <div class="row g-4">
                    <div class="col-6"><div class="monitor-label">{{ __('Processed / min') }}</div><div class="monitor-kpi text-success mt-2">{{ $number($queues['processed_per_minute']) }}</div></div>
                    <div class="col-6"><div class="monitor-label">{{ __('Failed / min') }}</div><div class="monitor-kpi text-danger mt-2">{{ $number($queues['failed_per_minute']) }}</div></div>
                    <div class="col-6"><div class="monitor-label">{{ __('Pending jobs') }}</div><div class="h5 mt-2">{{ $number($queues['pending'], 0) }}</div></div>
                    <div class="col-6"><div class="monitor-label">{{ __('Failed jobs total') }}</div><div class="h5 mt-2 {{ ($queues['failed_total'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $number($queues['failed_total'], 0) }}</div></div>
                    <div class="col-6"><div class="monitor-label">{{ __('Queue connection') }}</div><div class="h6 mt-2">{{ $queues['connection'] }}</div></div>
                    <div class="col-6">
                        <div class="monitor-label">{{ __('Scheduler / cron') }}</div>
                        <div class="mt-2"><span class="badge bg-soft-{{ $schedulerColor }} text-{{ $schedulerColor }}">{{ ucfirst($scheduler['state']) }}</span></div>
                        <div class="small text-muted mt-1">{{ $scheduler['seconds_since_last_run'] === null ? __('No heartbeat yet') : __('Last run :time ago', ['time' => $uptime($scheduler['seconds_since_last_run'])]) }}</div>
                    </div>
                </div>
            </div></div>
        </section>
        </div>
        <div class="row g-3 mt-0">
            <section id="cron" class="monitor-section col-xl-7">
                <div class="card monitor-card"><div class="card-header d-flex justify-content-between align-items-center"><span>{{ __('Scheduled tasks') }}</span><span class="badge bg-soft-{{ $schedulerColor }} text-{{ $schedulerColor }}">{{ count($scheduler['tasks'] ?? []) }} configured</span></div><div class="table-responsive">
                    <table class="table monitor-table"><thead><tr><th>{{ __('Task') }}</th><th>{{ __('Cron expression') }}</th><th>{{ __('Next run') }}</th><th>{{ __('Timezone') }}</th></tr></thead><tbody>
                    @forelse (($scheduler['tasks'] ?? []) as $task)
                        <tr><td class="fw-semibold">{{ $task['name'] }}</td><td><code>{{ $task['expression'] }}</code></td><td>{{ $task['next_run_at'] }}</td><td>{{ $task['timezone'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ $monitoringEnabled ? __('No scheduled tasks configured.') : __('Enable monitoring to list scheduled tasks.') }}</td></tr>
                    @endforelse
                    </tbody></table>
                </div></div>
            </section>
            <section class="monitor-section col-xl-5">
                <div class="card monitor-card"><div class="card-header">{{ __('Recent cron runs') }}</div><div class="table-responsive">
                    <table class="table monitor-table"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Task') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Duration') }}</th></tr></thead><tbody>
                    @forelse (($scheduler['recent'] ?? []) as $run)
                        @php $runColor = ($run['status'] ?? null) === 'completed' ? 'success' : (($run['status'] ?? null) === 'failed' ? 'danger' : 'warning'); @endphp
                        <tr><td>{{ $run['at'] }}</td><td class="fw-semibold">{{ $run['task'] }}</td><td><span class="badge bg-soft-{{ $runColor }} text-{{ $runColor }}">{{ ucfirst($run['status']) }}</span></td><td class="text-end">{{ $duration($run['duration_ms']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Waiting for the next scheduled run.') }}</td></tr>
                    @endforelse
                    </tbody></table>
                </div></div>
            </section>
        </div>
        </div>
    </details>

    <details id="containers" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-cube me-2 text-primary"></i>{{ __('Docker containers') }}</span>
            <span class="monitor-summary-meta"><span class="text-success">{{ $runningContainers }} running</span><span>{{ count($containers) }} total</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="card monitor-card"><div class="card-header">{{ __('Docker Containers') }}</div><div class="table-responsive">
            <table class="table monitor-table"><thead><tr><th>{{ __('Container') }}</th><th>{{ __('Status') }}</th><th>{{ __('Uptime') }}</th><th class="text-end">CPU</th><th class="text-end">RAM</th><th class="text-end">RX / TX</th><th class="text-end">{{ __('Disk read / write') }}</th></tr></thead><tbody>
            @forelse ($containers as $container)
                <tr>
                    <td class="fw-semibold">{{ $container['name'] }}</td>
                    <td><span class="badge bg-soft-{{ $container['status'] === 'running' ? 'success' : 'warning' }} text-{{ $container['status'] === 'running' ? 'success' : 'warning' }}">{{ ucfirst($container['status']) }}</span></td>
                    <td>{{ $uptime($container['uptime_seconds'] ?? null) }}</td>
                    <td class="text-end">{{ $percent($container['cpu_percent'] ?? null) }}</td>
                    <td class="text-end">{{ $bytes($container['memory_bytes'] ?? null) }}</td>
                    <td class="text-end">{{ $bytes($container['network_receive_bytes_per_second'] ?? null) }} / {{ $bytes($container['network_transmit_bytes_per_second'] ?? null) }}</td>
                    <td class="text-end">{{ $bytes($container['disk_read_bytes_per_second'] ?? null) }} / {{ $bytes($container['disk_write_bytes_per_second'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No container samples available.') }}</td></tr>
            @endforelse
            </tbody></table>
        </div></div>
        </div>
    </details>

    <details id="monitoring-status" wire:ignore.self class="monitor-details mb-3">
        <summary>
            <span><i class="uil uil-check-circle me-2 text-primary"></i>{{ __('Monitoring & service status') }}</span>
            <span class="monitor-summary-meta"><span class="text-success">{{ $connectedComponents }} connected</span><span>MySQL {{ ucfirst($services['database']['state'] ?? 'unknown') }}</span><span>Pulse {{ ucfirst(collect($statuses)->firstWhere('name', 'Laravel Pulse')['state'] ?? 'unknown') }}</span></span>
        </summary>
        <div class="monitor-details-body">
        <div class="card monitor-card"><div class="card-header">{{ __('Monitoring Status') }}</div><div class="card-body">
            <div class="row g-3">
                @foreach ($statuses as $status)
                    @php $color = $stateClasses[$status['state']] ?? 'secondary'; @endphp
                    <div class="col-xl-4 col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center"><strong>{{ $status['name'] }}</strong><span class="badge bg-soft-{{ $color }} text-{{ $color }}"><span class="status-dot bg-{{ $color }}"></span>{{ ucwords(str_replace('_', ' ', $status['state'])) }}</span></div>
                            @if ($status['detail'])<div class="small text-muted mt-2">{{ $status['detail'] }}</div>@endif
                            @if ($status['name'] === 'Laravel Pulse' && $status['state'] === 'connected' && Route::has('pulse'))
                                <a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('pulse') }}">{{ __('Open Pulse Dashboard') }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
                @foreach ($services as $serviceName => $service)
                    @php $color = $stateClasses[$service['state']] ?? 'secondary'; @endphp
                    <div class="col-xl-4 col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center"><strong>{{ $serviceName === 'database' ? 'MySQL / Database' : ucfirst($serviceName) }}</strong><span class="badge bg-soft-{{ $color }} text-{{ $color }}"><span class="status-dot bg-{{ $color }}"></span>{{ ucwords(str_replace('_', ' ', $service['state'])) }}</span></div>
                            <div class="small text-muted mt-2">{{ $service['detail'] }}</div>
                        </div>
                    </div>
                @endforeach
                <div class="col-xl-4 col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center"><strong>{{ __('Laravel Scheduler') }}</strong><span class="badge bg-soft-{{ $schedulerColor }} text-{{ $schedulerColor }}"><span class="status-dot bg-{{ $schedulerColor }}"></span>{{ ucfirst($scheduler['state']) }}</span></div>
                        <div class="small text-muted mt-2">{{ $scheduler['seconds_since_last_run'] === null ? __('Waiting for the first cron heartbeat.') : __('Heartbeat received :time ago.', ['time' => $uptime($scheduler['seconds_since_last_run'])]) }}</div>
                    </div>
                </div>
            </div>
        </div></div>
        </div>
    </details>

    <script id="monitoring-chart-data" type="application/json">@json($chartPayload)</script>
</div>
