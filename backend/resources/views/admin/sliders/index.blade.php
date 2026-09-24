@extends('layouts.app')

@section('title', 'Sliders')
@section('page_title', 'Sliders')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreateSliders = admin_has_permission('sliders.create'))
@php($canEditSliders = admin_has_permission('sliders.edit'))
@php($canDeleteSliders = admin_has_permission('sliders.delete'))
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
                    @if ($canCreateSliders)
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
                                        @if ($slider->media_type === 'video' || (!empty($slider->media_url) && str_ends_with(strtolower($slider->media_url), '.mp4')))
                                            <div class="rounded border d-flex flex-column align-items-center justify-content-center text-white p-1" style="width: 104px; height: 58px; background: #0f172a;">
                                                <i class="uil uil-video fs-18 text-warning mb-0.5"></i>
                                                <span class="fs-10 fw-bold text-uppercase">Video Reel</span>
                                            </div>
                                        @elseif ($slider->image_url)
                                            <img src="{{ $slider->image_url }}" alt="{{ $slider->title ?: $slider->placement }}" class="rounded border" style="width: 104px; height: 58px; object-fit: cover;">
                                        @elseif ($slider->gradient)
                                            <div class="rounded border d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 104px; height: 58px; background: {{ $slider->gradient }};">
                                                <i class="{{ $slider->icon ?: 'ri-gift-line' }} fs-18"></i>
                                            </div>
                                        @else
                                            <span class="text-muted fs-12">No media</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                            @if ($slider->badge)
                                                <span class="badge shadow-sm rounded-pill px-2 py-0.5 fs-10 fw-bold" style="background-color: {{ $slider->badge_bg ?: '#ffffff' }}; color: {{ $slider->badge_color ?: '#0f172a' }}; border: 1px solid rgba(0,0,0,0.1);">
                                                    @if ($slider->icon)<i class="{{ $slider->icon }} me-1"></i>@endif{{ $slider->badge }}
                                                </span>
                                            @endif
                                            @if ($slider->discount)
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 fs-10 fw-bold">
                                                    {{ $slider->discount }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="fw-semibold fs-14">{{ $slider->title ?: '-' }}</div>
                                        @if ($slider->subtitle)
                                            <div class="text-muted fs-12 text-truncate" style="max-width: 320px;">{{ $slider->subtitle }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($slider->placement === 'second_screen' || strtolower($slider->placement) === 'secondscreen')
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fs-11">
                                                📺 2nd Screen (POS)
                                            </span>
                                        @elseif (strtolower($slider->placement) === 'mobile')
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 fs-11">
                                                📱 Mobile App
                                            </span>
                                        @elseif (strtolower($slider->placement) === 'all')
                                            <span class="badge bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25 px-2 py-1 fs-11">
                                                🌐 All Channels
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 fs-11">
                                                💻 Website
                                            </span>
                                        @endif
                                    </td>
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
                                        @if ($canEditSliders)
                                            <a href="{{ route('admin.sliders.edit', ['slider' => $slider->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteSliders)
                                            <form action="{{ route('admin.sliders.destroy', ['slider' => $slider->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this slider?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditSliders && ! $canDeleteSliders)
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
