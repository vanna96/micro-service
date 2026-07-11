@extends('layouts.app')

@section('title', 'Sliders')
@section('page_title', 'Sliders')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canManageSliders = admin_has_permission('sliders.manage'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Manage promotional sliders for website and mobile placements within the selected tenant.
                        </p>
                    </div>
                    @if ($canManageSliders)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.sliders.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Slider
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
                    <i class="mdi mdi-database me-2"></i>Showing sliders for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-sliders" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Placement</th>
                                <th>Target URL</th>
                                <th>Sort Order</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sliders as $slider)
                                <tr>
                                    <td>
                                        @if ($slider->image_url)
                                            <img src="{{ $slider->image_url }}" alt="{{ $slider->title ?: $slider->placement }}" class="rounded border" style="width: 96px; height: 54px; object-fit: cover;">
                                        @else
                                            <span class="text-muted">No image</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $slider->title ?: '-' }}</div>
                                        <div class="text-muted font-size-12">{{ $slider->subtitle ?: $slider->recommended_dimensions }}</div>
                                    </td>
                                    <td>{{ $slider->placement }}</td>
                                    <td>
                                        @if ($slider->target_url)
                                            <a href="{{ $slider->target_url }}" target="_blank" rel="noopener noreferrer">{{ $slider->target_url }}</a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ (int) $slider->sort_order }}</td>
                                    <td>
                                        <span class="badge {{ $slider->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $slider->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($slider->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canManageSliders)
                                            <a href="{{ route('admin.sliders.edit', ['slider' => $slider->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.sliders.destroy', ['slider' => $slider->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this slider?')">Delete</button>
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
        $('#datatable-sliders').DataTable({
            responsive: true,
            order: [[4, 'asc'], [6, 'desc']],
            language: {
                emptyTable: 'No sliders found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
