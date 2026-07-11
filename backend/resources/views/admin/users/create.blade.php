@extends('layouts.app')

@section('title', 'Create User')
@section('page_title', 'Create User')

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
<form method="POST" action="{{ route('admin.users.store') }}" class="admin-form-page" enctype="multipart/form-data">
    @csrf

    @include('admin.users._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </div>
    </div>
</form>
@endsection
