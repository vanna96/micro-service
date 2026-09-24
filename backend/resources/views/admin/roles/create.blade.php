@extends('layouts.app')

@section('title', 'Create Role')
@section('page_title', 'Create Role')

@push('styles')
<style>
    .admin-form-page {
        padding-bottom: 90px;
    }

    .admin-fixed-action-bar {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1055;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.04);
    }
</style>
@endpush

@section('content')
<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-light border rounded-circle p-0 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Back">
            <i class="uil uil-arrow-left font-size-16"></i>
        </a>
        <div>
            <h4 class="card-title mb-0 fw-bold text-dark fs-20">Create New Role</h4>
            <span class="text-muted font-size-12">Tenant: {{ admin_tenant_display_name($selectedTenant) }}</span>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.roles.store') }}" class="admin-form-page">
    @csrf

    @include('admin.roles._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-end gap-2 py-3">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light px-3">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-medium">
                    Save Role
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
