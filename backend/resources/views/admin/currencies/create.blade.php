@extends('layouts.app')

@section('title', 'Create Currency')
@section('page_title', 'Create Currency')

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
<form method="POST" action="{{ route('admin.currencies.store') }}" class="admin-form-page">
    @csrf

    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-currency-usd me-2"></i>Creating currency for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
    </div>

    @include('admin.currencies._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('admin.currencies.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Currency</button>
            </div>
        </div>
    </div>
</form>
@endsection
