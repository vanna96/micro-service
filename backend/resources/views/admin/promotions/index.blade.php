@extends('layouts.app')

@section('title', 'Promotions')
@section('page_title', 'Promotions')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canCreatePromotions = admin_has_permission('promotions.create'))
@php($canEditPromotions = admin_has_permission('promotions.edit'))
@php($canDeletePromotions = admin_has_permission('promotions.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Promotion Campaigns</h4>
                        <p class="card-title-desc mb-0">
                            Manage automatic promotion campaigns independently from price lists.
                        </p>
                    </div>
                    @if ($canCreatePromotions)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Promotion
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
                    <i class="mdi mdi-ticket-percent-outline me-2"></i>Showing promotions for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-promotions" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Promotion</th>
                                <th>Type</th>
                                <th>Rule</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($promotions as $promotion)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if ($promotion->image_url)
                                                <img src="{{ $promotion->image_url }}" alt="{{ $promotion->name }}" class="rounded me-3" style="width: 52px; height: 52px; object-fit: cover;">
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $promotion->name }}</div>
                                                <div class="text-muted font-size-12">{{ $promotion->code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $promotion->type_label }}</td>
                                    <td>{{ $promotion->rule_summary }}</td>
                                    <td>{{ $promotion->schedule_summary }}</td>
                                    <td>
                                        <span class="badge {{ $promotion->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $promotion->status }}
                                        </span>
                                    </td>
                                    <td>{{ (int) $promotion->active_lines_count }}</td>
                                    <td>{{ optional($promotion->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditPromotions)
                                            <a href="{{ route('admin.promotions.edit', ['promotion' => $promotion->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeletePromotions)
                                            <form action="{{ route('admin.promotions.destroy', ['promotion' => $promotion->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this promotion?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditPromotions && ! $canDeletePromotions)
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
        $('#datatable-promotions').DataTable({
            responsive: true,
            order: [[1, 'desc'], [0, 'asc']],
            language: {
                emptyTable: 'No promotions found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
