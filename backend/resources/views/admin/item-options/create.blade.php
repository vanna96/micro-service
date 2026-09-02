@extends('layouts.app')

@section('title', 'Create Option Master')
@section('page_title', 'Create Option Master')

@section('content')
<form method="POST" action="{{ route('admin.item-options.store') }}">
    @csrf
    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-link-variant me-2"></i>Create an option such as <strong>Red</strong> for Color or <strong>Big</strong> for Size.
    </div>
    @include('admin.item-options._form')
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.item-options.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Option</button>
    </div>
</form>
@endsection
