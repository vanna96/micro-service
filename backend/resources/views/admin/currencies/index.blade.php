@extends('layouts.app')

@section('title', 'Currencies')
@section('page_title', 'Currencies')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreateCurrencies = admin_has_permission('currencies.create'))
@php($canEditCurrencies = admin_has_permission('currencies.edit'))
@php($canDeleteCurrencies = admin_has_permission('currencies.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Currency master</h4>
                        <p class="card-title-desc mb-0">
                            Create currencies before maintaining monthly exchange rates.
                        </p>
                    </div>
                    @if ($canCreateCurrencies)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.currencies.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Currency
                            </a>
                        </div>
                    @endif
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-currency-usd me-2"></i>Showing currencies for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-currencies" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Format</th>
                                <th>Sort Order</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($currencies as $currency)
                                <tr>
                                    <td class="fw-semibold">{{ $currency->code }}</td>
                                    <td>{{ $currency->name }}</td>
                                    <td>{{ $currency->symbol ?: '-' }}</td>
                                    <td>{{ $currency->decimal_places }} dp · {{ $currency->format_example }}</td>
                                    <td>{{ $currency->sort_order }}</td>
                                    <td>
                                        <span class="badge {{ $currency->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $currency->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($currency->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditCurrencies)
                                            <a href="{{ route('admin.currencies.edit', ['currency' => $currency->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteCurrencies)
                                            <form action="{{ route('admin.currencies.destroy', ['currency' => $currency->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this currency?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditCurrencies && ! $canDeleteCurrencies)
                                            <span class="text-muted">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        $('#datatable-currencies').DataTable({
            responsive: true,
            order: [[3, 'asc'], [0, 'asc']],
            language: {
                emptyTable: 'No currencies found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
