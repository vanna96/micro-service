@extends('layouts.app')

@section('title', 'Tenants')
@section('page_title', 'Tenants')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Review tenant databases, domains, and status.
                        </p>
                    </div>
                    <div class="mt-3 mt-sm-0">
                        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary waves-effect waves-light">
                            <i class="uil uil-plus me-1"></i>Create Tenant
                        </a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table id="datatable-tenants" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Alias</th>
                                <th>Database</th>
                                <th>Connection</th>
                                <th>Status</th>
                                <th>Domain</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $tenant)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $tenant->alias ?: $tenant->id }}</div>
                                        <div class="text-muted fs-7">ID: {{ $tenant->id }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $tenant->db_name }}</div>
                                        <div class="text-muted font-size-12">{{ $tenant->db_host }}:{{ $tenant->db_port }}</div>
                                    </td>
                                    <td>{{ $tenant->db_connection }}</td>
                                    <td>
                                        <span class="badge {{ $tenant->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $tenant->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($tenant->domains->first())->domain ?: 'No domain' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        <form action="{{ route('admin.tenants.destroy', $tenant) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this tenant?')">Delete</button>
                                        </form>
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
        $('#datatable-tenants').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No tenants found.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
