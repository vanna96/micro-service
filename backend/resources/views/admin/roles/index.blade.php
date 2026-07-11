@extends('layouts.app')

@section('title', 'Roles')
@section('page_title', 'Roles & Permissions')

@section('content')
@php($canManageRoles = admin_has_permission('roles.manage'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Tenant Roles</h4>
                        <p class="card-title-desc mb-0">Define what tenant users are allowed to see and manage.</p>
                    </div>
                    @if ($canManageRoles)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Role
                            </a>
                        </div>
                    @endif
                </div>

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-shield-account me-2"></i>Showing roles for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Permissions</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roles as $role)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $role->label }}</div>
                                        <div class="text-muted font-size-12">{{ $role->name }}</div>
                                    </td>
                                    <td>{{ $role->description ?: '-' }}</td>
                                    <td>{{ (int) $role->permissions_count }}</td>
                                    <td>{{ (int) $role->users_count }}</td>
                                    <td class="text-nowrap">
                                        @if ($canManageRoles)
                                            <a href="{{ route('admin.roles.edit', ['role' => $role->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.roles.destroy', ['role' => $role->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this role?')">Delete</button>
                                            </form>
                                        @else
                                            <span class="text-muted">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No roles found for this tenant.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
