@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'Users')

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
                            Manage backend users with datatable search, sort, and paging.
                        </p>
                    </div>
                    <div class="mt-3 mt-sm-0">
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary waves-effect waves-light">
                            <i class="uil uil-plus me-1"></i>Create User
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
                    <table id="datatable-users" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Tenants</th>
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
                                        <div>{{ $user->email ?: 'No email' }}</div>
                                        <div class="text-muted font-size-12">{{ $user->phone ?: 'No phone' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $user->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $user->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @forelse ($user->tenants as $tenant)
                                            <span class="badge bg-soft-primary text-primary me-1 mb-1">{{ $tenant->id }}</span>
                                        @empty
                                            <span class="text-muted">Not assigned</span>
                                        @endforelse
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button>
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
        $('#datatable-users').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No users found.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
