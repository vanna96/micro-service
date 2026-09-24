@extends('layouts.app')

@section('title', 'UoM Groups')
@section('page_title', 'UoM Groups')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreateUom = admin_has_permission('uom_groups.create'))
@php($canEditUom = admin_has_permission('uom_groups.edit'))
@php($canDeleteUom = admin_has_permission('uom_groups.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">UoM Groups</h4>
                        <p class="card-title-desc mb-0">
                            Group related units together like SAP UoM groups, with one base unit and conversion units.
                        </p>
                    </div>
                    @if ($canCreateUom)
                        <div class="mt-3 mt-sm-0 d-flex gap-2">
                            <a href="{{ route('admin.units-of-measure.index') }}" class="btn btn-light waves-effect">Units</a>
                            <a href="{{ route('admin.uom-groups.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Group
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
                    <i class="mdi mdi-database me-2"></i>Showing UoM groups for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-uom-groups" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Base Unit</th>
                                <th>Units</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $group)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $group->name }}</div>
                                        <div class="text-muted font-size-12">
                                            {{ $group->code }}{{ $group->foreign_name ? ' / ' . $group->foreign_name : '' }}
                                        </div>
                                    </td>
                                    <td>
                                        @if ($group->baseUnit)
                                            <span class="badge bg-soft-primary text-primary">{{ $group->baseUnit->code }}</span>
                                            <span class="text-muted">{{ $group->baseUnit->name }}</span>
                                        @else
                                            <span class="text-muted">Not set</span>
                                        @endif
                                    </td>
                                    <td>{{ (int) $group->units_count }}</td>
                                    <td>
                                        <span class="badge {{ $group->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $group->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($group->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditUom)
                                            <a href="{{ route('admin.uom-groups.edit', ['uom_group' => $group->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteUom)
                                            <form action="{{ route('admin.uom-groups.destroy', ['uom_group' => $group->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this UoM group and its units?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditUom && ! $canDeleteUom)
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
        $('#datatable-uom-groups').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No UoM groups found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
