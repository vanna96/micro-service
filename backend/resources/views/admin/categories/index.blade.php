@extends('layouts.app')

@section('title', 'Categories')
@section('page_title', 'Categories')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .category-index-thumb {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        object-fit: cover;
        border: 1px solid #e9edf4;
    }

    .category-index-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef4ff;
        color: #5b73e8;
        font-weight: 700;
        border: 1px solid #d9e5ff;
    }
</style>
@endpush

@section('content')
@php($canCreateCategories = admin_has_permission('categories.create'))
@php($canEditCategories = admin_has_permission('categories.edit'))
@php($canDeleteCategories = admin_has_permission('categories.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Manage category structure, parent relationships, and publishing status for the currently selected tenant.
                        </p>
                    </div>
                    @if ($canCreateCategories)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Category
                            </a>
                        </div>
                    @endif
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-database me-2"></i>Showing categories for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-categories" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Parent</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if ($category->image_url)
                                                <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="category-index-thumb">
                                            @else
                                                <div class="category-index-placeholder">
                                                    {{ strtoupper(substr($category->name ?: 'C', 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $category->name }}</div>
                                                <div class="text-muted font-size-12">
                                                    {{ $category->foreign_name ?: 'No foreign name' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($category->parent)
                                            <div class="fw-semibold">{{ $category->parent->name }}</div>
                                            <div class="text-muted font-size-12">{{ $category->parent->foreign_name ?: 'Parent category' }}</div>
                                        @else
                                            <span class="text-muted">Top level</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $category->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $category->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($category->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditCategories)
                                            <a href="{{ route('admin.categories.edit', ['category' => $category->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteCategories)
                                            <form action="{{ route('admin.categories.destroy', ['category' => $category->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditCategories && ! $canDeleteCategories)
                                            <span class="text-muted">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        $('#datatable-categories').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No categories found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
