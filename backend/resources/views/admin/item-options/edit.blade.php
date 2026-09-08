@extends('layouts.app')

@section('title', 'Edit Option')
@section('page_title', 'Edit Option')

@section('content')
<form method="POST" action="{{ route('admin.item-options.update', $option->id) }}">
    @csrf
    @method('PUT')
    @include('admin.item-options._form')
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.item-options.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Option</button>
    </div>
</form>
@endsection
