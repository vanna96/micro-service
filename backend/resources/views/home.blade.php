@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page_title', __('Dashboard'))

@php
    $tenantName = $selectedTenant ? (admin_tenant_display_name($selectedTenant) ?: __('Selected tenant')) : __('No tenant selected');
    $firstName = trim((string) auth()->user()->name);
    $firstName = $firstName !== '' ? explode(' ', $firstName)[0] : __('there');
    $hour = now()->hour;
    $greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
    $money = fn ($value) => $dashboard['currency_symbol'] . number_format((float) $value, $dashboard['currency_decimals']);
    $revenueUp = $dashboard['revenue_trend'] >= 0;
    $ordersUp = $dashboard['orders_trend'] >= 0;
    $healthyItems = max($dashboard['active_items'] - $dashboard['low_stock_items'] - $dashboard['out_of_stock_items'], 0);
    $topUnits = max((float) ($dashboard['top_items']->max('units') ?? 0), 1);
@endphp

@push('styles')
<style>
    .operations-dashboard {
        --dashboard-navy: #173754;
        --dashboard-navy-dark: #102a42;
        --dashboard-orange: #f5a524;
        --dashboard-orange-soft: #fff4df;
        --dashboard-blue-soft: #edf5fb;
        --dashboard-border: #e5ebf2;
        --dashboard-muted: #738195;
        color: #263447;
    }

    .operations-dashboard .dashboard-card {
        background: #fff;
        border: 1px solid var(--dashboard-border);
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(24, 55, 84, .06);
        height: 100%;
        overflow: hidden;
    }

    .operations-dashboard .dashboard-hero {
        background: radial-gradient(circle at 82% 12%, rgba(245, 165, 36, .32), transparent 25%), linear-gradient(122deg, var(--dashboard-navy-dark), var(--dashboard-navy));
        border: 0;
        color: #fff;
        padding: 26px 30px;
        position: relative;
    }

    .operations-dashboard .dashboard-hero::after {
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 50%;
        content: '';
        height: 160px;
        position: absolute;
        right: 8%;
        top: -82px;
        width: 160px;
    }

    .operations-dashboard .eyebrow {
        color: rgba(255, 255, 255, .66);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .13em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .operations-dashboard .dashboard-hero h2 {
        color: #fff;
        font-size: clamp(1.35rem, 2.5vw, 1.9rem);
        font-weight: 700;
        margin-bottom: 8px;
    }

    .operations-dashboard .dashboard-hero p { color: rgba(255, 255, 255, .7); margin: 0; }

    .operations-dashboard .tenant-chip {
        align-items: center;
        background: rgba(255, 255, 255, .1);
        border: 1px solid rgba(255, 255, 255, .15);
        border-radius: 999px;
        color: #fff;
        display: inline-flex;
        font-size: 12px;
        font-weight: 600;
        gap: 7px;
        margin-top: 16px;
        max-width: 100%;
        padding: 7px 12px;
    }

    .operations-dashboard .tenant-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .operations-dashboard .hero-actions {
        align-items: flex-end;
        display: flex;
        flex-direction: column;
        gap: 12px;
        position: relative;
        z-index: 1;
    }

    .operations-dashboard .hero-date { color: rgba(255, 255, 255, .72); font-size: 12px; font-weight: 600; }

    .operations-dashboard .btn-dashboard-primary {
        align-items: center;
        background: var(--dashboard-orange);
        border: 0;
        border-radius: 10px;
        color: #182f45;
        display: inline-flex;
        font-weight: 700;
        gap: 8px;
        padding: 11px 17px;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .operations-dashboard .btn-dashboard-primary:hover {
        box-shadow: 0 9px 20px rgba(245, 165, 36, .25);
        color: #182f45;
        transform: translateY(-1px);
    }

    .operations-dashboard .metric-card { padding: 20px; position: relative; }
    .operations-dashboard .metric-card::after {
        background: var(--dashboard-orange);
        border-radius: 999px 0 0 999px;
        content: '';
        height: 38px;
        opacity: .78;
        position: absolute;
        right: 0;
        top: 22px;
        width: 4px;
    }

    .operations-dashboard .metric-head { align-items: flex-start; display: flex; justify-content: space-between; }
    .operations-dashboard .metric-label { color: var(--dashboard-muted); font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .operations-dashboard .metric-icon {
        align-items: center;
        background: var(--dashboard-orange-soft);
        border-radius: 12px;
        color: #df8b08;
        display: inline-flex;
        font-size: 20px;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    .operations-dashboard .metric-value {
        color: var(--dashboard-navy-dark);
        font-size: clamp(1.45rem, 2vw, 1.8rem);
        font-weight: 750;
        letter-spacing: -.03em;
        line-height: 1;
        margin: 18px 0 10px;
    }

    .operations-dashboard .metric-foot { align-items: center; color: var(--dashboard-muted); display: flex; font-size: 12px; gap: 7px; }
    .operations-dashboard .trend { align-items: center; border-radius: 999px; display: inline-flex; font-size: 11px; font-weight: 700; gap: 2px; padding: 3px 7px; }
    .operations-dashboard .trend-up { background: #e8f8ef; color: #1e9661; }
    .operations-dashboard .trend-down { background: #fff0ee; color: #d8584d; }
    .operations-dashboard .card-heading { align-items: flex-start; display: flex; justify-content: space-between; margin-bottom: 12px; }
    .operations-dashboard .card-title { color: var(--dashboard-navy-dark); font-size: 16px; font-weight: 700; margin: 0 0 4px; }
    .operations-dashboard .card-subtitle { color: var(--dashboard-muted); font-size: 12px; margin: 0; }
    .operations-dashboard .summary-pill { background: var(--dashboard-blue-soft); border-radius: 10px; color: var(--dashboard-navy); font-size: 11px; font-weight: 700; padding: 7px 10px; white-space: nowrap; }
    .operations-dashboard .chart-card-body { padding: 22px 24px 12px; }
    .operations-dashboard #dashboard-performance-chart { min-height: 292px; }
    .operations-dashboard .chart-stat { border-left: 2px solid var(--dashboard-border); padding-left: 12px; }
    .operations-dashboard .chart-stat:first-child { border-color: var(--dashboard-orange); }
    .operations-dashboard .chart-stat-label { color: var(--dashboard-muted); display: block; font-size: 11px; margin-bottom: 3px; }
    .operations-dashboard .chart-stat-value { color: var(--dashboard-navy-dark); font-size: 15px; font-weight: 700; }
    .operations-dashboard .stock-card-body { padding: 22px 24px; }
    .operations-dashboard #dashboard-stock-chart { min-height: 210px; }
    .operations-dashboard .stock-row { align-items: center; border-top: 1px solid #edf1f5; display: flex; justify-content: space-between; padding: 11px 0; }
    .operations-dashboard .stock-row:last-child { padding-bottom: 0; }
    .operations-dashboard .stock-label { align-items: center; color: #59687a; display: flex; font-size: 12px; gap: 8px; }
    .operations-dashboard .stock-dot { background: #55bd87; border-radius: 50%; height: 8px; width: 8px; }
    .operations-dashboard .stock-dot.warning { background: var(--dashboard-orange); }
    .operations-dashboard .stock-dot.danger { background: #e2655a; }
    .operations-dashboard .stock-value { color: var(--dashboard-navy-dark); font-size: 13px; font-weight: 700; }
    .operations-dashboard .table-card-body { padding: 22px 24px 10px; }
    .operations-dashboard .dashboard-table { margin-bottom: 0; }
    .operations-dashboard .dashboard-table th { border-bottom: 1px solid var(--dashboard-border); color: var(--dashboard-muted); font-size: 10px; font-weight: 700; letter-spacing: .07em; padding: 12px 10px; text-transform: uppercase; }
    .operations-dashboard .dashboard-table td { border-bottom: 1px solid #edf1f5; color: #526174; font-size: 12px; padding: 13px 10px; vertical-align: middle; }
    .operations-dashboard .dashboard-table tbody tr:last-child td { border-bottom: 0; }
    .operations-dashboard .invoice-number { color: var(--dashboard-navy-dark); display: block; font-weight: 700; }
    .operations-dashboard .invoice-time { color: #97a2b0; display: block; font-size: 10px; margin-top: 2px; }
    .operations-dashboard .payment-badge { background: var(--dashboard-blue-soft); border-radius: 999px; color: #52708a; display: inline-block; font-size: 10px; font-weight: 700; padding: 5px 8px; }
    .operations-dashboard .sale-total { color: var(--dashboard-navy-dark); font-weight: 750; }
    .operations-dashboard .list-card-body { padding: 22px 24px; }
    .operations-dashboard .top-item { align-items: center; display: grid; gap: 12px; grid-template-columns: 34px minmax(0, 1fr) auto; padding: 11px 0; }
    .operations-dashboard .top-item-rank { align-items: center; background: var(--dashboard-orange-soft); border-radius: 10px; color: #cf7f05; display: flex; font-size: 12px; font-weight: 800; height: 34px; justify-content: center; width: 34px; }
    .operations-dashboard .top-item-name { color: var(--dashboard-navy-dark); display: block; font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .operations-dashboard .top-item-meta { color: var(--dashboard-muted); font-size: 10px; }
    .operations-dashboard .top-item-progress { background: #edf1f5; border-radius: 999px; height: 4px; margin-top: 7px; overflow: hidden; }
    .operations-dashboard .top-item-progress span { background: linear-gradient(90deg, var(--dashboard-orange), #ffc96d); border-radius: inherit; display: block; height: 100%; }
    .operations-dashboard .top-item-value { color: var(--dashboard-navy-dark); font-size: 12px; font-weight: 750; text-align: right; }
    .operations-dashboard .quick-actions { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .operations-dashboard .quick-action { align-items: center; background: #f8fafc; border: 1px solid var(--dashboard-border); border-radius: 13px; color: var(--dashboard-navy); display: flex; font-size: 12px; font-weight: 700; gap: 9px; min-height: 52px; padding: 10px 12px; transition: border-color .18s ease, transform .18s ease, background .18s ease; }
    .operations-dashboard .quick-action:hover { background: var(--dashboard-orange-soft); border-color: #f2c16e; color: var(--dashboard-navy-dark); transform: translateY(-1px); }
    .operations-dashboard .quick-action i { color: #dd8907; font-size: 18px; }
    .operations-dashboard .empty-state { align-items: center; color: var(--dashboard-muted); display: flex; flex-direction: column; gap: 8px; justify-content: center; min-height: 180px; padding: 24px; text-align: center; }
    .operations-dashboard .empty-state i { color: #b6c1ce; font-size: 34px; }
    .operations-dashboard .tenant-required-card { align-items: center; background: linear-gradient(135deg, #fff, var(--dashboard-orange-soft)); display: flex; gap: 18px; padding: 24px; }
    .operations-dashboard .tenant-required-icon { align-items: center; background: var(--dashboard-orange); border-radius: 16px; color: var(--dashboard-navy-dark); display: flex; flex: 0 0 auto; font-size: 27px; height: 58px; justify-content: center; width: 58px; }

    @media (max-width: 767.98px) {
        .operations-dashboard .dashboard-hero { padding: 22px; }
        .operations-dashboard .hero-actions { align-items: flex-start; margin-top: 20px; }
        .operations-dashboard .chart-card-body, .operations-dashboard .stock-card-body, .operations-dashboard .table-card-body, .operations-dashboard .list-card-body { padding-left: 18px; padding-right: 18px; }
        .operations-dashboard .dashboard-table th:nth-child(3), .operations-dashboard .dashboard-table td:nth-child(3), .operations-dashboard .dashboard-table th:nth-child(4), .operations-dashboard .dashboard-table td:nth-child(4) { display: none; }
    }

    @media (max-width: 420px) {
        .operations-dashboard .quick-actions { grid-template-columns: 1fr; }
        .operations-dashboard .tenant-required-card { align-items: flex-start; flex-direction: column; }
    }
</style>
@endpush

@section('content')
<div class="operations-dashboard pb-3">
    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm mb-4">{{ session('status') }}</div>
    @endif

    @if (session('tenant_required'))
        <div class="alert alert-warning border-0 shadow-sm mb-4">{{ session('tenant_required') }}</div>
    @endif

    <div class="dashboard-card dashboard-hero mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="eyebrow">{{ __('Business overview') }}</div>
                <h2>{{ $greeting }}, {{ $firstName }}</h2>
                <p>{{ __('Here is what is happening with your store today.') }}</p>
                <div class="tenant-chip"><i class="uil uil-building"></i><span>{{ $tenantName }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="hero-actions">
                    <span class="hero-date">{{ now()->format('l, d M Y') }}</span>
                    @if (admin_can_switch_tenant() && $tenantOptions->isNotEmpty())
                        <button type="button" class="btn-dashboard-primary" data-bs-toggle="modal" data-bs-target="#tenantSelectionModal"><i class="uil uil-building"></i>{{ $selectedTenant ? __('Switch tenant') : __('Choose tenant') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (! $selectedTenant)
        <div class="dashboard-card tenant-required-card mb-4">
            <div class="tenant-required-icon"><i class="uil uil-building"></i></div>
            <div class="flex-grow-1">
                <h4 class="card-title mb-1">{{ $tenantOptions->isEmpty() ? __('No tenant assigned') : __('Choose a tenant to see live results') }}</h4>
                <p class="card-subtitle mb-0">{{ $tenantOptions->isEmpty() ? __('No active tenant is assigned to your account.') : __('Select one of your assigned companies and this dashboard will load its sales, inventory, customers, and promotions.') }}</p>
            </div>
            @if (admin_can_switch_tenant() && $tenantOptions->isNotEmpty())
                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#tenantSelectionModal">{{ __('Select tenant') }}</button>
            @endif
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-card metric-card">
                <div class="metric-head"><span class="metric-label">{{ __('Revenue today') }}</span><span class="metric-icon"><i class="uil uil-dollar-alt"></i></span></div>
                <div class="metric-value">{{ $money($dashboard['revenue_today']) }}</div>
                <div class="metric-foot"><span class="trend {{ $revenueUp ? 'trend-up' : 'trend-down' }}"><i class="uil {{ $revenueUp ? 'uil-arrow-up' : 'uil-arrow-down' }}"></i>{{ number_format(abs($dashboard['revenue_trend']), 1) }}%</span><span>{{ __('vs yesterday') }}</span></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-card metric-card">
                <div class="metric-head"><span class="metric-label">{{ __('Orders today') }}</span><span class="metric-icon"><i class="uil uil-shopping-cart-alt"></i></span></div>
                <div class="metric-value">{{ number_format($dashboard['orders_today']) }}</div>
                <div class="metric-foot"><span class="trend {{ $ordersUp ? 'trend-up' : 'trend-down' }}"><i class="uil {{ $ordersUp ? 'uil-arrow-up' : 'uil-arrow-down' }}"></i>{{ number_format(abs($dashboard['orders_trend']), 1) }}%</span><span>{{ __('vs yesterday') }}</span></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-card metric-card">
                <div class="metric-head"><span class="metric-label">{{ __('Customers') }}</span><span class="metric-icon"><i class="uil uil-users-alt"></i></span></div>
                <div class="metric-value">{{ number_format($dashboard['customers']) }}</div>
                <div class="metric-foot"><span>{{ __('Active customer accounts') }}</span></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-card metric-card">
                <div class="metric-head"><span class="metric-label">{{ __('Promotions') }}</span><span class="metric-icon"><i class="uil uil-tag-alt"></i></span></div>
                <div class="metric-value">{{ number_format($dashboard['active_promotions']) }}</div>
                <div class="metric-foot"><span>{{ __('Active promotions right now') }}</span></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="dashboard-card">
                <div class="chart-card-body">
                    <div class="card-heading">
                        <div><h4 class="card-title">{{ __('Sales performance') }}</h4><p class="card-subtitle">{{ __('Revenue and completed orders over the last 7 days') }}</p></div>
                        <span class="summary-pill">{{ __('7 day total') }}: {{ $money($dashboard['seven_day_revenue']) }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-4 mt-3">
                        <div class="chart-stat"><span class="chart-stat-label">{{ __('Average order') }}</span><span class="chart-stat-value">{{ $money($dashboard['average_order_value']) }}</span></div>
                        <div class="chart-stat"><span class="chart-stat-label">{{ __('Orders this week') }}</span><span class="chart-stat-value">{{ number_format(array_sum($dashboard['orders_series'])) }}</span></div>
                        <div class="chart-stat"><span class="chart-stat-label">{{ __('Held carts') }}</span><span class="chart-stat-value">{{ number_format($dashboard['held_sales']) }}</span></div>
                    </div>
                    <div id="dashboard-performance-chart"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="dashboard-card">
                <div class="stock-card-body">
                    <div class="card-heading mb-0">
                        <div><h4 class="card-title">{{ __('Inventory health') }}</h4><p class="card-subtitle">{{ __('Availability across active items') }}</p></div>
                        <span class="summary-pill">{{ number_format($dashboard['active_items']) }} {{ __('items') }}</span>
                    </div>
                    <div id="dashboard-stock-chart"></div>
                    <div class="stock-row"><span class="stock-label"><span class="stock-dot"></span>{{ __('Healthy stock') }}</span><span class="stock-value">{{ number_format($healthyItems) }}</span></div>
                    <div class="stock-row"><span class="stock-label"><span class="stock-dot warning"></span>{{ __('Low stock') }}</span><span class="stock-value">{{ number_format($dashboard['low_stock_items']) }}</span></div>
                    <div class="stock-row"><span class="stock-label"><span class="stock-dot danger"></span>{{ __('Out of stock') }}</span><span class="stock-value">{{ number_format($dashboard['out_of_stock_items']) }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="dashboard-card">
                <div class="table-card-body">
                    <div class="card-heading">
                        <div><h4 class="card-title">{{ __('Recent sales') }}</h4><p class="card-subtitle">{{ __('Latest completed transactions') }}</p></div>
                    </div>
                    @if ($dashboard['recent_sales']->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table dashboard-table">
                                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Items') }}</th><th>{{ __('Payment') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($dashboard['recent_sales'] as $sale)
                                        <tr>
                                            <td><span class="invoice-number">{{ $sale->invoice_number }}</span><span class="invoice-time">{{ \Carbon\Carbon::parse($sale->completed_at)->diffForHumans() }}</span></td>
                                            <td>{{ $sale->customer_name ?: __('Walk-in customer') }}</td>
                                            <td>{{ number_format((int) $sale->item_count) }}</td>
                                            <td><span class="payment-badge">{{ \Illuminate\Support\Str::headline($sale->payment_method ?: __('Unknown')) }}</span></td>
                                            <td class="text-end"><span class="sale-total">{{ $money($sale->total_base) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state"><i class="uil uil-receipt"></i><span>{{ __('Completed sales will appear here.') }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="row g-4">
                <div class="col-md-7 col-xl-12">
                    <div class="dashboard-card">
                        <div class="list-card-body">
                            <div class="card-heading"><div><h4 class="card-title">{{ __('Top selling items') }}</h4><p class="card-subtitle">{{ __('Ranked by units sold') }}</p></div></div>
                            @forelse ($dashboard['top_items'] as $item)
                                <div class="top-item">
                                    <div class="top-item-rank">{{ $loop->iteration }}</div>
                                    <div class="overflow-hidden">
                                        <span class="top-item-name">{{ $item->name }}</span>
                                        <span class="top-item-meta">{{ $item->sku ?: __('No SKU') }} · {{ number_format((float) $item->units, 0) }} {{ __('units') }}</span>
                                        <div class="top-item-progress"><span style="width: {{ max(8, min(100, ((float) $item->units / $topUnits) * 100)) }}%"></span></div>
                                    </div>
                                    <div class="top-item-value">{{ $money($item->revenue) }}</div>
                                </div>
                            @empty
                                <div class="empty-state" style="min-height: 120px"><i class="uil uil-chart-growth"></i><span>{{ __('Item rankings will appear after sales are completed.') }}</span></div>
                            @endforelse
                        </div>
                    </div>
                </div>
                @if ($selectedTenant)
                    <div class="col-md-5 col-xl-12">
                        <div class="dashboard-card">
                            <div class="list-card-body">
                                <div class="card-heading"><div><h4 class="card-title">{{ __('Quick actions') }}</h4><p class="card-subtitle">{{ __('Jump back into daily work') }}</p></div></div>
                                <div class="quick-actions">
                                    @if (admin_has_permission('items.view'))<a class="quick-action" href="{{ route('admin.items.index') }}"><i class="uil uil-box"></i>{{ __('Manage items') }}</a>@endif
                                    @if (admin_has_permission('customers.view'))<a class="quick-action" href="{{ route('admin.customers.index') }}"><i class="uil uil-users-alt"></i>{{ __('Customers') }}</a>@endif
                                    @if (admin_has_permission('promotions.view'))<a class="quick-action" href="{{ route('admin.promotions.index') }}"><i class="uil uil-tag-alt"></i>{{ __('Promotions') }}</a>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
    (function () {
        const renderDashboardCharts = function () {
            if (typeof ApexCharts === 'undefined') return;

            const moneySymbol = @json($dashboard['currency_symbol']);
            const moneyDecimals = @json($dashboard['currency_decimals']);
            const performanceElement = document.querySelector('#dashboard-performance-chart');
            const stockElement = document.querySelector('#dashboard-stock-chart');

            if (performanceElement) {
                new ApexCharts(performanceElement, {
                    chart: { height: 292, type: 'line', toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
                    series: [
                        { name: @json(__('Revenue')), type: 'column', data: @json($dashboard['revenue_series']) },
                        { name: @json(__('Orders')), type: 'line', data: @json($dashboard['orders_series']) }
                    ],
                    colors: ['#f5a524', '#173754'],
                    dataLabels: { enabled: false },
                    stroke: { width: [0, 3], curve: 'smooth' },
                    plotOptions: { bar: { borderRadius: 5, columnWidth: '42%' } },
                    fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: .2, opacityFrom: .95, opacityTo: .65, stops: [0, 100] } },
                    grid: { borderColor: '#edf1f5', strokeDashArray: 4, padding: { left: 4, right: 4 } },
                    markers: { size: 4, colors: ['#173754'], strokeColors: '#fff', strokeWidth: 2 },
                    xaxis: { categories: @json($dashboard['chart_labels']), axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#8793a2', fontSize: '11px' } } },
                    yaxis: [
                        { labels: { style: { colors: '#8793a2', fontSize: '10px' }, formatter: value => moneySymbol + Number(value).toFixed(0) } },
                        { opposite: true, min: 0, max: Math.max(5, ...@json($dashboard['orders_series'])), tickAmount: 5, labels: { style: { colors: '#8793a2', fontSize: '10px' }, formatter: value => Math.round(value) } }
                    ],
                    legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', markers: { width: 8, height: 8, radius: 8 } },
                    tooltip: { shared: true, intersect: false, y: { formatter: function (value, context) { return context.seriesIndex === 0 ? moneySymbol + Number(value).toFixed(moneyDecimals) : Math.round(value) + ' ' + @json(__('orders')); } } }
                }).render();
            }

            if (stockElement) {
                new ApexCharts(stockElement, {
                    chart: { height: 220, type: 'radialBar', sparkline: { enabled: true }, fontFamily: 'inherit' },
                    series: [@json($dashboard['stock_health'])],
                    colors: ['#f5a524'],
                    plotOptions: { radialBar: { startAngle: -130, endAngle: 130, hollow: { size: '62%' }, track: { background: '#edf1f5', strokeWidth: '100%', margin: 2 }, dataLabels: { name: { show: true, offsetY: 20, color: '#738195', fontSize: '11px' }, value: { offsetY: -14, color: '#102a42', fontSize: '28px', fontWeight: 700, formatter: value => Math.round(value) + '%' } } } },
                    labels: [@json(__('In stock'))],
                    stroke: { lineCap: 'round' }
                }).render();
            }
        };

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderDashboardCharts);
        else renderDashboardCharts();
    })();
</script>
@endpush
