@extends('layouts.app')

@section('title', 'Edit Role: ' . $role->label)
@section('page_title', 'Edit Role')

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
            <div class="d-flex align-items-center gap-2">
                <h4 class="card-title mb-0 fw-bold text-dark fs-20">Edit Role: {{ $role->label }}</h4>
                <code class="text-muted font-size-11 bg-light px-2 py-0.5 rounded border">{{ $role->name }}</code>
            </div>
            <span class="text-muted font-size-12">Tenant: {{ admin_tenant_display_name($selectedTenant) }}</span>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.roles.update', ['role' => $role->id]) }}" class="admin-form-page">
    @csrf
    @method('PUT')

    @include('admin.roles._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-end gap-2 py-3">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light px-3">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-medium">
                    Update Role
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
