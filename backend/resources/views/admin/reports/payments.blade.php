@extends('layouts.app')

@section('title', __('Payment Summary Report'))
@section('page_title', __('Payment Summary Report'))

@php
    $currencySymbol = $currency['symbol'] ?? ($kpis['currency_symbol'] ?? '$');
    $currencyDecimals = (int) ($currency['decimals'] ?? ($kpis['currency_decimals'] ?? 2));
    $money = fn ($val) => $currencySymbol . number_format((float) ($val ?? 0), $currencyDecimals);
    $selectedPeriod = $filters['date_range'] ?? ($filters['period'] ?? 'last_30_days');
    $totalRev = $total_revenue ?? ($kpis['total_collected'] ?? 0);
    $totalTxns = $total_transactions ?? ($kpis['total_transactions'] ?? 0);
    $pmList = $payments ?? ($paymentMethods ?? collect());
    $chartMethods = $chart_methods ?? [];
    $chartAmounts = $chart_amounts ?? [];
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
        height: 100%;
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
    .method-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <!-- Top Nav Tabs -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div class="report-tabs mb-0 pb-0 border-0">
            <a href="{{ route('admin.reports.sales') }}" class="report-tab-link">
                <i class="uil uil-chart-line"></i> {{ __('Sales Overview') }}
            </a>
            <a href="{{ route('admin.reports.products') }}" class="report-tab-link">
                <i class="uil uil-box"></i> {{ __('Product Performance') }}
            </a>
            <a href="{{ route('admin.reports.inventory') }}" class="report-tab-link">
                <i class="uil uil-archive"></i> {{ __('Inventory & Stock') }}
            </a>
            <a href="{{ route('admin.reports.payments') }}" class="report-tab-link active">
                <i class="uil uil-credit-card"></i> {{ __('Payment Summary') }}
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.reports.payments') }}" id="paymentFilterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
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

                <div class="col-md-3 col-sm-6" id="startDateCol" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Start Date') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
                </div>

                <div class="col-md-3 col-sm-6" id="endDateCol" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('End Date') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
                </div>

                <div class="col-md-auto d-flex gap-2 ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="uil uil-filter me-1"></i> {{ __('Apply') }}
                    </button>
                    <a href="{{ route('admin.reports.payments') }}" class="btn btn-light btn-sm px-3 text-muted">
                        {{ __('Reset') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 2 KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Total Collections') }}</span>
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="uil uil-bill"></i>
                    </div>
                </div>
                <div class="kpi-value text-success">{{ $money($totalRev) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Aggregate completed payment volume') }}</small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Total Transactions') }}</span>
                    <div class="kpi-icon" style="background: #eef2ff; color: #4f46e5;">
                        <i class="uil uil-transaction"></i>
                    </div>
                </div>
                <div class="kpi-value text-primary">{{ number_format($totalTxns) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Completed sales tickets processed') }}</small>
            </div>
        </div>
    </div>

    <!-- Payment Visualization Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="chart-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h4 class="chart-card-title">{{ __('Collection Share') }}</h4>
                        <p class="chart-card-subtitle">{{ __('Relative breakdown across payment methods') }}</p>
                    </div>
                </div>
                <div id="payment-donut-chart" style="min-height: 280px;"></div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="data-table-card h-100 mb-0">
                <h4 class="chart-card-title mb-1">{{ __('Payment Method Details') }}</h4>
                <p class="chart-card-subtitle mb-3">{{ __('Metrics breakdown per payment channel') }}</p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="font-size-12 text-muted fw-semibold">{{ __('Method') }}</th>
                                <th class="font-size-12 text-muted fw-semibold text-center">{{ __('Transactions') }}</th>
                                <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Avg Ticket') }}</th>
                                <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Total Volume') }}</th>
                                <th class="font-size-12 text-muted fw-semibold" style="width: 120px;">{{ __('Share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pmList as $pm)
                                @php
                                    $rawMethod = $pm->payment_method ?? ($pm['method'] ?? 'unknown');
                                    $methodName = ucfirst(str_replace('_', ' ', $rawMethod));
                                    $icon = match(strtolower($rawMethod)) {
                                        'cash' => 'uil-money-bill',
                                        'card', 'credit_card', 'debit_card' => 'uil-credit-card',
                                        'bank_transfer', 'transfer' => 'uil-university',
                                        'qr', 'khqr', 'aba_pay' => 'uil-qrcode-scan',
                                        default => 'uil-wallet'
                                    };
                                    $iconBg = match(strtolower($rawMethod)) {
                                        'cash' => '#ecfdf5',
                                        'card', 'credit_card' => '#eff6ff',
                                        'bank_transfer' => '#f5f3ff',
                                        'qr', 'khqr' => '#fff7ed',
                                        default => '#f8fafc'
                                    };
                                    $iconColor = match(strtolower($rawMethod)) {
                                        'cash' => '#059669',
                                        'card', 'credit_card' => '#2563eb',
                                        'bank_transfer' => '#7c3aed',
                                        'qr', 'khqr' => '#ea580c',
                                        default => '#64748b'
                                    };
                                    $mCount = $pm->orders_count ?? ($pm['count'] ?? 0);
                                    $mTotal = $pm->total_amount ?? ($pm['total'] ?? 0);
                                    $mAvg = $pm->avg_amount ?? ($pm['avg_per_txn'] ?? ($mCount > 0 ? $mTotal / $mCount : 0));
                                    $mPct = $pm->percentage ?? 0;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="method-icon-box" style="background: {{ $iconBg }}; color: {{ $iconColor }};">
                                                <i class="uil {{ $icon }}"></i>
                                            </div>
                                            <span class="fw-semibold text-dark">{{ $methodName }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center font-size-13">
                                        {{ number_format($mCount) }}
                                    </td>
                                    <td class="text-end font-size-13 text-muted">
                                        {{ $money($mAvg) }}
                                    </td>
                                    <td class="text-end font-size-14 fw-bold text-dark">
                                        {{ $money($mTotal) }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ min($mPct, 100) }}%"></div>
                                            </div>
                                            <span class="font-size-11 text-muted fw-semibold" style="min-width: 36px;">{{ $mPct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="uil uil-inbox font-size-36 d-block mb-2 text-muted"></i>
                                        {{ __('No payment records found for this period.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
        const labels = @json($chartMethods);
        const series = @json($chartAmounts);
        const donutElement = document.querySelector('#payment-donut-chart');

        if (donutElement && labels && labels.length > 0 && series && series.length > 0) {
            new ApexCharts(donutElement, {
                chart: { height: 280, type: 'donut', fontFamily: 'inherit' },
                series: series,
                labels: labels.map(l => l.replace('_', ' ').toUpperCase()),
                colors: ['#10b981', '#4f46e5', '#f59e0b', '#8b5cf6', '#0ea5e9', '#ec4899'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: @json(__('Total')),
                                    formatter: () => currencySymbol + series.reduce((a, b) => a + b, 0).toFixed(currencyDecimals)
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', horizontalAlign: 'center', fontSize: '12px' },
                tooltip: {
                    y: {
                        formatter: val => currencySymbol + Number(val).toFixed(currencyDecimals)
                    }
                }
            }).render();
        } else if (donutElement) {
            donutElement.innerHTML = '<div class="text-center py-5 text-muted font-size-13">{{ __("No payment data available") }}</div>';
        }
    })();
</script>
@endpush
