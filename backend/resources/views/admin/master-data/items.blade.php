@extends('layouts.app')

@section('title', 'Items')
@section('page_title', 'Items')

@section('content')
<div class="row">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div>
                        <span class="badge bg-soft-primary text-primary mb-3">Master Data</span>
                        <h4 class="mb-2">Item module placeholder is now connected</h4>
                        <p class="text-muted mb-0">
                            The menu entry is live and ready for the item module once the model, migration, and CRUD flow are defined.
                        </p>
                    </div>
                    <span class="badge bg-soft-warning text-warning">Coming Soon</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="mb-3">Next build</h5>
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">Item schema</div>
                        <div class="text-muted small">Create the items table and model with pricing and stock fields.</div>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">Category relation</div>
                        <div class="text-muted small">Link items to categories for easier filtering and reporting.</div>
                    </div>
                    <div class="list-group-item px-0 pb-0">
                        <div class="fw-semibold">Admin CRUD</div>
                        <div class="text-muted small">Add listing, forms, search, and status actions in this backend.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
