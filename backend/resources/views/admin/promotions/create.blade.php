@extends('layouts.app')

@section('title', 'Create Promotion')
@section('page_title', 'Create Promotion')

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
    <form method="POST" action="{{ route('admin.promotions.store') }}" enctype="multipart/form-data" class="admin-form-page">
        @csrf

        <div class="alert alert-border-left alert-light mb-4" role="alert">
            <i class="mdi mdi-ticket-percent-outline me-2"></i>Creating promotion for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
        </div>

        <div class="alert alert-info alert-border-left mb-4" role="alert">
            <i class="mdi mdi-information-outline me-2"></i>Save the promotion first, then you will be taken to the item setup screen to choose the promotion items.
        </div>

        @include('admin.promotions._form')

        <div class="admin-fixed-action-bar">
            <div class="container-fluid">
                <div class="d-flex justify-content-end gap-2 py-3">
                    <a href="{{ route('admin.promotions.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Promotion</button>
                </div>
            </div>
        </div>
    </form>
@endsection
