@extends('layouts.app')

@section('title', __('Monitoring'))
@section('page_title', __('Monitoring'))

@push('styles')
@livewireStyles
<style>
    .monitor-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.04); height: 100%; }
    .monitor-card .card-header { background: transparent; border-bottom: 1px solid #eef2f7; font-weight: 700; padding: .7rem .85rem; }
    .monitor-card .card-body { padding: .8rem .85rem; }
    .monitor-kpi { font-size: 1.3rem; font-weight: 750; color: #0f172a; line-height: 1.2; }
    .monitor-label { color: #64748b; font-size: .73rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .monitor-nav { gap: .25rem; overflow-x: auto; flex-wrap: nowrap; padding-bottom: .35rem; }
    .monitor-nav .nav-link { white-space: nowrap; color: #475569; border-radius: 8px; font-weight: 600; font-size: .78rem; padding: .35rem .6rem; }
    .monitor-nav .nav-link:hover { background: #eef2ff; color: #4f46e5; }
    .status-dot { width: .55rem; height: .55rem; border-radius: 50%; display: inline-block; margin-right: .35rem; }
    .monitor-table { margin-bottom: 0; }
    .monitor-table th { color: #64748b; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .monitor-table td { vertical-align: middle; white-space: nowrap; }
    .monitor-section { scroll-margin-top: 90px; }
    .monitor-live-bar { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .monitor-live-dot { width: .55rem; height: .55rem; border-radius: 50%; display: inline-block; background: #22c55e; box-shadow: 0 0 0 .2rem rgba(34,197,94,.13); }
    .monitor-live-dot.is-paused { background: #94a3b8; box-shadow: none; }
    .monitor-live-dot.is-error { background: #ef4444; box-shadow: 0 0 0 .2rem rgba(239,68,68,.12); }
    .monitor-chart { min-height: 210px; }
    .monitor-overview-card { border-radius: 16px; overflow: hidden; margin-bottom: 0; }
    .monitor-chart-heading { padding: 1.4rem 1.5rem .5rem; display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; }
    .monitor-chart-heading h2 { font-size: 1rem; font-weight: 700; color: #172033; margin: 0 0 .4rem; }
    .monitor-chart-heading p { font-size: .82rem; color: #7b8ba4; margin: 0; }
    .monitor-period { color: #635bff; border: 1px solid #d7dcff; background: #f1f3ff; border-radius: 6px; padding: .2rem .55rem; font-size: .7rem; white-space: nowrap; }
    .monitor-overview-chart { min-height: 290px; padding: .5rem 1rem 0; }
    .monitor-capacity-legend { padding: 0 1.5rem 1.1rem; font-size: .8rem; }
    .monitor-capacity-legend > div { display: flex; justify-content: space-between; gap: .8rem; padding: .5rem 0; border-bottom: 1px solid #f1f4f8; }
    .monitor-capacity-legend > div:last-child { border: 0; }
    .monitor-legend-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .5rem; }
    .monitor-legend-dot.is-used { background: #5145e7; }
    .monitor-legend-dot.is-free { background: #dfe4f5; }
    .monitor-chart-footer { display: flex; flex-wrap: wrap; gap: .8rem; justify-content: space-between; margin: 0 1.5rem; padding: .85rem 0 1.1rem; border-top: 1px solid #f1f4f8; font-size: .75rem; color: #7b8ba4; }
    .monitor-chart-footer strong { color: #26364a; margin-left: .35rem; }
    @media (max-width: 575.98px) { .monitor-chart-heading { padding: 1rem 1rem .4rem; flex-wrap: wrap; } .monitor-overview-chart { padding: .5rem .25rem 0; } .monitor-kpi { font-size: 1.1rem; } }
    .monitor-details { border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; overflow: hidden; scroll-margin-top: 90px; }
    .monitor-details + .monitor-details { margin-top: .65rem; }
    .monitor-details > summary { list-style: none; cursor: pointer; padding: .72rem .9rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem; font-weight: 700; color: #26364a; user-select: none; }
    .monitor-details > summary::-webkit-details-marker { display: none; }
    .monitor-details > summary::after { content: '\e9c2'; font-family: unicons-line; font-size: 1rem; color: #8793a2; transition: transform .18s ease; }
    .monitor-details[open] > summary { border-bottom: 1px solid #eef2f7; }
    .monitor-details[open] > summary::after { transform: rotate(180deg); }
    .monitor-details-body { padding: .75rem; background: #fbfcfe; }
    .monitor-summary-meta { margin-left: auto; display: flex; align-items: center; gap: .65rem; color: #718096; font-size: .74rem; font-weight: 600; white-space: nowrap; }
    .monitor-details .table-responsive { max-height: 420px; }
    @media (max-width: 575.98px) { .monitor-summary-meta { display: none; } .monitor-details-body { padding: .5rem; } }
</style>
@endpush

@section('content')
    <livewire:admin.monitoring-dashboard />
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('livewire:initialized', () => {
    const componentRoot = document.getElementById('monitoring-component');
    const component = Livewire.find(componentRoot.getAttribute('wire:id'));
    const root = document.getElementById('monitoring-dashboard-content');
    const state = document.getElementById('monitor-live-state');
    const dot = document.getElementById('monitor-live-dot');
    const updated = document.getElementById('monitor-live-updated');
    const refreshButton = document.getElementById('monitor-refresh-now');
    const toggleButton = document.getElementById('monitor-toggle-live');
    const toggleLabel = toggleButton.querySelector('span');
    const intervalMilliseconds = Number(componentRoot.dataset.refreshSeconds) * 1000;
    let paused = componentRoot.dataset.liveEnabled !== 'true';
    let loading = false;
    const chartInstances = new Map();
    let preferenceVersion = 0;

    component.$interceptRequest(({ onError }) => {
        onError(({ preventDefault }) => preventDefault());
    });

    const labels = {
        live: @json(__('Live')),
        paused: @json(__('Paused')),
        unavailable: @json(__('Refresh unavailable')),
        pause: @json(__('Pause')),
        resume: @json(__('Resume')),
    };

    function showState(kind) {
        dot.classList.toggle('is-paused', kind === 'paused');
        dot.classList.toggle('is-error', kind === 'error');
        state.textContent = kind === 'paused' ? labels.paused : (kind === 'error' ? labels.unavailable : labels.live);
    }

    function showToggleState() {
        toggleButton.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggleButton.querySelector('i').className = paused ? 'uil uil-play me-1' : 'uil uil-pause me-1';
        toggleLabel.textContent = paused ? labels.resume : labels.pause;
        showState(paused ? 'paused' : 'live');
    }

    function renderMonitoringCharts() {
        if (typeof ApexCharts === 'undefined') return;

        const graphPanel = document.getElementById('graphs');

        const source = document.getElementById('monitoring-chart-data');
        if (! source) return;

        let data;
        try {
            data = JSON.parse(source.textContent || '{}');
        } catch (error) {
            return;
        }

        const chartBase = {
            chart: { height: 210, type: 'line', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, fontFamily: 'inherit' },
            dataLabels: { enabled: false },
            stroke: { width: 2.5, curve: 'smooth' },
            markers: { size: 0 },
            grid: { borderColor: '#edf1f5', strokeDashArray: 4 },
            xaxis: { type: 'datetime', labels: { datetimeUTC: false, style: { colors: '#8793a2', fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
            noData: { text: @json(__('No historical samples yet')), align: 'center', verticalAlign: 'middle', style: { color: '#8793a2' } },
            tooltip: { shared: true, intersect: false, x: { format: 'HH:mm' } },
        };

        const updates = [];
        const render = (selector, options) => {
            const element = document.querySelector(selector);
            if (! element) return;
            const series = JSON.stringify(options.series);
            const existing = chartInstances.get(selector);
            if (existing) {
                if (existing.series === series) return;
                existing.series = series;
                existing.ready = existing.ready.then(() => options.chart?.type === 'donut'
                    ? existing.chart.updateOptions({ series: options.series, plotOptions: options.plotOptions, tooltip: options.tooltip }, false, false)
                    : existing.chart.updateSeries(options.series, false));
                updates.push(existing.ready);
                return;
            }
            const chart = new ApexCharts(element, { ...chartBase, ...options });
            const ready = chart.render();
            chartInstances.set(selector, { chart, ready, series });
            updates.push(ready);
        };

        const overviewBase = {
            ...chartBase,
            chart: { ...chartBase.chart, height: 290 },
            legend: { ...chartBase.legend, fontSize: '12px', markers: { width: 9, height: 9 }, itemMargin: { horizontal: 10 } },
            yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: '#9ba7ba' }, formatter: value => Number(value).toFixed(1) } },
            xaxis: { ...chartBase.xaxis, tickAmount: 6, labels: { ...chartBase.xaxis.labels, format: 'HH:mm', hideOverlappingLabels: true } },
        };
        const renderOverview = (id, options) => render(`#monitor-overview-${id}`, { ...overviewBase, ...options });
        const toMbps = points => (points || []).map(point => ({ x: point.x, y: Number((point.y * 8 / 1000000).toFixed(3)) }));

        renderOverview('resources', {
            chart: { ...overviewBase.chart, type: 'area' },
            series: [
                { name: @json(__('CPU')), data: data.cpu_percent || [] },
                { name: @json(__('RAM')), data: data.ram_percent || [] },
            ],
            colors: ['#5145e7', '#10b981'],
            fill: { type: 'gradient', gradient: { opacityFrom: .22, opacityTo: .02 } },
            yaxis: { ...overviewBase.yaxis, forceNiceScale: false, max: 100, tickAmount: 4, labels: { ...overviewBase.yaxis.labels, formatter: value => `${Number(value).toFixed(0)}%` } },
        });

        ['memory', 'disk'].forEach(id => {
            const capacity = data.overview?.[id];
            renderOverview(id, {
                chart: { ...overviewBase.chart, type: 'donut' },
                series: capacity ? [capacity.used, capacity.free] : [],
                labels: [@json(__('Used')), @json(__('Available'))],
                colors: ['#5145e7', '#dfe4f5'],
                stroke: { width: 2, colors: ['#ffffff'] },
                legend: { show: false },
                plotOptions: { pie: { expandOnClick: false, donut: { size: '72%', labels: {
                    show: true,
                    name: { fontSize: '14px', color: '#7b8ba4' },
                    value: { fontSize: '30px', color: '#172033', formatter: value => `${(Number(value) / ((capacity?.used + capacity?.free) || 1) * 100).toFixed(1)}%` },
                    total: { show: true, showAlways: true, label: @json(__('Used capacity')), color: '#7b8ba4', formatter: () => `${capacity?.percent ?? 0}%` },
                } } } },
                tooltip: { y: { formatter: value => {
                    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    const power = value > 0 ? Math.max(0, Math.min(Math.floor(Math.log(value) / Math.log(1024)), 4)) : 0;
                    return `${(value / 1024 ** power).toFixed(power > 1 ? 1 : 0)} ${units[power]}`;
                } } },
                noData: { ...chartBase.noData, text: @json(__('No capacity samples available')) },
            });
        });

        renderOverview('requests', {
            series: [
                { name: @json(__('Requests / min')), type: 'column', data: data.api_requests_per_minute || [] },
                { name: @json(__('Response time')), type: 'line', data: data.api_average_duration_ms || [] },
            ],
            colors: ['#5145e7', '#10b981'],
            stroke: { width: [0, 3], curve: 'straight' },
            plotOptions: { bar: { columnWidth: '45%', borderRadius: 2 } },
            fill: { opacity: [.8, 1] },
            yaxis: [
                { ...overviewBase.yaxis, seriesName: @json(__('Requests / min')), title: { text: @json(__('Requests / min')), style: { color: '#9ba7ba', fontWeight: 400 } } },
                { ...overviewBase.yaxis, seriesName: @json(__('Response time')), opposite: true, title: { text: 'ms', style: { color: '#9ba7ba', fontWeight: 400 } } },
            ],
            tooltip: { ...chartBase.tooltip, y: [
                { formatter: value => `${Number(value).toFixed(1)} / min` },
                { formatter: value => `${Number(value).toFixed(1)} ms` },
            ] },
        });

        renderOverview('network', {
            chart: { ...overviewBase.chart, type: 'area' },
            series: [
                { name: @json(__('Receive')), data: toMbps(data.network_receive_bytes_per_second) },
                { name: @json(__('Transmit')), data: toMbps(data.network_transmit_bytes_per_second) },
            ],
            colors: ['#5145e7', '#10b981'],
            fill: { type: 'gradient', gradient: { opacityFrom: .22, opacityTo: .02 } },
            yaxis: { ...overviewBase.yaxis, title: { text: 'Mbps', style: { color: '#9ba7ba', fontWeight: 400 } } },
            tooltip: { ...chartBase.tooltip, y: { formatter: value => `${Number(value).toFixed(3)} Mbps` } },
        });

        renderOverview('queues', {
            series: [
                { name: @json(__('Processed')), data: data.queue_processed_per_minute || [] },
                { name: @json(__('Failed')), data: data.queue_failed_per_minute || [] },
            ],
            colors: ['#10b981', '#ef4444'],
            stroke: { width: 2.5, curve: 'straight' },
            yaxis: { ...overviewBase.yaxis, title: { text: @json(__('Jobs / min')), style: { color: '#9ba7ba', fontWeight: 400 } } },
            tooltip: { ...chartBase.tooltip, y: { formatter: value => `${Number(value).toFixed(1)} / min` } },
        });

        if (graphPanel && ! graphPanel.open) return Promise.all(updates);

        render('#monitor-system-chart', {
            series: [
                { name: 'CPU %', data: data.cpu_percent || [] },
                { name: 'RAM %', data: data.ram_percent || [] },
                { name: 'CPU °C', data: data.cpu_temperature_celsius || [] },
            ],
            colors: ['#4f46e5', '#0ea5e9', '#f59e0b'],
            yaxis: { min: 0, max: 100, tickAmount: 5, labels: { formatter: value => Number(value).toFixed(0) } },
        });

        render('#monitor-network-chart', {
            chart: { ...chartBase.chart, type: 'area' },
            series: [
                { name: 'Receive Mbps', data: toMbps(data.network_receive_bytes_per_second) },
                { name: 'Transmit Mbps', data: toMbps(data.network_transmit_bytes_per_second) },
            ],
            colors: ['#10b981', '#6366f1'],
            fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .03 } },
            yaxis: { min: 0, labels: { formatter: value => Number(value).toFixed(2) } },
        });

        render('#monitor-api-chart', {
            series: [
                { name: 'Requests', data: data.api_requests_per_minute || [] },
                { name: 'HTTP 4xx', data: data.http_4xx_per_minute || [] },
                { name: 'HTTP 5xx', data: data.http_5xx_per_minute || [] },
            ],
            colors: ['#4f46e5', '#f59e0b', '#ef4444'],
            yaxis: { min: 0, labels: { formatter: value => Number(value).toFixed(1) } },
        });

        render('#monitor-queue-chart', {
            chart: { ...chartBase.chart, type: 'area' },
            series: [
                { name: 'Processed', data: data.queue_processed_per_minute || [] },
                { name: 'Failed', data: data.queue_failed_per_minute || [] },
            ],
            colors: ['#10b981', '#ef4444'],
            fill: { type: 'gradient', gradient: { opacityFrom: .25, opacityTo: .02 } },
            yaxis: { min: 0, labels: { formatter: value => Number(value).toFixed(1) } },
        });
        return Promise.all(updates);
    }

    function bindDetailPanels() {
        const graphPanel = root.querySelector('#graphs');
        if (! graphPanel) return;

        graphPanel.addEventListener('toggle', () => {
            renderMonitoringCharts();
        });
    }

    root.addEventListener('click', event => {
        const link = event.target.closest('.monitor-detail-link');
        if (! link) return;

        const panel = root.querySelector(link.getAttribute('href'));
        if (! panel || panel.tagName !== 'DETAILS') return;

        event.preventDefault();
        panel.open = true;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    async function refreshDashboard(force = false) {
        if ((! force && paused) || loading || document.hidden) return;

        loading = true;
        refreshButton.disabled = true;

        const version = preferenceVersion;
        try {
            const result = await component.refreshDashboard(force);
            if (version === preferenceVersion) paused = ! result.liveEnabled;
            showToggleState();
            if (! result.refreshed) return false;

            await renderMonitoringCharts();
            updated.textContent = result.updatedAt;
            showState(paused ? 'paused' : 'live');
            return true;
        } catch (error) {
            showState('error');
            return false;
        } finally {
            loading = false;
            refreshButton.disabled = false;
        }
    }

    refreshButton.addEventListener('click', () => {
        refreshDashboard(true).then((succeeded) => {
            if (succeeded) showToggleState();
        });
    });

    toggleButton.addEventListener('click', async () => {
        const nextPaused = ! paused;
        paused = nextPaused;
        preferenceVersion++;
        toggleButton.disabled = true;
        showToggleState();

        try {
            await component.setLiveEnabled(! nextPaused);

            if (! paused) refreshDashboard();
        } catch (error) {
            paused = true;
            showToggleState();
            showState('error');
        } finally {
            toggleButton.disabled = false;
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (! document.hidden && ! paused) refreshDashboard();
    });

    window.setInterval(refreshDashboard, intervalMilliseconds);
    showToggleState();
    bindDetailPanels();
    renderMonitoringCharts();
});
</script>
@livewireScripts
@endpush
