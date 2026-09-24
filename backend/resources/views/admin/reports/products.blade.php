@extends('layouts.app')

@section('title', __('Product Performance Report'))
@section('page_title', __('Product Performance Report'))

@php
    $currencySymbol = $currency['symbol'] ?? ($kpis['currency_symbol'] ?? '$');
    $currencyDecimals = (int) ($currency['decimals'] ?? ($kpis['currency_decimals'] ?? 2));
    $money = fn ($val) => $currencySymbol . number_format((float) ($val ?? 0), $currencyDecimals);
    $selectedPeriod = $filters['date_range'] ?? ($filters['period'] ?? 'last_30_days');
    $totalUnitsAll = $total_units ?? ($kpis['total_units'] ?? 0);
    $totalRevAll = max((float) ($total_revenue ?? ($kpis['total_revenue'] ?? 0)), 1);
    $productsList = $items_report ?? ($products ?? collect());
    $chartNames = $top_chart_names ?? [];
    $chartRevenues = $top_chart_revenue ?? [];
    $totalDistinct = ($productsList instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $productsList->total() : count($productsList);
@endphp

@push('styles')
<style>
    .report-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 12px;
    }
    .report-tab-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 600;
        color: #64748b;
        background: transparent;
        text-decoration: none;
        transition: all .15s ease;
    }
    .report-tab-link:hover {
        color: #4f46e5;
        background: #f1f5f9;
    }
    .report-tab-link.active {
        color: #4f46e5;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
    }
    .kpi-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 20px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }
    .kpi-label {
        font-size: 12.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 8px;
    }
    .kpi-value {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
    .chart-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 20px;
        margin-bottom: 24px;
    }
    .chart-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .chart-card-subtitle {
        font-size: 12.5px;
        color: #64748b;
        margin-bottom: 16px;
    }
    .data-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 20px;
        margin-bottom: 24px;
    }
    .rank-badge {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
    }
    .rank-1 { background-color: #fef3c7; color: #b45309; }
    .rank-2 { background-color: #f1f5f9; color: #475569; }
    .rank-3 { background-color: #ffedd5; color: #c2410c; }
    .rank-other { background-color: #f8fafc; color: #94a3b8; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <!-- Top Nav Tabs & Actions Bar -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div class="report-tabs mb-0 pb-0 border-0">
            <a href="{{ route('admin.reports.sales') }}" class="report-tab-link">
                <i class="uil uil-chart-line"></i> {{ __('Sales Overview') }}
            </a>
            <a href="{{ route('admin.reports.products') }}" class="report-tab-link active">
                <i class="uil uil-box"></i> {{ __('Product Performance') }}
            </a>
            <a href="{{ route('admin.reports.inventory') }}" class="report-tab-link">
                <i class="uil uil-archive"></i> {{ __('Inventory & Stock') }}
            </a>
            <a href="{{ route('admin.reports.payments') }}" class="report-tab-link">
                <i class="uil uil-credit-card"></i> {{ __('Payment Summary') }}
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if (admin_has_permission('reports.export'))
                <a href="{{ route('admin.reports.products.export', request()->query()) }}" class="btn btn-outline-primary btn-sm px-3 font-size-13">
                    <i class="uil uil-export me-1"></i> {{ __('Export CSV') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.reports.products') }}" id="productFilterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Period') }}</label>
                    <select name="date_range" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                        <option value="today" {{ $selectedPeriod === 'today' ? 'selected' : '' }}>{{ __('Today') }}</option>
                        <option value="yesterday" {{ $selectedPeriod === 'yesterday' ? 'selected' : '' }}>{{ __('Yesterday') }}</option>
                        <option value="last_7_days" {{ $selectedPeriod === 'last_7_days' ? 'selected' : '' }}>{{ __('Last 7 Days') }}</option>
                        <option value="last_30_days" {{ $selectedPeriod === 'last_30_days' ? 'selected' : '' }}>{{ __('Last 30 Days') }}</option>
                        <option value="this_month" {{ $selectedPeriod === 'this_month' ? 'selected' : '' }}>{{ __('This Month') }}</option>
                        <option value="last_month" {{ $selectedPeriod === 'last_month' ? 'selected' : '' }}>{{ __('Last Month') }}</option>
                        <option value="this_year" {{ $selectedPeriod === 'this_year' ? 'selected' : '' }}>{{ __('This Year') }}</option>
                        <option value="custom" {{ $selectedPeriod === 'custom' ? 'selected' : '' }}>{{ __('Custom Range') }}</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6" id="startDateCol" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Start Date') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
                </div>

                <div class="col-md-2 col-sm-6" id="endDateCol" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('End Date') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
                </div>

                <div class="col-md-3 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Category') }}</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ (string) ($filters['category_id'] ?? '') === (string) $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('Item name or SKU') }}" value="{{ $filters['search'] ?? '' }}">
                </div>

                <div class="col-md-auto d-flex gap-2 ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="uil uil-filter me-1"></i> {{ __('Apply') }}
                    </button>
                    <a href="{{ route('admin.reports.products') }}" class="btn btn-light btn-sm px-3 text-muted">
                        {{ __('Reset') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 3 KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Total Units Sold') }}</span>
                    <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="uil uil-box"></i>
                    </div>
                </div>
                <div class="kpi-value text-primary">{{ number_format($totalUnitsAll) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Aggregate quantities across sales') }}</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Product Revenue') }}</span>
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="uil uil-dollar-sign-alt"></i>
                    </div>
                </div>
                <div class="kpi-value text-success">{{ $money($total_revenue ?? 0) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Net line totals before tax/order discounts') }}</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Active Selling Products') }}</span>
                    <div class="kpi-icon" style="background: #fdf2f8; color: #db2777;">
                        <i class="uil uil-tag"></i>
                    </div>
                </div>
                <div class="kpi-value">{{ number_format($totalDistinct) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Products with recorded sales') }}</small>
            </div>
        </div>
    </div>

    <!-- Top Products Chart -->
    @if (count($chartNames) > 0)
        <div class="chart-card mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <h4 class="chart-card-title">{{ __('Top Products by Revenue') }}</h4>
                    <p class="chart-card-subtitle">{{ __('Leaderboard for the selected reporting period') }}</p>
                </div>
            </div>
            <div id="top-products-chart" style="min-height: 280px;"></div>
        </div>
    @endif

    <!-- Products Performance Table -->
    <div class="data-table-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="chart-card-title mb-1">{{ __('Ranked Product Performance') }}</h4>
                <p class="chart-card-subtitle mb-0">
                    @if ($productsList instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        {{ __('Showing :from to :to of :total items', [
                            'from' => $productsList->firstItem() ?? 0,
                            'to' => $productsList->lastItem() ?? 0,
                            'total' => $productsList->total()
                        ]) }}
                    @else
                        {{ __('Showing :total items', ['total' => count($productsList)]) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="font-size-12 text-muted fw-semibold text-center" style="width: 50px;">#</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Item / Description') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('SKU') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Qty Sold') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Avg Price') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Revenue') }}</th>
                        <th class="font-size-12 text-muted fw-semibold" style="width: 140px;">{{ __('Share') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productsList as $index => $item)
                        @php
                            $currentPage = ($productsList instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $productsList->currentPage() : 1;
                            $perPage = ($productsList instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $productsList->perPage() : 15;
                            $rank = ($currentPage - 1) * $perPage + $index + 1;
                            $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                            $itemRev = (float) ($item->total_revenue ?? 0);
                            $sharePct = round(($itemRev / $totalRevAll) * 100, 1);
                        @endphp
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge {{ $rankClass }}">{{ $rank }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->name ?? ($item->item_name ?? __('Item')) }}</div>
                            </td>
                            <td>
                                <span class="font-size-12 text-muted font-monospace">{{ $item->sku ?: '-' }}</span>
                            </td>
                            <td class="text-end font-size-13 fw-semibold text-dark">
                                {{ number_format((float) ($item->units_sold ?? ($item->total_qty ?? 0))) }}
                            </td>
                            <td class="text-end font-size-13 text-muted">
                                {{ $money($item->avg_price ?? 0) }}
                            </td>
                            <td class="text-end font-size-14 fw-bold text-dark">
                                {{ $money($itemRev) }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min($sharePct, 100) }}%"></div>
                                    </div>
                                    <span class="font-size-11 text-muted fw-semibold" style="min-width: 38px;">{{ $sharePct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="uil uil-inbox font-size-36 d-block mb-2 text-muted"></i>
                                {{ __('No product sales found for this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($productsList instanceof \Illuminate\Pagination\LengthAwarePaginator && $productsList->hasPages())
            <div class="mt-4 d-flex justify-content-end">
                {{ $productsList->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
    function toggleCustomDates(period) {
        const isCustom = period === 'custom';
        const startCol = document.getElementById('startDateCol');
        const endCol = document.getElementById('endDateCol');
        if (startCol) startCol.style.display = isCustom ? 'block' : 'none';
        if (endCol) endCol.style.display = isCustom ? 'block' : 'none';
    }

    (function () {
        if (typeof ApexCharts === 'undefined') return;

        const currencySymbol = @json($currencySymbol);
        const currencyDecimals = @json($currencyDecimals);
        const chartNames = @json($chartNames);
        const chartRevenues = @json($chartRevenues);

        const chartElement = document.querySelector('#top-products-chart');
        if (chartElement && chartNames && chartNames.length > 0) {
            new ApexCharts(chartElement, {
                chart: {
                    type: 'bar',
                    height: 280,
                    toolbar: { show: false },
                    fontFamily: 'inherit'
                },
                plotOptions: {
                    bar: {
                        borderRadius: 6,
                        horizontal: true,
                        barHeight: '52%'
                    }
                },
                colors: ['#4f46e5'],
                series: [{
                    name: @json(__('Revenue')),
                    data: chartRevenues
                }],
                xaxis: {
                    categories: chartNames,
                    labels: {
                        style: { colors: '#64748b', fontSize: '11px' },
                        formatter: val => currencySymbol + Number(val).toLocaleString()
                    }
                },
                yaxis: {
                    labels: {
                        style: { colors: '#0f172a', fontSize: '12px', fontWeight: 500 },
                        maxWidth: 220
                    }
                },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                tooltip: {
                    y: {
                        formatter: val => currencySymbol + Number(val).toFixed(currencyDecimals)
                    }
                }
            }).render();
        }
    })();
</script>
@endpush
