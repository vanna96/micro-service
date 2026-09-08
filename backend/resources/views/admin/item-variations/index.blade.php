@extends('layouts.app')

@section('title', 'Variations')
@section('page_title', 'Variations')

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
                        <h4 class="card-title mb-1">Variations</h4>
                        <p class="card-title-desc mb-0">
                            Maintain reusable item choices such as Size, Color, and Storage.
                        </p>
                    </div>
                    @if ($canManage)
                        <div class="mt-3 mt-sm-0 d-flex gap-2">
                            <a href="{{ route('admin.item-options.index') }}" class="btn btn-light waves-effect">Options</a>
                            <a href="{{ route('admin.item-variations.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Variation
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
                    <i class="mdi mdi-database me-2"></i>Showing variations for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-item-variations" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Variation</th>
                                <th>Behavior</th>
                                <th>Options</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variations as $variation)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $variation->name }}</div>
                                        <div class="text-muted font-size-12">
                                            {{ $variation->foreign_name ?: 'Reusable variation' }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $variation->type === 'variant' ? 'Variation' : 'Modifier' }}
                                        <span class="text-muted">/ {{ ucfirst($variation->selection_type) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary">{{ (int) $variation->options_count }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $variation->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $variation->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($variation->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canManage)
                                            <a href="{{ route('admin.item-options.create', ['variation' => $variation->id]) }}" class="btn btn-sm btn-primary me-2">Add Option</a>
                                            <a href="{{ route('admin.item-variations.edit', $variation->id) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.item-variations.destroy', $variation->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this variation and its options?')">Delete</button>
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
        $('#datatable-item-variations').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [5] }
            ],
            language: {
                emptyTable: 'No variations found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
