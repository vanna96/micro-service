@extends('layouts.app')

@section('title', 'Create Item')
@section('page_title', 'Create Item')

@push('styles')
<style>
    .admin-form-page {
        padding-bottom: 110px;
    }

    .admin-fixed-action-bar {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1055;
        background: #ffffff;
        border-top: 1px solid #e9e9ef;
        box-shadow: 0 -4px 18px rgba(39, 48, 78, 0.08);
    }
</style>
@endpush

@section('content')
<form id="item-form" method="POST" action="{{ route('admin.items.store') }}" class="admin-form-page" enctype="multipart/form-data">
    @csrf

    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-database me-2"></i>Creating item for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
    </div>

    @include('admin.items._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('admin.items.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Item</button>
            </div>
        </div>
    </div>
</form>
@endsection
