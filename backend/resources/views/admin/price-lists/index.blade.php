@extends('layouts.app')

@section('title', 'Price Lists')
@section('page_title', 'Price Lists')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreatePriceLists = admin_has_permission('price_lists.create'))
@php($canEditPriceLists = admin_has_permission('price_lists.edit'))
@php($canDeletePriceLists = admin_has_permission('price_lists.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Manage tenant price-list headers and choose which active list should behave as the default commercial price book.
                        </p>
                    </div>
                    @if ($canCreatePriceLists)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.price-lists.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Price List
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
                    <i class="mdi mdi-database me-2"></i>Showing price lists for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-price-lists" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Price List</th>
                                <th>Default</th>
                                <th>Header Rule</th>
                                <th>Status</th>
                                <th>Lines</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($priceLists as $priceList)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $priceList->name }}</div>
                                        <div class="text-muted font-size-12">{{ $priceList->code }}</div>
                                    </td>
                                    <td>
                                        @if ($priceList->is_default)
                                            <span class="badge bg-primary">Default</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td>{{ $priceList->header_pricing_summary ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $priceList->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $priceList->status }}
                                        </span>
                                    </td>
                                    <td>{{ (int) $priceList->active_lines_count }}</td>
                                    <td>{{ optional($priceList->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditPriceLists)
                                            <a href="{{ route('admin.price-lists.edit', ['price_list' => $priceList->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeletePriceLists)
                                            <form action="{{ route('admin.price-lists.destroy', ['price_list' => $priceList->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this price list?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditPriceLists && ! $canDeletePriceLists)
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
        $('#datatable-price-lists').DataTable({
            responsive: true,
            order: [[1, 'desc'], [0, 'asc']],
            language: {
                emptyTable: 'No price lists found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
