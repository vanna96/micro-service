@extends('layouts.app')

@section('title', 'Units of Measure')
@section('page_title', 'Units of Measure')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canManageUom = admin_has_permission('units_of_measure.manage'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Units of Measure</h4>
                        <p class="card-title-desc mb-0">
                            Maintain UoM codes and names. Conversion ratios are maintained in UOM Group setup.
                        </p>
                    </div>
                    @if ($canManageUom)
                        <div class="mt-3 mt-sm-0 d-flex gap-2">
                            <a href="{{ route('admin.uom-groups.index') }}" class="btn btn-light waves-effect">UoM Groups</a>
                            <a href="{{ route('admin.units-of-measure.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Unit
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
                    <i class="mdi mdi-database me-2"></i>Showing units of measure for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-units-of-measure" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Symbol</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($units as $unit)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $unit->name }}
                                            @if ($unit->is_base_unit)
                                                <span class="badge bg-soft-primary text-primary ms-1">Base</span>
                                            @endif
                                        </div>
                                        <div class="text-muted font-size-12">
                                            {{ $unit->code }}{{ $unit->symbol ? ' / ' . $unit->symbol : '' }}{{ $unit->foreign_name ? ' / ' . $unit->foreign_name : '' }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $unit->symbol ?: '-' }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $unit->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $unit->status }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($canManageUom)
                                            <a href="{{ route('admin.units-of-measure.edit', ['units_of_measure' => $unit->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.units-of-measure.destroy', ['units_of_measure' => $unit->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this unit of measure?')">Delete</button>
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
        $('#datatable-units-of-measure').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No units of measure found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
