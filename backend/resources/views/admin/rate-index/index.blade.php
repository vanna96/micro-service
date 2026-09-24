@extends('layouts.app')

@section('title', 'Exchange Rates')
@section('page_title', 'Exchange Rates')

@push('styles')
<style>
    .rate-grid-table {
        width: max-content !important;
        table-layout: fixed;
    }

    .rate-grid-table th,
    .rate-grid-table td {
        width: 128px;
        min-width: 128px;
        max-width: 128px;
        padding: 0.4rem 0.5rem !important;
        font-size: 0.8125rem;
        line-height: 1.2;
        vertical-align: middle;
    }

    .rate-grid-table th:first-child,
    .rate-grid-table td:first-child {
        width: 112px;
        min-width: 112px;
        max-width: 112px;
        position: sticky;
        left: 0;
        z-index: 2;
        background: #f8f9fa;
    }

    .rate-grid-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #f8f9fa;
    }

    .rate-grid-wrapper {
        max-height: 70vh;
        overflow: auto;
        border: 1px solid #e9e9ef;
        border-radius: 0.375rem;
    }

    .rate-grid-table input:disabled {
        background-color: #f4f5f7;
        cursor: not-allowed;
    }

    .rate-grid-table .form-control-sm {
        min-height: 30px;
        padding: 0.25rem 0.4rem;
        font-size: 0.8125rem;
        line-height: 1.2;
    }

    .rate-grid-table tbody tr.rate-grid-today > td {
        background-color: #eef2ff;
    }

    .rate-grid-table tbody tr.rate-grid-today > td:first-child {
        background-color: #4f46e5;
        color: #fff;
    }

    .rate-grid-table tbody tr.rate-grid-today .form-control {
        border-color: #818cf8;
        box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.12);
    }
</style>
@endpush

@section('content')
@php
    $canEditRateIndex = admin_has_permission('rate_indexes.edit');
@endphp
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Exchange Rates</h4>
                        <p class="card-title-desc mb-0">
                            Maintain daily exchange rates in a month-by-currency matrix for the selected tenant.
                        </p>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-chart-line me-2"></i>Working with tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                    Base currency:
                    <strong>{{ $baseCurrency?->code ?: 'Not configured' }}</strong>.
                </div>

                <form method="GET" id="rate-index-filters" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select rate-index-auto-load">
                            @for ($month = 1; $month <= 12; $month++)
                                <option value="{{ $month }}" @selected($selectedMonth === $month)>
                                    {{ \Carbon\Carbon::create($selectedYear, $month, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select rate-index-auto-load">
                            @foreach ($yearOptions as $yearOption)
                                <option value="{{ $yearOption }}" @selected($selectedYear === $yearOption)>{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Base Currency</label>
                        <input type="text" class="form-control" value="{{ $baseCurrency?->code ?: 'Not configured' }}" disabled>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <a href="{{ route('admin.rate-index.index') }}" class="btn btn-outline-secondary">
                            <i class="uil uil-redo me-1"></i>Reset
                        </a>
                        @if ($canEditRateIndex && $baseCurrency && $currencies->isNotEmpty())
                            <button type="button" class="btn btn-primary" id="btn-top-save-rate-grid">
                                <i class="uil uil-save me-1"></i>Save
                            </button>
                        @endif
                    </div>
                </form>

                @if (! $baseCurrency)
                    <div class="alert alert-warning mb-0">
                        Set an active base currency in <a href="{{ route('admin.general-settings.index') }}">General</a> before maintaining the grid.
                    </div>
                @elseif ($currencies->isEmpty())
                    <div class="alert alert-warning mb-0">
                        Create active currencies in <a href="{{ route('admin.currencies.index') }}">Currency</a> before maintaining the grid.
                    </div>
                @else
                    @php
                        $oldYear = old('year');
                        $oldMonth = old('month');
                        $oldInputMatchesPeriod = $oldYear !== null
                            && $oldMonth !== null
                            && (int) $oldYear === $selectedYear
                            && (int) $oldMonth === $selectedMonth;
                    @endphp
                    <form method="POST" action="{{ route('admin.rate-index.update') }}" id="rate-grid-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="year" value="{{ $selectedYear }}">
                        <input type="hidden" name="month" value="{{ $selectedMonth }}">

                        <div class="rate-grid-wrapper">
                            <table class="table table-bordered mb-0 rate-grid-table">
                                <thead>
                                    <tr>
                                        <th>{{ $monthLabel }}</th>
                                        @foreach ($currencies as $currency)
                                            <th>
                                                <div>{{ $currency->code }}</div>
                                                <div class="text-muted font-size-11">{{ $currency->decimal_places }} dp</div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($days as $day)
                                        @php
                                            $isDisabledDay = $day > $daysInMonth;
                                        @endphp
                                        <tr @class(['rate-grid-today' => $todayDay === $day])>
                                            <td class="fw-semibold">{{ $day }}</td>
                                            @foreach ($currencies as $currency)
                                                @php
                                                    $storedCellValue = data_get($gridValues, $day . '.' . $currency->id, '');
                                                    $cellValue = $oldInputMatchesPeriod
                                                        ? old("cells.$day.$currency->id", $storedCellValue)
                                                        : $storedCellValue;
                                                    if (! $oldInputMatchesPeriod && $cellValue !== '') {
                                                        $cellValue = number_format((float) $cellValue, (int) $currency->decimal_places, '.', '');
                                                    }
                                                @endphp
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="{{ $currency->input_step }}"
                                                        min="0"
                                                        name="cells[{{ $day }}][{{ $currency->id }}]"
                                                        value="{{ $cellValue }}"
                                                        class="form-control form-control-sm @error("cells.$day.$currency->id") is-invalid @enderror"
                                                        @disabled($isDisabledDay || ! $canEditRateIndex)
                                                    >
                                                    @error("cells.$day.$currency->id")
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filters = document.getElementById('rate-index-filters');

        if (filters) {
            filters.querySelectorAll('.rate-index-auto-load').forEach(function (select) {
                select.addEventListener('change', function () {
                    filters.submit();
                });
            });
        }

        const topSaveBtn = document.getElementById('btn-top-save-rate-grid');
        if (topSaveBtn) {
            topSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const form = document.getElementById('rate-grid-form');
                if (form) {
                    form.requestSubmit();
                }
            });
        }
    });
</script>
@endpush
