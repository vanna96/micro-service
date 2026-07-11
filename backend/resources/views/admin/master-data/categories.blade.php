@extends('layouts.app')

@section('title', 'Categories')
@section('page_title', 'Categories')

@section('content')
<div class="row">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div>
                        <span class="badge bg-soft-primary text-primary mb-3">Master Data</span>
                        <h4 class="mb-2">Category module is ready for the next step</h4>
                        <p class="text-muted mb-0">
                            The navigation is wired and this screen is now part of the admin backend. We can turn this into full category CRUD next.
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
                <h5 class="mb-3">Planned actions</h5>
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">Category listing</div>
                        <div class="text-muted small">Show all categories with parent, status, and image.</div>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">Create and update</div>
                        <div class="text-muted small">Add forms for names, parent category, status, and thumbnail upload.</div>
                    </div>
                    <div class="list-group-item px-0 pb-0">
                        <div class="fw-semibold">Tenant-aware data</div>
                        <div class="text-muted small">Connect the admin page to the right category storage flow.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
