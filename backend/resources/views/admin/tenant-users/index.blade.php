@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'Users')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreateTenantUsers = admin_has_permission('tenant_users.create'))
@php($canEditTenantUsers = admin_has_permission('tenant_users.edit'))
@php($canDeleteTenantUsers = admin_has_permission('tenant_users.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Manage users stored inside the currently selected tenant database.
                        </p>
                    </div>
                    @if ($canCreateTenantUsers)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.tenant-users.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create User
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
                    <i class="mdi mdi-database me-2"></i>Showing users for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-tenant-users" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Roles</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="text-muted font-size-12">
                                            {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'No profile details' }}
                                        </div>
                                    </td>
                                    <td>{{ $user->username }}</td>
                                    <td>
                                        @forelse ($user->roles as $role)
                                            <span class="badge bg-light text-dark border me-1">{{ $role->label }}</span>
                                        @empty
                                            <span class="text-muted">No roles</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <div>{{ $user->email ?: 'No email' }}</div>
                                        <div class="text-muted font-size-12">{{ $user->phone ?: 'No phone' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $user->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $user->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($user->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditTenantUsers)
                                            <a href="{{ route('admin.tenant-users.edit', ['tenant_user' => $user->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteTenantUsers)
                                            <form action="{{ route('admin.tenant-users.destroy', ['tenant_user' => $user->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditTenantUsers && ! $canDeleteTenantUsers)
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
        $('#datatable-tenant-users').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No users found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
