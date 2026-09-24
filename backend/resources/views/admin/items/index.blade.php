@extends('layouts.app')

@section('title', 'Items')
@section('page_title', 'Items')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .item-index-thumb {
        width: 48px;
        height: 60px;
        border-radius: 14px;
        object-fit: cover;
        border: 1px solid #e9edf4;
    }

    .item-index-placeholder {
        width: 48px;
        height: 60px;
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
@php($canCreateItems = admin_has_permission('items.create'))
@php($canEditItems = admin_has_permission('items.edit'))
@php($canDeleteItems = admin_has_permission('items.delete'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <div class="me-md-3">
                        <h4 class="card-title mb-1">Default Datatable</h4>
                        <p class="card-title-desc mb-0">
                            Manage item catalog data for the currently selected tenant, including branch assignment, stock, rating, and promotional pricing.
                        </p>
                    </div>
                    @if ($canCreateItems)
                        <div class="d-flex align-items-center flex-nowrap gap-2 flex-shrink-0">
                            <a href="{{ route('admin.items.import-template') }}" class="btn btn-outline-secondary waves-effect text-nowrap">
                                <i class="uil uil-file-download me-1"></i>Excel Template
                            </a>
                            <button type="button" class="btn btn-outline-primary waves-effect text-nowrap" data-bs-toggle="modal" data-bs-target="#item-import-modal">
                                <i class="uil uil-import me-1"></i>Import Excel
                            </button>
                            <a href="{{ route('admin.items.create') }}" class="btn btn-primary waves-effect waves-light text-nowrap">
                                <i class="uil uil-plus me-1"></i>Create Item
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

                @if ($errors->has('import_file'))
                    <div class="alert alert-danger alert-border-left" role="alert">
                        <div class="fw-semibold mb-1">The Excel file was not imported.</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->get('import_file') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-database me-2"></i>Showing items for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-items" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Branch</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Rating</th>
                                <th>Status</th>
                                <th>Flags</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if ($item->image_url)
                                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="item-index-thumb">
                                            @else
                                                <div class="item-index-placeholder">
                                                    {{ strtoupper(substr($item->name ?: 'I', 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $item->name }}</div>
                                                <div class="text-muted font-size-12">
                                                    {{ $item->sku }}{{ $item->foreign_name ? ' / ' . $item->foreign_name : '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $item->category?->name ?: 'Unassigned' }}</td>
                                    <td>{{ $item->branch?->name ?: $item->branch_name ?: '-' }}</td>
                                    <td>
                                        @if ($item->item_type === 'variation')
                                            <span class="badge bg-info-subtle text-info">Variation</span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary">UOM</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ format_currency_amount($item->price, $item->currency) }}</div>
                                        @if (! $item->currency)
                                            <div class="text-muted font-size-12">Currency not assigned</div>
                                        @endif
                                    </td>
                                    <td>{{ $item->stock }}</td>
                                    <td>{{ $item->rating !== null ? number_format((float) $item->rating, 1) : '-' }} <span class="text-muted font-size-12">({{ $item->review_count }})</span></td>
                                    <td>
                                        <span class="badge {{ $item->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $item->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($item->purchase ?? true)
                                            <span class="badge bg-soft-info text-info border me-1" title="Available for Purchase">Purchase</span>
                                        @endif
                                        @if ($item->sale ?? true)
                                            <span class="badge bg-soft-success text-success border me-1" title="Available for Sale">Sale</span>
                                        @endif
                                        @foreach ([
                                            'Premium' => $item->is_premium,
                                            'Featured' => $item->is_featured,
                                            'New' => $item->is_new_arrival,
                                            'Try-On' => $item->is_try_on_enabled,
                                        ] as $label => $enabled)
                                            @if ($enabled)
                                                <span class="badge bg-light text-dark border me-1">{{ $label }}</span>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>{{ optional($item->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditItems)
                                            <a href="{{ route('admin.items.edit', ['item' => $item->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteItems)
                                            <form action="{{ route('admin.items.destroy', ['item' => $item->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditItems && ! $canDeleteItems)
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

@if ($canCreateItems)
    <div class="modal fade" id="item-import-modal" tabindex="-1" aria-labelledby="item-import-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.items.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="item-import-modal-label">Import Items from Excel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Download the template, complete the Items sheet, then upload it here. Existing SKUs are never overwritten.
                        </p>
                        <div class="alert alert-info py-2 font-size-13">
                            Variation items must be imported as Inactive, configured from Edit Item, and then activated.
                        </div>
                        <label for="item-import-file" class="form-label">Excel file</label>
                        <input id="item-import-file" name="import_file" type="file" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">Excel .xlsx or .xls, up to 5 MB and 500 item rows.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="uil uil-import me-1"></i>Import Items
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        $('#datatable-items').DataTable({
            responsive: true,
            order: [[9, 'desc']],
            language: {
                emptyTable: 'No items found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
