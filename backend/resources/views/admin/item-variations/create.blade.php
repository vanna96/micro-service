@extends('layouts.app')

@section('title', 'Create Variation')
@section('page_title', 'Create Variation')

@section('content')
<form method="POST" action="{{ route('admin.item-variations.store') }}">
    @csrf
    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-database me-2"></i>Creating a variation for <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
    </div>
    @include('admin.item-variations._form')
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.item-variations.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Variation</button>
    </div>
</form>
@endsection
