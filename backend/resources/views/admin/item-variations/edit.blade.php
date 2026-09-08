@extends('layouts.app')

@section('title', 'Edit Variation')
@section('page_title', 'Edit Variation')

@section('content')
<form method="POST" action="{{ route('admin.item-variations.update', $variation->id) }}">
    @csrf
    @method('PUT')
    @include('admin.item-variations._form')
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.item-variations.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Variation</button>
    </div>
</form>
@endsection
