@extends('layouts.app')

@section('title', 'Variation Master')
@section('page_title', 'Variation Master')

@section('content')
@php($canManage = admin_has_permission('items.manage'))
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h4 class="card-title mb-1">Variation Master</h4>
                <p class="text-muted mb-0">Define reusable groups such as Size, Color, or Storage.</p>
            </div>
            @if ($canManage)
                <a href="{{ route('admin.item-variations.create') }}" class="btn btn-primary"><i class="uil uil-plus me-1"></i>Create Variation</a>
            @endif
        </div>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-5"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search variations"></div>
            <div class="col-auto"><button class="btn btn-light" type="submit"><i class="uil uil-search"></i> Search</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead><tr><th>Variation</th><th>Behavior</th><th>Options</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($variations as $variation)
                        <tr>
                            <td><div class="fw-semibold">{{ $variation->name }}</div><small class="text-muted">{{ $variation->foreign_name ?: 'Reusable variation' }}</small></td>
                            <td>{{ $variation->type === 'variant' ? 'Variation' : 'Modifier' }} · {{ ucfirst($variation->selection_type) }}</td>
                            <td><span class="badge bg-soft-primary text-primary">{{ $variation->options_count }}</span></td>
                            <td><span class="badge {{ $variation->status === 'Active' ? 'bg-success' : 'bg-danger' }}">{{ $variation->status }}</span></td>
                            <td class="text-nowrap">
                                @if ($canManage)
                                    <a href="{{ route('admin.item-options.create', ['variation' => $variation->id]) }}" class="btn btn-sm btn-primary">Add Option</a>
                                    <a href="{{ route('admin.item-variations.edit', $variation->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('admin.item-variations.destroy', $variation->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this variation and its options?')">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No variations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
