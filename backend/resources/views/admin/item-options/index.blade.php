@extends('layouts.app')

@section('title', 'Option Master')
@section('page_title', 'Option Master')

@section('content')
@php($canManage = admin_has_permission('items.manage'))
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h4 class="card-title mb-1">Option Master</h4>
                <p class="text-muted mb-0">Define reusable choices and link each one to a variation.</p>
            </div>
            @if ($canManage)
                <a href="{{ route('admin.item-options.create') }}" class="btn btn-primary"><i class="uil uil-plus me-1"></i>Create Option</a>
            @endif
        </div>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-5"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search options"></div>
            <div class="col-auto"><button class="btn btn-light" type="submit"><i class="uil uil-search"></i> Search</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead><tr><th>Option</th><th>Variation</th><th>SKU / Color</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($options as $option)
                        <tr>
                            <td><div class="fw-semibold">{{ $option->name }}</div><small class="text-muted">{{ $option->foreign_name ?: 'Reusable option' }}</small></td>
                            <td>{{ $option->variation?->name ?: '-' }}</td>
                            <td>
                                @if ($option->color_hex)<span class="d-inline-block rounded me-1" style="width:16px;height:16px;background:{{ $option->color_hex }};border:1px solid #ccd2dc"></span>@endif
                                {{ $option->sku_suffix ?: '-' }}
                            </td>
                            <td>{{ number_format((float) $option->price_adjustment, 2) }}</td>
                            <td><span class="badge {{ $option->status === 'Active' ? 'bg-success' : 'bg-danger' }}">{{ $option->status }}</span></td>
                            <td class="text-nowrap">
                                @if ($canManage)
                                    <a href="{{ route('admin.item-options.edit', $option->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('admin.item-options.destroy', $option->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this option?')">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No options found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
