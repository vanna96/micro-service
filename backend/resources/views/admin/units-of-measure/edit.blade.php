@extends('layouts.app')

@section('title', 'Edit Unit of Measure')
@section('page_title', 'Edit Unit of Measure')

@push('styles')
<style>
    .admin-form-page { padding-bottom: 110px; }
    .admin-fixed-action-bar {
        position: fixed; right: 0; bottom: 0; left: 0; z-index: 1055;
        background: #ffffff; border-top: 1px solid #e9e9ef;
        box-shadow: 0 -4px 18px rgba(39, 48, 78, 0.08);
    }
</style>
@endpush

@section('content')
<form method="POST" action="{{ route('admin.units-of-measure.update', ['units_of_measure' => $unit->id]) }}" class="admin-form-page">
    @csrf
    @method('PUT')

    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-database me-2"></i>Editing unit of measure in tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
    </div>

    @include('admin.units-of-measure._form')

    <div class="admin-fixed-action-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('admin.units-of-measure.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Unit</button>
            </div>
        </div>
    </div>
</form>
@endsection
