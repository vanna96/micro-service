@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page_title', 'Roles & Permissions')

@section('content')
@php
    $canCreateRoles = admin_has_permission('roles.create');
    $canEditRoles = admin_has_permission('roles.edit');
    $canDeleteRoles = admin_has_permission('roles.delete');
    $totalRoles = $roles->count();
@endphp

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="card-title mb-1 fw-bold text-dark fs-20">Roles & Permissions</h4>
        <p class="text-muted mb-0 font-size-13">
            Define access privileges and manage what tenant users are allowed to see and do.
        </p>
    </div>
    @if ($canCreateRoles)
        <div>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="uil uil-plus me-1"></i>Create New Role
            </a>
        </div>
    @endif
</div>

@if (session('status'))
    <div class="alert alert-success alert-border-left alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Roles Table Card -->
<div class="card border shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="mdi mdi-shield-account text-primary font-size-18"></i>
            <span class="font-size-13 text-muted">
                Tenant: <strong class="text-dark">{{ admin_tenant_display_name($selectedTenant) }}</strong>
            </span>
        </div>
        <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 font-size-12 fw-medium">
            {{ $totalRoles }} {{ Str::plural('Role', $totalRoles) }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="min-width: 200px;">Role</th>
                        <th>Description</th>
                        <th style="width: 160px;">Permissions</th>
                        <th style="width: 120px;">Users</th>
                        <th class="text-end pe-4" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark font-size-14">{{ $role->label }}</div>
                                <code class="text-muted font-size-11">{{ $role->name }}</code>
                            </td>
                            <td>
                                <span class="text-muted font-size-13">{{ $role->description ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 font-size-12 fw-semibold">
                                    {{ (int) $role->permissions_count }} permissions
                                </span>
                            </td>
                            <td>
                                <span class="text-muted font-size-13">
                                    <i class="uil uil-user me-1"></i>{{ (int) $role->users_count }}
                                </span>
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                @if ($canEditRoles)
                                    <a href="{{ route('admin.roles.edit', ['role' => $role->id]) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Role">
                                        Edit
                                    </a>
                                @endif
                                @if ($canDeleteRoles)
                                    <form action="{{ route('admin.roles.destroy', ['role' => $role->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Role" onclick="return confirm('Are you sure you want to delete this role?')">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                                @if (! $canEditRoles && ! $canDeleteRoles)
                                    <span class="text-muted font-size-12">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="uil uil-shield-slash font-size-28 d-block mb-2 text-muted"></i>
                                <div class="fw-semibold font-size-14 text-dark mb-1">No Roles Configured</div>
                                <p class="font-size-12 text-muted mb-3">No access roles have been set up for this tenant yet.</p>
                                @if ($canCreateRoles)
                                    <a href="{{ route('admin.roles.create') }}" class="btn btn-sm btn-primary">
                                        <i class="uil uil-plus me-1"></i>Create Role
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
