@extends('layouts.app')

@section('title', 'Options')
@section('page_title', 'Options')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canManage = admin_has_permission('items.manage'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Options</h4>
                        <p class="card-title-desc mb-0">
                            Maintain reusable option values and assign each one to a variation.
                        </p>
                    </div>
                    @if ($canManage)
                        <div class="mt-3 mt-sm-0 d-flex gap-2">
                            <a href="{{ route('admin.item-variations.index') }}" class="btn btn-light waves-effect">Variations</a>
                            <a href="{{ route('admin.item-options.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Option
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
                    <i class="mdi mdi-database me-2"></i>Showing options for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-item-options" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Option</th>
                                <th>Variation</th>
                                <th>SKU / Color</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($options as $option)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $option->name }}</div>
                                        <div class="text-muted font-size-12">
                                            {{ $option->foreign_name ?: 'Reusable option' }}
                                        </div>
                                    </td>
                                    <td>{{ $option->variation?->name ?: '-' }}</td>
                                    <td>
                                        @if ($option->color_hex)
                                            <span class="d-inline-block rounded me-1 align-middle" style="width:16px;height:16px;background:{{ $option->color_hex }};border:1px solid #ccd2dc"></span>
                                        @endif
                                        {{ $option->sku_suffix ?: '-' }}
                                    </td>
                                    <td>{{ number_format((float) $option->price_adjustment, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $option->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $option->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($option->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canManage)
                                            <a href="{{ route('admin.item-options.edit', $option->id) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.item-options.destroy', $option->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this option?')">Delete</button>
                                            </form>
                                        @else
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
        $('#datatable-item-options').DataTable({
            responsive: true,
            order: [[1, 'asc'], [0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [6] }
            ],
            language: {
                emptyTable: 'No options found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
