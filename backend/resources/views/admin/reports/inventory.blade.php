@extends('layouts.app')

@section('title', __('Inventory & Stock Report'))
@section('page_title', __('Inventory & Stock Report'))

@php
    $currencySymbol = $currency['symbol'] ?? ($kpis['currency_symbol'] ?? '$');
    $currencyDecimals = (int) ($currency['decimals'] ?? ($kpis['currency_decimals'] ?? 2));
    $money = fn ($val) => $currencySymbol . number_format((float) ($val ?? 0), $currencyDecimals);
    $totalActive = $total_active_items ?? ($kpis['total_items'] ?? 0);
    $healthyCount = $healthy_items ?? ($kpis['healthy_count'] ?? 0);
    $lowStockCount = $low_stock_items ?? ($kpis['low_stock_count'] ?? 0);
    $outCount = $out_of_stock_items ?? ($kpis['out_of_stock_count'] ?? 0);
    $stockValuation = $total_stock_value ?? 0;
    $invItems = $inventory_items ?? ($items ?? collect());
    $stockStatus = $filters['stock_status'] ?? ($filters['status'] ?? '');
    $healthPct = $totalActive > 0 ? round(($healthyCount / $totalActive) * 100, 1) : 0;
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
    .data-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 20px;
        margin-bottom: 24px;
    }
    .badge-soft-success { background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .badge-soft-warning { background-color: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .badge-soft-danger { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .badge-soft-secondary { background-color: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
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
            <a href="{{ route('admin.reports.products') }}" class="report-tab-link">
                <i class="uil uil-box"></i> {{ __('Product Performance') }}
            </a>
            <a href="{{ route('admin.reports.inventory') }}" class="report-tab-link active">
                <i class="uil uil-archive"></i> {{ __('Inventory & Stock') }}
            </a>
            <a href="{{ route('admin.reports.payments') }}" class="report-tab-link">
                <i class="uil uil-credit-card"></i> {{ __('Payment Summary') }}
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if (admin_has_permission('reports.export'))
                <a href="{{ route('admin.reports.inventory.export', request()->query()) }}" class="btn btn-outline-primary btn-sm px-3 font-size-13">
                    <i class="uil uil-export me-1"></i> {{ __('Export CSV') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.reports.inventory') }}" id="inventoryFilterForm">
            <div class="row g-3 align-items-end">
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
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">{{ __('Stock Status') }}</label>
                    <select name="stock_status" class="form-select form-select-sm">
                        <option value="">{{ __('All Stock Levels') }}</option>
                        <option value="healthy" {{ $stockStatus === 'healthy' ? 'selected' : '' }}>{{ __('Healthy Stock (> 10)') }}</option>
                        <option value="low" {{ $stockStatus === 'low' ? 'selected' : '' }}>{{ __('Low Stock (1 - 10)') }}</option>
                        <option value="out" {{ $stockStatus === 'out' ? 'selected' : '' }}>{{ __('Out of Stock (0)') }}</option>
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
                    <a href="{{ route('admin.reports.inventory') }}" class="btn btn-light btn-sm px-3 text-muted">
                        {{ __('Reset') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 4 KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Catalog Items') }}</span>
                    <div class="kpi-icon" style="background: #eef2ff; color: #4f46e5;">
                        <i class="uil uil-box"></i>
                    </div>
                </div>
                <div class="kpi-value text-primary">{{ number_format($totalActive) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Active items in catalog') }}</small>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Healthy Stock') }}</span>
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="uil uil-check-circle"></i>
                    </div>
                </div>
                <div class="kpi-value text-success">{{ number_format($healthyCount) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __(':pct% of items in healthy range', ['pct' => $healthPct]) }}</small>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Low Stock Alert') }}</span>
                    <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="uil uil-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="kpi-value text-warning">{{ number_format($lowStockCount) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Items with 10 or fewer units') }}</small>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label">{{ __('Out of Stock') }}</span>
                    <div class="kpi-icon" style="background: #fef2f2; color: #dc2626;">
                        <i class="uil uil-times-circle"></i>
                    </div>
                </div>
                <div class="kpi-value text-danger">{{ number_format($outCount) }}</div>
                <small class="text-muted mt-2 font-size-11">{{ __('Stock valuation: :val', ['val' => $money($stockValuation)]) }}</small>
            </div>
        </div>
    </div>

    <!-- Inventory Data Table -->
    <div class="data-table-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="chart-card-title mb-1">{{ __('Stock Valuation & Inventory Levels') }}</h4>
                <p class="chart-card-subtitle mb-0">
                    @if ($invItems instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        {{ __('Showing :from to :to of :total items', [
                            'from' => $invItems->firstItem() ?? 0,
                            'to' => $invItems->lastItem() ?? 0,
                            'total' => $invItems->total()
                        ]) }}
                    @else
                        {{ __('Showing :total items', ['total' => count($invItems)]) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Item Name') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('SKU') }}</th>
                        <th class="font-size-12 text-muted fw-semibold">{{ __('Category') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Unit Price') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Cost Price') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Quantity') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-end">{{ __('Valuation') }}</th>
                        <th class="font-size-12 text-muted fw-semibold text-center">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invItems as $item)
                        @php
                            $qty = (float) ($item->stock ?? ($item->stock_quantity ?? 0));
                            $cost = (float) ($item->cost ?? ($item->cost_price ?? 0));
                            $price = (float) ($item->price ?? ($item->unit_price ?? 0));
                            $valuation = $qty * ($cost > 0 ? $cost : $price);
                            $statusBadge = $qty <= 0 
                                ? 'badge-soft-danger' 
                                : ($qty <= 10 ? 'badge-soft-warning' : 'badge-soft-success');
                            $statusText = $qty <= 0 
                                ? __('Out of Stock') 
                                : ($qty <= 10 ? __('Low Stock') : __('In Stock'));
                            $catName = $item->category?->name ?? __('Uncategorized');
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->name }}</div>
                            </td>
                            <td>
                                <span class="font-size-12 text-muted font-monospace">{{ $item->sku ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-soft-secondary">{{ $catName }}</span>
                            </td>
                            <td class="text-end font-size-13 text-dark">
                                {{ $money($price) }}
                            </td>
                            <td class="text-end font-size-13 text-muted">
                                {{ $cost > 0 ? $money($cost) : '-' }}
                            </td>
                            <td class="text-end font-size-13 font-monospace fw-bold text-dark">
                                {{ number_format($qty) }}
                            </td>
                            <td class="text-end font-size-13 fw-semibold text-dark">
                                {{ $money($valuation) }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $statusBadge }} font-size-11 px-2 py-1">{{ $statusText }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="uil uil-inbox font-size-36 d-block mb-2 text-muted"></i>
                                {{ __('No inventory items found matching the selected filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invItems instanceof \Illuminate\Pagination\LengthAwarePaginator && $invItems->hasPages())
            <div class="mt-4 d-flex justify-content-end">
                {{ $invItems->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
