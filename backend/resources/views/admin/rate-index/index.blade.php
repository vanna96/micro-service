@extends('layouts.app')

@section('title', 'Rate Index')
@section('page_title', 'Rate Index')

@push('styles')
<style>
    .rate-grid-table th,
    .rate-grid-table td {
        min-width: 110px;
        vertical-align: middle;
    }

    .rate-grid-table th:first-child,
    .rate-grid-table td:first-child {
        min-width: 70px;
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
</style>
@endpush

@section('content')
@php($canManageRateIndex = admin_has_permission('rate_indexes.manage'))
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

                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            @for ($month = 1; $month <= 12; $month++)
                                <option value="{{ $month }}" @selected($selectedMonth === $month)>
                                    {{ \Carbon\Carbon::create($selectedYear, $month, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
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
                        <button type="submit" class="btn btn-primary">Load</button>
                        <a href="{{ route('admin.rate-index.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                    <form method="POST" action="{{ route('admin.rate-index.update') }}">
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
                                        @php($isDisabledDay = $day > $daysInMonth)
                                        <tr>
                                            <td class="fw-semibold">{{ $day }}</td>
                                            @foreach ($currencies as $currency)
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="{{ $currency->input_step }}"
                                                        min="0"
                                                        name="cells[{{ $day }}][{{ $currency->id }}]"
                                                        value="{{ old("cells.$day.$currency->id", data_get($gridValues, $day . '.' . $currency->id, '')) }}"
                                                        class="form-control form-control-sm @error("cells.$day.$currency->id") is-invalid @enderror"
                                                        @disabled($isDisabledDay || ! $canManageRateIndex)
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

                        @if ($canManageRateIndex)
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="uil-save me-1"></i>Save Exchange Rates
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
