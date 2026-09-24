@extends('layouts.app')

@section('title', __('Sales Report'))
@section('page_title', __('Sales Report'))

@php
    $currencySymbol = $currency['symbol'] ?? ($kpis['currency_symbol'] ?? '$');
    $currencyDecimals = (int) ($currency['decimals'] ?? ($kpis['currency_decimals'] ?? 2));
    $currencyCode = $currency['code'] ?? ($kpis['currency_code'] ?? 'USD');
    $currencyCatalog = collect($currencies ?? [])->mapWithKeys(function ($entry, $key) {
        $code = strtoupper((string) ($entry['code'] ?? $key));

        return [$code => $entry];
    })->all();
    $resolveLineCurrency = function (?string $code, array $embedded = []) use ($currencyCatalog, $currencyCode, $currencySymbol, $currencyDecimals) {
        $resolvedCode = strtoupper(trim((string) ($code ?: ($embedded['code'] ?? $currencyCode))));
        $configured = $currencyCatalog[$resolvedCode] ?? [];

        return [
            'code' => $resolvedCode,
            'symbol' => (string) ($embedded['symbol'] ?? $configured['symbol'] ?? ($resolvedCode === strtoupper((string) $currencyCode) ? $currencySymbol : $resolvedCode)),
            'decimals' => max(0, (int) ($embedded['decimalPlaces'] ?? $embedded['decimals'] ?? $configured['decimals'] ?? ($resolvedCode === strtoupper((string) $currencyCode) ? $currencyDecimals : 2))),
        ];
    };
    $money = fn ($val) => $currencySymbol . number_format((float) ($val ?? 0), $currencyDecimals);
    $selectedPeriod = $filters['date_range'] ?? ($filters['period'] ?? 'last_30_days');
    $totalRev = $gross_revenue ?? ($kpis['total_revenue'] ?? 0);
    $ordersCount = $completed_orders ?? ($kpis['completed_orders'] ?? 0);
    $aov = $avg_order_value ?? ($kpis['avg_order_value'] ?? 0);
    $itemsSold = $total_items ?? ($kpis['total_items_sold'] ?? 0);
    $discounts = $total_discounts ?? ($kpis['total_discount'] ?? 0);
    $taxes = $total_taxes ?? ($kpis['total_tax'] ?? 0);
    $salesList = $transactions ?? ($sales ?? collect());
    $distList = $payments_distribution ?? ($paymentMethodDistribution ?? collect());
    $pMethods = $available_payment_methods ?? ($paymentMethods ?? []);
    $oTypes = $available_order_types ?? ($orderTypes ?? []);
    $chartCats = $chart_labels ?? ($chart['categories'] ?? []);
    $chartRev = $revenue_series ?? ($chart['revenue'] ?? []);
    $chartOrd = $orders_series ?? ($chart['orders'] ?? []);
    $companyData = $company ?? [];
    $companyName = $companyData['name'] ?? ($selectedTenant ? admin_tenant_display_name($selectedTenant) : 'V-POS Store');
    $companyAddress = $companyData['address'] ?? '';
    $companyPhone = $companyData['phone'] ?? '';
    $companyEmail = $companyData['email'] ?? '';
    $receiptFooter = $companyData['receipt_footer'] ?? __('Thank you for your business.');
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
    .badge-soft-primary { background-color: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    .badge-soft-success { background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .badge-soft-warning { background-color: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .badge-soft-danger { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .badge-soft-info { background-color: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
    .badge-soft-secondary { background-color: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

    /* Modal Invoice Preview Styles */
    .invoice-format-switch {
        display: inline-flex;
        gap: 4px;
        margin-bottom: 14px;
        padding: 4px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f1f5f9;
    }
    .invoice-format-switch button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        cursor: pointer;
    }
    .invoice-format-switch button:hover {
        color: #4f46e5;
    }
    .invoice-format-switch button.active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12);
    }
    .invoice-preview-sheet {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        color: #0f172a;
    }
    .invoice-preview-header {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 0.9fr);
        grid-template-areas: "company title";
        align-items: flex-start;
        column-gap: 48px;
        padding: 24px;
        border-top: 5px solid #4f46e5;
        border-bottom: 1px solid #e2e8f0;
    }
    .invoice-preview-company {
        grid-area: company;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }
    .invoice-preview-company-mark {
        display: grid;
        place-items: center;
        flex: 0 0 44px;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 22px;
    }
    .invoice-preview-company h4 {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
    }
    .invoice-preview-address {
        max-width: 360px;
        color: #64748b;
        font-size: 11.5px;
        white-space: pre-line;
        line-height: 1.4;
    }
    .invoice-preview-title {
        grid-area: title;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        text-align: right;
    }
    .invoice-preview-title > div:first-child {
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: 0.08em;
        line-height: 1;
    }
    .invoice-preview-header-meta {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        width: auto;
        margin-top: 14px;
        margin-left: auto;
    }
    .invoice-preview-header-meta-item {
        display: flex;
        align-items: baseline;
        gap: 8px;
        min-width: 0;
        color: #0f172a;
        font-size: 11px;
    }
    .invoice-preview-header-meta-item > span {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px;
        flex: 0 0 68px;
        width: 68px;
        color: #64748b;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .invoice-preview-header-meta-item > span > span:last-child {
        margin-left: auto;
        text-align: right;
    }
    .invoice-preview-header-meta-item strong {
        color: #0f172a;
        font-weight: 600;
        text-align: left;
        line-height: 1.35;
    }
    .invoice-preview-number-row strong {
        color: #4f46e5;
        font-family: monospace;
        font-size: 13px;
        font-weight: 700;
    }
    .invoice-preview-time-row strong {
        color: #64748b;
        font-weight: 500;
    }
    .invoice-preview-table-wrap {
        padding: 16px 24px 4px;
    }
    .invoice-preview-table {
        width: 100%;
        margin-bottom: 0;
        font-size: 12px;
    }
    .invoice-preview-table thead th {
        padding: 10px 9px;
        border-top: 1px solid #dbe3f3;
        border-bottom: 2px solid #c7d2fe;
        background: #f5f7ff;
        color: #475569;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .invoice-preview-table thead th:first-child {
        border-top-left-radius: 7px;
    }
    .invoice-preview-table thead th:last-child {
        border-top-right-radius: 7px;
    }
    .invoice-preview-table tbody td {
        padding: 11px 9px;
        border-bottom: 1px solid #f1f5f9;
    }
    .invoice-preview-discount,
    .invoice-preview-discount span,
    .invoice-preview-discount strong,
    .thermal-discount,
    .thermal-discount span,
    .thermal-discount strong {
        color: #dc2626 !important;
    }
    .invoice-preview-summary {
        width: min(330px, calc(100% - 48px));
        margin: 12px 24px 20px auto;
    }
    .invoice-preview-summary > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 5px 0;
        font-size: 12px;
    }
    .invoice-preview-total {
        margin-top: 6px;
        padding-top: 12px !important;
        border-top: 2px solid #0f172a;
        color: #4f46e5;
        font-size: 15px !important;
    }
    .invoice-preview-total strong {
        color: #4f46e5;
        font-size: 18px;
        font-weight: 700;
    }
    .invoice-preview-total small {
        color: #64748b;
        font-size: 10px;
        font-weight: normal;
    }
    .invoice-preview-footer {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 58px;
        padding: 14px 24px;
        border-top: 1px dashed #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-align: center;
    }
    .invoice-preview-footer-message,
    .invoice-preview-rate-remark {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
    }
    .invoice-preview-rate-remark {
        color: #334155;
        font-size: 10px;
        font-weight: 600;
    }
    .invoice-preview-footer i {
        color: #4f46e5;
    }

    /* Thermal 80mm Receipt Styles */
    .thermal-receipt {
        width: min(100%, 302px);
        margin: 0 auto;
        padding: 20px 14px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        background: #ffffff;
        color: #111827;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .thermal-receipt,
    .thermal-receipt * {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Khmer Sangam MN", monospace !important;
    }
    .thermal-receipt-header {
        color: #111827;
        font-size: 9px;
        line-height: 1.4;
        text-align: center;
    }
    .thermal-receipt-header h3 {
        margin: 0 0 3px;
        font-size: 16px;
        font-weight: 800;
    }
    .thermal-receipt-header h4 {
        margin: 12px 0 7px;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.12em;
    }
    .thermal-receipt-meta {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
        margin-top: 7px;
        text-align: center;
    }
    .thermal-receipt-meta > div {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 5px;
        width: 100%;
    }
    .thermal-receipt-meta span {
        flex: 0 0 auto;
        color: #4b5563;
    }
    .thermal-receipt-meta strong {
        min-width: 0;
        font-weight: 700;
        text-align: center;
        overflow-wrap: anywhere;
    }
    .thermal-receipt-rule {
        margin: 10px 0;
        border-top: 1px dashed #6b7280;
    }
    .thermal-receipt-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 8.5px;
        line-height: 1.35;
    }
    .thermal-receipt-table th, .thermal-receipt-table td {
        padding: 6px 2px;
        vertical-align: top;
    }
    .thermal-receipt-table th:first-child, .thermal-receipt-table td:first-child { width: 60%; padding-left: 0; }
    .thermal-receipt-table th:nth-child(2), .thermal-receipt-table td:nth-child(2) { width: 12%; text-align: center; }
    .thermal-receipt-table th:last-child, .thermal-receipt-table td:last-child { width: 28%; text-align: right; padding-right: 0; }
    .thermal-receipt-table thead th {
        border-bottom: 1px dashed #6b7280;
        color: #111827;
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .thermal-receipt-table tbody td {
        border-bottom: 1px dashed #d1d5db;
    }
    .thermal-receipt-table td strong,
    .thermal-receipt-table td small {
        display: block;
    }
    .thermal-receipt-table td strong {
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .thermal-receipt-table td small {
        margin-top: 2px;
        color: #6b7280;
        font-size: 7.5px;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .thermal-receipt-summary {
        display: flex;
        flex-direction: column;
        gap: 3px;
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px dashed #6b7280;
        font-size: 9px;
    }
    .thermal-receipt-summary > div {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
    }
    .thermal-receipt-summary strong {
        text-align: right;
        white-space: nowrap;
    }
    .thermal-receipt-total {
        margin-top: 4px;
        padding-top: 7px;
        border-top: 2px solid #111827;
        font-size: 12px;
        font-weight: 800;
    }
    .thermal-receipt-summary > small {
        display: block;
        margin-top: 2px;
        color: #6b7280;
        font-size: 7.5px;
        text-align: center;
    }
    .thermal-receipt-footer {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed #6b7280;
        font-size: 8.5px;
        text-align: center;
        white-space: pre-line;
        line-height: 1.45;
    }
    .thermal-receipt-footer div {
        margin-bottom: 7px;
        color: #4b5563;
        font-size: 7.5px;
    }
    .thermal-receipt-footer strong {
        font-weight: 700;
    }
    @media (max-width: 767.98px) {
        .invoice-preview-header {
            grid-template-columns: 1fr;
            grid-template-areas:
                "company"
                "title";
            gap: 20px;
        }
        .invoice-preview-title {
            width: 100%;
            text-align: left !important;
        }
        .invoice-preview-header-meta {
            width: 100%;
            margin-left: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <!-- Top Nav Tabs & Actions Bar -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div class="report-tabs mb-0 pb-0 border-0">
            <a href="{{ route('admin.reports.sales') }}" class="report-tab-link active">
                <i class="uil uil-chart-line"></i> {{ __('Sales Overview') }}
            </a>
            <a href="{{ route('admin.reports.products') }}" class="report-tab-link">
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
                <a href="{{ route('admin.reports.sales.export', request()->query()) }}" class="btn btn-outline-primary btn-sm px-3 font-size-13">
                    <i class="uil uil-export me-1"></i> {{ __('Export CSV') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.reports.sales') }}" id="salesFilterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Period') }}</label>
                    <select name="date_range" class="form-select form-select-sm" id="periodSelect" onchange="toggleCustomDates(this.value)">
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

                <div class="col-md-2 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Payment Method') }}</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">{{ __('All Methods') }}</option>
                        @foreach ($pMethods as $pm)
                            <option value="{{ $pm }}" {{ ($filters['payment_method'] ?? '') === $pm ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $pm)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Order Type') }}</label>
                    <select name="order_type" class="form-select form-select-sm">
                        <option value="">{{ __('All Types') }}</option>
                        @foreach ($oTypes as $ot)
                            <option value="{{ $ot }}" {{ ($filters['order_type'] ?? '') === $ot ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $ot)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('Invoice # or customer') }}" value="{{ $filters['search'] ?? '' }}">
                </div>

                <div class="col-md-auto d-flex gap-2 ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="uil uil-filter me-1"></i> {{ __('Apply') }}
                    </button>
                    <a href="{{ route('admin.reports.sales') }}" class="btn btn-light btn-sm px-3 text-muted">
                        {{ __('Reset') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 6 KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Total Revenue') }}</span>
                    <div class="kpi-icon" style="background: #eef2ff; color: #4f46e5;">
                        <i class="uil uil-dollar-sign-alt"></i>
                    </div>
                </div>
                <div class="kpi-value text-primary">{{ $money($totalRev) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Net completed sales') }}</small>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Orders') }}</span>
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="uil uil-shopping-bag"></i>
                    </div>
                </div>
                <div class="kpi-value text-success">{{ number_format($ordersCount) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Completed transactions') }}</small>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Avg Order Value') }}</span>
                    <div class="kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                        <i class="uil uil-receipt"></i>
                    </div>
                </div>
                <div class="kpi-value">{{ $money($aov) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Average per ticket') }}</small>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Items Sold') }}</span>
                    <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="uil uil-box"></i>
                    </div>
                </div>
                <div class="kpi-value">{{ number_format($itemsSold) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Total unit volume') }}</small>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Discounts') }}</span>
                    <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="uil uil-pricetag-alt"></i>
                    </div>
                </div>
                <div class="kpi-value text-warning">{{ $money($discounts) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Applied discounts') }}</small>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Tax Collected') }}</span>
                    <div class="kpi-icon" style="background: #f8fafc; color: #64748b;">
                        <i class="uil uil-file-bookmark-alt"></i>
                    </div>
                </div>
                <div class="kpi-value text-muted">{{ $money($taxes) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Applicable taxes') }}</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h4 class="chart-card-title">{{ __('Sales & Orders Trend') }}</h4>
                        <p class="chart-card-subtitle">{{ __('Daily revenue volume and completed order counts') }}</p>
                    </div>
                    <span class="badge badge-soft-primary px-2 py-1 font-size-12">{{ ucfirst(str_replace('_', ' ', $selectedPeriod)) }}</span>
                </div>
                <div id="sales-trend-chart" style="min-height: 310px;"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="chart-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h4 class="chart-card-title">{{ __('Payment Methods') }}</h4>
                        <p class="chart-card-subtitle">{{ __('Revenue distribution by payment channel') }}</p>
                    </div>
                </div>
                <div id="payment-distribution-chart" style="min-height: 230px;"></div>

                <div class="mt-3">
                    @forelse ($distList as $item)
                        @php
                            $mName = $item->payment_method ?? ($item['method'] ?? 'Unknown');
                            $mTotal = $item->total_amount ?? ($item['total'] ?? 0);
                            $mCount = $item->orders_count ?? ($item['count'] ?? 0);
                            $mPct = $item->percentage ?? ($item['percentage'] ?? 0);
                        @endphp
                        <div class="d-flex align-items-center justify-content-between py-1 border-bottom font-size-12">
                            <span class="fw-semibold text-capitalize text-dark">{{ str_replace('_', ' ', $mName) }}</span>
                            <div>
                                <span class="text-muted me-2">{{ $mCount }} {{ __('txns') }}</span>
                                <span class="fw-bold text-dark">{{ $money($mTotal) }}</span>
                                <span class="badge badge-soft-secondary ms-1">{{ $mPct }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-2 text-muted font-size-12">{{ __('No payment records for this period') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Data Table -->
    <div class="data-table-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="chart-card-title mb-1">{{ __('Completed Sales Transactions') }}</h4>
                <p class="chart-card-subtitle mb-0">
                    @if ($salesList instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        {{ __('Showing :from to :to of :total records', [
                            'from' => $salesList->firstItem() ?? 0,
                            'to' => $salesList->lastItem() ?? 0,
                            'total' => $salesList->total()
                        ]) }}
                    @else
                        {{ __('Showing :total records', ['total' => count($salesList)]) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Invoice #') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Completed At') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Customer') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Order Type') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-center">{{ __('Items') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Payment Method') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Total') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-center">{{ __('Status') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-center" style="width: 140px;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesList as $sale)
                        @php
                            $pmString = $sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : ($sale->payments->pluck('method')->filter()->unique()->map(fn ($m) => ucfirst(str_replace('_', ' ', $m)))->join(', ') ?: ($sale->snapshot['paymentMethod'] ?? __('N/A')));
                            $cName = $sale->customer_name ?: ($sale->snapshot['customer']['name'] ?? __('Walk-in customer'));
                            $cCode = $sale->customer_code ?: ($sale->snapshot['customer']['code'] ?? '');
                            $cPhone = $sale->snapshot['customer']['phone'] ?? '';
                            $oType = ucfirst(str_replace('_', ' ', $sale->order_type ?: ($sale->snapshot['orderType'] ?? 'Takeaway')));
                            $dt = $sale->completed_at ? \Carbon\Carbon::parse($sale->completed_at) : ($sale->created_at ? \Carbon\Carbon::parse($sale->created_at) : now());
                            $saleItems = $sale->items->isNotEmpty() ? $sale->items->map(function ($it) use ($resolveLineCurrency) {
                                $lineCurrency = $resolveLineCurrency($it->currency_code);

                                return [
                                    'name' => $it->name,
                                    'sku' => $it->sku,
                                    'uom' => $it->uom_name ?: ($it->uom_code ?: (is_array($it->selected_options) ? collect($it->selected_options)->pluck('label')->filter()->join(', ') : 'Standard')),
                                    'quantity' => (float) $it->quantity,
                                    'currency_code' => $lineCurrency['code'],
                                    'currency_symbol' => $lineCurrency['symbol'],
                                    'currency_decimals' => $lineCurrency['decimals'],
                                    'unit_price' => (float) $it->unit_price,
                                    'line_total' => (float) $it->unit_price * (float) $it->quantity,
                                ];
                            })->values() : collect($sale->snapshot['cart'] ?? [])->map(function ($cIt) use ($resolveLineCurrency) {
                                $selectedVariants = is_array($cIt['selectedVariants'] ?? null)
                                    ? collect($cIt['selectedVariants'])->pluck('label')->filter()->join(', ')
                                    : '';
                                $embeddedCurrency = is_array($cIt['product']['currency'] ?? null) ? $cIt['product']['currency'] : [];
                                $lineCurrency = $resolveLineCurrency($embeddedCurrency['code'] ?? null, $embeddedCurrency);

                                return [
                                    'name' => $cIt['product']['name'] ?? 'Item',
                                    'sku' => $cIt['product']['sku'] ?? '',
                                    'uom' => $cIt['selectedUOM']['name'] ?? ($selectedVariants ?: 'Standard'),
                                    'quantity' => (float) ($cIt['quantity'] ?? 1),
                                    'currency_code' => $lineCurrency['code'],
                                    'currency_symbol' => $lineCurrency['symbol'],
                                    'currency_decimals' => $lineCurrency['decimals'],
                                    'unit_price' => (float) ($cIt['unitPrice'] ?? 0),
                                    'line_total' => (float) (($cIt['unitPrice'] ?? 0) * ($cIt['quantity'] ?? 1)),
                                ];
                            })->values();

                            $subtotalBase = (float) $sale->subtotal_base;
                            $discountBase = (float) $sale->discount_base;
                            $promoDiscountBase = (float) $sale->promotion_discount_base;
                            $taxBase = (float) $sale->tax_base;
                            $serviceFeeBase = (float) $sale->service_fee_base;
                            $totalBase = (float) $sale->total_base;
                            $itemCountBase = (int) $sale->item_count;

                            $cartItems = $sale->snapshot['cart'] ?? [];
                            $cartSubtotal = collect($cartItems)->sum(fn ($cIt) => (float) ($cIt['unitPrice'] ?? 0) * (float) ($cIt['quantity'] ?? 1));
                            $cartItemCount = (int) collect($cartItems)->sum(fn ($cIt) => (float) ($cIt['quantity'] ?? 1));

                            $appliedPromo = $sale->promotion_name ? [
                                'id' => $sale->promotion_id,
                                'name' => $sale->promotion_name,
                                'savings' => $promoDiscountBase,
                            ] : ($sale->snapshot['appliedPromotion'] ?? ($sale->snapshot['applied_promotion'] ?? null));
                            $promoName = $appliedPromo['name'] ?? null;
                            $promoSavings = (float) ($promoDiscountBase > 0 ? $promoDiscountBase : ($sale->snapshot['promotionDiscountAmount'] ?? ($appliedPromo['savings'] ?? 0)));
                            $totalDiscount = $discountBase > 0 ? $discountBase : (float) ($sale->snapshot['discountAmount'] ?? $promoSavings);
                            $resolvedPromotionDiscount = $appliedPromo ? min($totalDiscount > 0 ? $totalDiscount : $promoSavings, max(0, $promoSavings)) : 0;
                            $manualDiscount = max(0, $totalDiscount - $resolvedPromotionDiscount);
                            $discountType = $sale->discount_type ?: ($sale->snapshot['discountType'] ?? 'percentage');
                            $discountValue = (float) ($sale->discount_value ?: ($sale->snapshot['discountValue'] ?? 0));
                            $taxPercent = (float) ($sale->tax_percent ?: ($sale->snapshot['taxPercent'] ?? 0));

                            $subtotal = $subtotalBase > 0 ? $subtotalBase : (float) ($sale->snapshot['subTotal'] ?? ($cartSubtotal > 0 ? $cartSubtotal : $totalBase));
                            $tax = $taxBase > 0 ? $taxBase : (float) ($sale->snapshot['taxAmount'] ?? 0);
                            $serviceFee = $serviceFeeBase > 0 ? $serviceFeeBase : (float) ($sale->snapshot['serviceFee'] ?? 0);
                            $grandTotal = $totalBase > 0 ? $totalBase : (float) ($sale->snapshot['totalPayable'] ?? max(0, $subtotal - $totalDiscount + $tax + $serviceFee));
                            $totalItems = $itemCountBase > 0 ? $itemCountBase : ($saleItems->sum('quantity') ?: $cartItemCount);
                            $exchangeRateRemark = $sale->items
                                ->filter(fn ($item) => filled($item->currency_code)
                                    && strtoupper((string) $item->currency_code) !== strtoupper((string) $currencyCode)
                                    && (float) $item->exchange_rate > 0)
                                ->mapWithKeys(fn ($item) => [strtoupper((string) $item->currency_code) => (float) $item->exchange_rate])
                                ->map(fn ($rate, $code) => '1 ' . strtoupper((string) $currencyCode) . ' = '
                                    . rtrim(rtrim(number_format($rate, 8, '.', ','), '0'), '.') . ' ' . $code)
                                ->values()
                                ->join(' · ');

                            $salePayload = [
                                'id' => $sale->id,
                                'invoice_number' => $sale->invoice_number ?: ('#' . $sale->id),
                                'completed_at' => $dt->format('M d, Y · h:i A'),
                                'completed_date' => $dt->format('Y-m-d'),
                                'completed_time' => $dt->format('h:i:s A'),
                                'customer_name' => $cName,
                                'customer_code' => $cCode,
                                'customer_phone' => $cPhone,
                                'order_type' => $oType,
                                'payment_method' => $pmString,
                                'subtotal' => $subtotal,
                                'discount' => $totalDiscount,
                                'manual_discount' => $manualDiscount,
                                'promotion_discount' => $resolvedPromotionDiscount,
                                'applied_promotion' => $appliedPromo ? ['name' => $promoName, 'savings' => $resolvedPromotionDiscount] : null,
                                'discount_type' => $discountType,
                                'discount_value' => $discountValue,
                                'tax' => $tax,
                                'tax_percent' => $taxPercent,
                                'service_fee' => $serviceFee,
                                'total' => $grandTotal,
                                'item_count' => $totalItems,
                                'exchange_rate_remark' => $exchangeRateRemark,
                                'print_url_a4' => route('admin.reports.sales.print', ['sale' => $sale->id, 'format' => 'a4']),
                                'print_url_thermal' => route('admin.reports.sales.print', ['sale' => $sale->id, 'format' => 'thermal']),
                                'items' => $saleItems,
                            ];
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-bold text-dark font-monospace">{{ $sale->invoice_number ?: ('#' . $sale->id) }}</span>
                            </td>
                            <td>
                                <span class="font-size-12 text-muted">{{ $dt->format('M d, Y H:i') }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $cName }}</span>
                                @if ($cPhone)
                                    <small class="d-block text-muted font-size-11">{{ $cPhone }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-soft-info text-capitalize">{{ $oType }}</span>
                            </td>
                            <td class="text-center font-size-13 font-monospace">
                                {{ $totalItems }}
                            </td>
                            <td>
                                <span class="badge badge-soft-secondary font-size-12">{{ $pmString }}</span>
                            </td>
                            <td class="text-end font-size-14 fw-bold text-dark">
                                {{ $money($sale->total_base) }}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-soft-success font-size-11">{{ __('Completed') }}</span>
                            </td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1 font-size-12 btn-open-invoice"
                                    data-sale="{{ json_encode($salePayload) }}"
                                    title="{{ __('View Invoice Details') }}">
                                    <i class="uil uil-receipt me-1"></i> {{ __('Invoice') }}
                                </button>
                                <a href="{{ route('admin.reports.sales.print', ['sale' => $sale->id, 'format' => 'a4']) }}"
                                    target="_blank"
                                    class="btn btn-sm btn-light px-2 py-1 text-muted"
                                    title="{{ __('Print Invoice in New Tab') }}">
                                    <i class="uil uil-print"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="uil uil-inbox font-size-36 d-block mb-2 text-muted"></i>
                                {{ __('No completed sales found for the selected period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($salesList instanceof \Illuminate\Pagination\LengthAwarePaginator && $salesList->hasPages())
            <div class="mt-4 d-flex justify-content-end">
                {{ $salesList->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Interactive Invoice Details / Print Modal -->
<div class="modal fade" id="invoiceDetailModal" tabindex="-1" aria-labelledby="invoiceDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <h6 class="modal-title fw-bold mb-0" id="invoiceDetailModalLabel">{{ __('Invoice Preview') }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="invoice-format-switch" role="group" aria-label="Invoice format">
                    <button type="button" class="active" id="btnFormatA4" onclick="switchModalFormat('a4')">
                        <i class="uil uil-file-alt"></i> A4 / PDF
                    </button>
                    <button type="button" id="btnFormatThermal" onclick="switchModalFormat('thermal')">
                        <i class="uil uil-receipt"></i> Thermal 80mm
                    </button>
                </div>

                <div id="modalInvoiceContent">
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3 invoice-preview-actions">
                    <button type="button" class="btn btn-outline-secondary" onclick="printModalInvoice()">
                        <i class="uil uil-print me-1"></i><span id="modalPrintButtonLabel">{{ __('Print A4 / PDF') }}</span>
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        {{ __('Close') }}
                    </button>
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

    // Modal state & company info
    let currentSaleData = null;
    let currentFormat = 'a4';
    const currencySymbol = @json($currencySymbol);
    const currencyDecimals = @json($currencyDecimals);
    const currencyCode = @json($currencyCode);
    const companyInfo = {
        name: @json($companyName),
        address: @json($companyAddress),
        phone: @json($companyPhone),
        email: @json($companyEmail),
        receipt_footer: @json($receiptFooter)
    };

    function formatMoney(amount) {
        const separator = currencySymbol.length > 1 ? ' ' : '';
        return currencySymbol + separator + Number(amount || 0).toFixed(currencyDecimals);
    }

    function formatLineMoney(amount, item) {
        const symbol = item.currency_symbol || item.currency_code || currencySymbol;
        const decimals = Number.isInteger(Number(item.currency_decimals))
            ? Math.max(0, Number(item.currency_decimals))
            : currencyDecimals;
        const separator = symbol.length > 1 ? ' ' : '';

        return symbol + separator + Number(amount || 0).toFixed(decimals);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function switchModalFormat(fmt) {
        currentFormat = fmt;
        const btnA4 = document.getElementById('btnFormatA4');
        const btnThermal = document.getElementById('btnFormatThermal');

        if (btnA4 && btnThermal) {
            btnA4.classList.toggle('active', fmt === 'a4');
            btnThermal.classList.toggle('active', fmt === 'thermal');
        }
        const printButtonLabel = document.getElementById('modalPrintButtonLabel');
        if (printButtonLabel) {
            printButtonLabel.textContent = fmt === 'a4' ? 'Print A4 / PDF' : 'Print Thermal';
        }

        if (currentSaleData) {
            renderModalInvoiceContent();
        }
    }

    function renderModalInvoiceContent() {
        if (!currentSaleData) return;
        const container = document.getElementById('modalInvoiceContent');
        const s = currentSaleData;
        const comp = companyInfo;

        if (currentFormat === 'a4') {
            let itemsHtml = '';
            (s.items || []).forEach(it => {
                itemsHtml += `
                    <tr>
                        <td>
                            <strong class="d-block text-dark">${escapeHtml(it.name)}</strong>
                            ${it.sku ? `<small class="text-muted font-monospace">${escapeHtml(it.sku)}</small>` : ''}
                        </td>
                        <td class="text-muted font-size-12">${escapeHtml(it.uom || 'Standard')}</td>
                        <td class="text-center font-monospace">${Number(it.quantity)}</td>
                        <td class="text-end text-muted">${formatLineMoney(it.unit_price, it)}</td>
                        <td class="text-end fw-semibold text-dark">${formatLineMoney(it.line_total, it)}</td>
                    </tr>
                `;
            });

            let discountRowsHtml = '';
            if (s.manual_discount > 0 || !s.applied_promotion) {
                let discLabel = 'Discount';
                if (s.discount_type === 'percentage' && s.discount_value > 0) {
                    discLabel += ` (${Number(s.discount_value)}%)`;
                } else if (s.discount_type === 'fixed') {
                    discLabel += ' (Fixed)';
                }
                discountRowsHtml += `
                    <div class="${s.manual_discount > 0 ? 'invoice-preview-discount' : ''}">
                        <span>${escapeHtml(discLabel)}</span>
                        <strong>${s.manual_discount > 0 ? '-' : ''}${formatMoney(s.manual_discount)}</strong>
                    </div>
                `;
            }
            if (s.applied_promotion && s.applied_promotion.name) {
                discountRowsHtml += `
                    <div class="invoice-preview-discount">
                        <span>Promotion · ${escapeHtml(s.applied_promotion.name)}</span>
                        <strong>-${formatMoney(s.promotion_discount)}</strong>
                    </div>
                `;
            }

            let taxHtml = `
                <div>
                    <span>GST Tax (${Number(s.tax_percent || 0)}%)</span>
                    <strong>${formatMoney(s.tax)}</strong>
                </div>
            `;
            let feeHtml = `
                <div>
                    <span>Service Fee</span>
                    <strong>${formatMoney(s.service_fee)}</strong>
                </div>
            `;

            container.innerHTML = `
                <article class="invoice-preview-sheet border rounded-3 bg-white">
                    <header class="invoice-preview-header">
                        <div class="invoice-preview-company">
                            <div class="invoice-preview-company-mark" aria-hidden="true">
                                <i class="uil uil-store"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold">${escapeHtml(comp.name)}</h4>
                                ${comp.address ? `<div class="text-muted font-size-12 invoice-preview-address">${escapeHtml(comp.address)}</div>` : ''}
                                ${comp.phone ? `<div class="text-muted font-size-12 mt-1">${escapeHtml(comp.phone)}</div>` : ''}
                            </div>
                        </div>
                        <div class="invoice-preview-title text-end">
                            <div class="text-uppercase fw-bolder">INVOICE</div>
                            <div class="invoice-preview-header-meta">
                                <div class="invoice-preview-header-meta-item invoice-preview-number-row">
                                    <span><span>#</span><span>:</span></span>
                                    <strong>${escapeHtml(s.invoice_number)}</strong>
                                </div>
                                <div class="invoice-preview-header-meta-item invoice-preview-time-row">
                                    <span><span>Time</span><span>:</span></span>
                                    <strong>${escapeHtml(s.completed_at)}</strong>
                                </div>
                                <div class="invoice-preview-header-meta-item">
                                    <span><span>BILL TO</span><span>:</span></span>
                                    <strong>${escapeHtml(s.customer_name)}</strong>
                                </div>
                                <div class="invoice-preview-header-meta-item">
                                    <span><span>ORDER</span><span>:</span></span>
                                    <strong>${escapeHtml(s.order_type)}</strong>
                                </div>
                                <div class="invoice-preview-header-meta-item">
                                    <span><span>PAYMENT</span><span>:</span></span>
                                    <strong>${escapeHtml(s.payment_method)}</strong>
                                </div>
                            </div>
                        </div>
                    </header>
                    <div class="table-responsive invoice-preview-table-wrap">
                        <table class="table table-sm align-middle mb-0 font-size-12 invoice-preview-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>UOM / Option</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml || '<tr><td colspan="5" class="text-center py-4 text-muted">No items recorded</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    <section class="invoice-preview-summary">
                        <div>
                            <span>Subtotal</span>
                            <strong>${formatMoney(s.subtotal)}</strong>
                        </div>
                        ${discountRowsHtml}
                        ${taxHtml}
                        ${feeHtml}
                        <div class="invoice-preview-total">
                            <span class="d-flex flex-column align-items-start gap-1">
                                <span>Grand Total <small>(${escapeHtml(currencyCode)})</small></span>
                                <small>${s.item_count} ${s.item_count === 1 ? 'item' : 'items'} included</small>
                            </span>
                            <strong>${formatMoney(s.total)}</strong>
                        </div>
                    </section>
                    <footer class="invoice-preview-footer">
                        ${s.exchange_rate_remark ? `
                            <div class="invoice-preview-rate-remark">
                                <i class="uil uil-info-circle"></i>
                                <span>Exchange rate used: ${escapeHtml(s.exchange_rate_remark)}</span>
                            </div>
                        ` : ''}
                        <div class="invoice-preview-footer-message">
                            <i class="uil uil-heart"></i>
                            <span>${escapeHtml(comp.receipt_footer || 'Thank you for your business.')}</span>
                        </div>
                    </footer>
                </article>
            `;
        } else {
            // Thermal 80mm
            let itemsHtml = '';
            (s.items || []).forEach(it => {
                const itemDescription = [it.sku, it.uom || 'Standard'].filter(Boolean).join(' · ');
                itemsHtml += `
                    <tr>
                        <td>
                            <strong>${escapeHtml(it.name)}</strong>
                            <small>${escapeHtml(itemDescription)}</small>
                        </td>
                        <td class="text-center">${Number(it.quantity)}</td>
                        <td class="text-end">${formatLineMoney(it.line_total, it)}</td>
                    </tr>
                `;
            });

            let thermalDiscountRowsHtml = '';
            if (s.manual_discount > 0 || !s.applied_promotion) {
                let discLabel = 'Discount';
                if (s.discount_type === 'percentage' && s.discount_value > 0) {
                    discLabel += ` (${Number(s.discount_value)}%)`;
                }
                thermalDiscountRowsHtml += `
                    <div class="${s.manual_discount > 0 ? 'thermal-discount' : ''}">
                        <span>${escapeHtml(discLabel)}</span>
                        <strong>${s.manual_discount > 0 ? '-' : ''}${formatMoney(s.manual_discount)}</strong>
                    </div>
                `;
            }
            if (s.applied_promotion && s.applied_promotion.name) {
                thermalDiscountRowsHtml += `
                    <div class="thermal-discount">
                        <span>Promotion · ${escapeHtml(s.applied_promotion.name)}</span>
                        <strong>-${formatMoney(s.promotion_discount)}</strong>
                    </div>
                `;
            }

            let thermalTaxHtml = `<div><span>GST Tax (${Number(s.tax_percent || 0)}%)</span><strong>${formatMoney(s.tax)}</strong></div>`;
            let thermalFeeHtml = `<div><span>Service Fee</span><strong>${formatMoney(s.service_fee)}</strong></div>`;

            container.innerHTML = `
                <article class="thermal-receipt">
                    <header class="thermal-receipt-header">
                        <h3>${escapeHtml(comp.name)}</h3>
                        ${comp.address ? `<div>${escapeHtml(comp.address)}</div>` : ''}
                        ${comp.phone ? `<div>Tel: ${escapeHtml(comp.phone)}</div>` : ''}
                        <h4>INVOICE</h4>
                        <div class="thermal-receipt-meta">
                            <div><span>Invoice #:</span><strong>${escapeHtml(s.invoice_number)}</strong></div>
                            <div><span>Date:</span><strong>${escapeHtml(s.completed_date)}</strong></div>
                            <div><span>Time:</span><strong>${escapeHtml(s.completed_time)}</strong></div>
                            <div><span>Customer:</span><strong>${escapeHtml(s.customer_name)}</strong></div>
                            ${s.customer_code ? `<div><span>Customer ID:</span><strong>${escapeHtml(s.customer_code)}</strong></div>` : ''}
                            ${s.customer_phone ? `<div><span>Phone:</span><strong>${escapeHtml(s.customer_phone)}</strong></div>` : ''}
                            <div><span>Order:</span><strong>${escapeHtml(s.order_type)}</strong></div>
                            <div><span>Payment:</span><strong>${escapeHtml(s.payment_method)}</strong></div>
                        </div>
                    </header>
                    <div class="thermal-receipt-rule"></div>
                    <table class="thermal-receipt-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml || '<tr><td colspan="3" class="text-center">No items recorded</td></tr>'}
                        </tbody>
                    </table>
                    <section class="thermal-receipt-summary">
                        <div><span>Subtotal</span><strong>${formatMoney(s.subtotal)}</strong></div>
                        ${thermalDiscountRowsHtml}
                        ${thermalTaxHtml}
                        ${thermalFeeHtml}
                        <div class="thermal-receipt-total">
                            <span>TOTAL</span>
                            <strong>${formatMoney(s.total)}</strong>
                        </div>
                        <small>${s.item_count} ${s.item_count === 1 ? 'item' : 'items'} included</small>
                    </section>
                    <footer class="thermal-receipt-footer">
                        ${s.exchange_rate_remark ? `<div>Exchange rate used: ${escapeHtml(s.exchange_rate_remark)}</div>` : ''}
                        <strong>${escapeHtml(comp.receipt_footer || 'Thank you for your business.')}</strong>
                    </footer>
                </article>
            `;
        }
    }

    function printModalInvoice() {
        if (!currentSaleData) return;
        const url = currentFormat === 'a4' ? currentSaleData.print_url_a4 : currentSaleData.print_url_thermal;
        printInvoiceUrl(url);
    }

    function printInvoiceUrl(url) {
        const printFrame = document.createElement('iframe');
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        printFrame.src = url + (url.includes('?') ? '&' : '?') + 'auto_print=0';
        document.body.appendChild(printFrame);

        printFrame.addEventListener('load', () => {
            try {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
            } catch (e) {
                window.open(url, '_blank');
            }
            setTimeout(() => {
                printFrame.remove();
            }, 60000);
        }, { once: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Event delegation for opening invoice detail modal
        document.querySelectorAll('.btn-open-invoice').forEach(btn => {
            btn.addEventListener('click', function () {
                const rawJson = this.getAttribute('data-sale');
                if (!rawJson) return;
                try {
                    const saleData = JSON.parse(rawJson);
                    currentSaleData = saleData;
                    switchModalFormat('a4');
                    const modal = new bootstrap.Modal(document.getElementById('invoiceDetailModal'));
                    modal.show();
                } catch (e) {
                    console.error('Failed to parse sale JSON', e);
                }
            });
        });
    });

    (function () {
        if (typeof ApexCharts === 'undefined') return;

        // Sales Trend Chart
        const chartCategories = @json($chartCats);
        const chartRevenue = @json($chartRev);
        const chartOrders = @json($chartOrd);

        const trendElement = document.querySelector('#sales-trend-chart');
        if (trendElement && chartCategories && chartCategories.length > 0) {
            new ApexCharts(trendElement, {
                chart: {
                    height: 310,
                    type: 'line',
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    zoom: { enabled: false }
                },
                series: [
                    { name: @json(__('Revenue')), type: 'column', data: chartRevenue },
                    { name: @json(__('Orders')), type: 'line', data: chartOrders }
                ],
                colors: ['#4f46e5', '#10b981'],
                stroke: { width: [0, 3], curve: 'smooth' },
                plotOptions: { bar: { borderRadius: 6, columnWidth: '38%' } },
                fill: {
                    type: ['gradient', 'solid'],
                    gradient: { shadeIntensity: 0.1, opacityFrom: 0.9, opacityTo: 0.6, stops: [0, 100] }
                },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                markers: { size: 4, colors: ['#10b981'], strokeColors: '#fff', strokeWidth: 2 },
                xaxis: {
                    categories: chartCategories,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#64748b', fontSize: '11px' } }
                },
                yaxis: [
                    {
                        labels: {
                            style: { colors: '#64748b', fontSize: '11px' },
                            formatter: val => currencySymbol + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })
                        }
                    },
                    {
                        opposite: true,
                        min: 0,
                        tickAmount: 4,
                        labels: {
                            style: { colors: '#64748b', fontSize: '11px' },
                            formatter: val => Math.round(val)
                        }
                    }
                ],
                legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val, ctx) {
                            if (ctx.seriesIndex === 0) {
                                return currencySymbol + Number(val).toFixed(currencyDecimals);
                            }
                            return Math.round(val) + ' ' + @json(__('orders'));
                        }
                    }
                }
            }).render();
        } else if (trendElement) {
            trendElement.innerHTML = '<div class="text-center py-5 text-muted font-size-13">{{ __("No trend data available for this period") }}</div>';
        }

        // Payment Donut Chart
        const rawDist = @json($distList);
        const donutElement = document.querySelector('#payment-distribution-chart');
        if (donutElement && rawDist && rawDist.length > 0) {
            const labels = rawDist.map(item => (item.payment_method || item.method || 'Unknown').replace('_', ' ').toUpperCase());
            const series = rawDist.map(item => Number(item.total_amount || item.total || 0));

            new ApexCharts(donutElement, {
                chart: { height: 230, type: 'donut', fontFamily: 'inherit' },
                series: series,
                labels: labels,
                colors: ['#4f46e5', '#10b981', '#f59e0b', '#8b5cf6', '#0ea5e9'],
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
                legend: { show: false },
                tooltip: {
                    y: {
                        formatter: val => currencySymbol + Number(val).toFixed(currencyDecimals)
                    }
                }
            }).render();
        } else if (donutElement) {
            donutElement.innerHTML = '<div class="text-center py-4 text-muted font-size-12">{{ __("No payment data for this period") }}</div>';
        }
    })();
</script>
@endpush
