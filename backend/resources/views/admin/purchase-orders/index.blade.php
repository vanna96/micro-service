@extends('layouts.app')

@section('title', 'Purchase Orders')
@section('page_title', 'Purchase Orders')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .badge-draft { background-color: rgba(116, 120, 141, 0.18); color: #74788d; }
    .badge-ordered { background-color: rgba(85, 110, 230, 0.18); color: #556ee6; }
    .badge-received { background-color: rgba(52, 195, 143, 0.18); color: #34c38f; }
    .badge-cancelled { background-color: rgba(244, 106, 106, 0.18); color: #f46a6a; }
</style>
@endpush

@section('content')
@php
    $canCreate = admin_has_permission('purchase_orders.create');
    $canEdit = admin_has_permission('purchase_orders.edit');
    $canReceive = admin_has_permission('purchase_orders.receive');
    $canDelete = admin_has_permission('purchase_orders.delete');
@endphp

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Purchase Orders</h4>
                        <p class="card-title-desc mb-0">
                            Create supplier orders and stock in inventory for tracked items upon receipt.
                        </p>
                    </div>
                    @if ($canCreate)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>New Purchase Order
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
                @if (session('error'))
                    <div class="alert alert-danger alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-block-helper me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-outline me-2"></i>{{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Status Filter Tabs --}}
                <ul class="nav nav-tabs nav-tabs-custom mb-4" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ empty($selectedStatus) ? 'active' : '' }}"
                           href="{{ route('admin.purchase-orders.index', array_filter(['search' => $search, 'vendor_id' => $selectedVendorId, 'branch_id' => $selectedBranchId])) }}">
                            <i class="uil uil-layers me-1"></i>All Orders
                            <span class="badge bg-soft-secondary text-secondary rounded-pill ms-1">{{ $statusCounts['all'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $selectedStatus === 'Draft' ? 'active' : '' }}"
                           href="{{ route('admin.purchase-orders.index', array_filter(['status' => 'Draft', 'search' => $search, 'vendor_id' => $selectedVendorId, 'branch_id' => $selectedBranchId])) }}">
                            <i class="uil uil-edit-alt me-1 text-muted"></i>Draft
                            <span class="badge bg-soft-secondary text-secondary rounded-pill ms-1">{{ $statusCounts['draft'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $selectedStatus === 'Ordered' ? 'active' : '' }}"
                           href="{{ route('admin.purchase-orders.index', array_filter(['status' => 'Ordered', 'search' => $search, 'vendor_id' => $selectedVendorId, 'branch_id' => $selectedBranchId])) }}">
                            <i class="uil uil-truck me-1 text-primary"></i>Ordered
                            <span class="badge bg-soft-primary text-primary rounded-pill ms-1">{{ $statusCounts['ordered'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $selectedStatus === 'Received' ? 'active' : '' }}"
                           href="{{ route('admin.purchase-orders.index', array_filter(['status' => 'Received', 'search' => $search, 'vendor_id' => $selectedVendorId, 'branch_id' => $selectedBranchId])) }}">
                            <i class="uil uil-check-circle me-1 text-success"></i>Received (Stocked In)
                            <span class="badge bg-soft-success text-success rounded-pill ms-1">{{ $statusCounts['received'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $selectedStatus === 'Cancelled' ? 'active' : '' }}"
                           href="{{ route('admin.purchase-orders.index', array_filter(['status' => 'Cancelled', 'search' => $search, 'vendor_id' => $selectedVendorId, 'branch_id' => $selectedBranchId])) }}">
                            <i class="uil uil-times-circle me-1 text-danger"></i>Cancelled
                            <span class="badge bg-soft-danger text-danger rounded-pill ms-1">{{ $statusCounts['cancelled'] ?? 0 }}</span>
                        </a>
                    </li>
                </ul>

                {{-- Table Filters --}}
                <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="row g-3 align-items-center mb-4">
                    @if ($selectedStatus)
                        <input type="hidden" name="status" value="{{ $selectedStatus }}" />
                    @endif
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="uil uil-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search PO #, vendor, notes..." value="{{ $search }}" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="vendor_id" class="form-select">
                            <option value="">All Vendors</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ (string) $selectedVendorId === (string) $vendor->id ? 'selected' : '' }}>
                                    {{ $vendor->name }} ({{ $vendor->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="branch_id" class="form-select">
                            <option value="">All Branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) $selectedBranchId === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="uil uil-filter me-1"></i>Filter</button>
                        @if ($search || $selectedStatus || $selectedVendorId || $selectedBranchId)
                            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="uil uil-redo"></i>
                            </a>
                        @endif
                    </div>
                </form>

                {{-- Purchase Orders Table --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0" id="purchaseOrdersTable">
                        <thead class="table-light">
                            <tr>
                                <th>PO Number</th>
                                <th>Vendor</th>
                                <th>Branch</th>
                                <th>Order Date</th>
                                <th>Expected Date</th>
                                <th>Lines</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.purchase-orders.show', ['purchase_order' => $order->id]) }}" class="fw-semibold text-primary">
                                            {{ $order->po_number }}
                                        </a>
                                        @if ($order->stock_received_at)
                                            <div class="text-muted font-size-11" title="Stock added to inventory">
                                                <i class="uil uil-arrow-down-right text-success"></i> In: {{ $order->stock_received_at->format('M d, H:i') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order->vendor)
                                            <div class="fw-medium text-dark">{{ $order->vendor->name }}</div>
                                            <div class="text-muted font-size-11">{{ $order->vendor->code }}</div>
                                        @else
                                            <span class="text-muted">Direct Supplier</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $order->branch?->name ?: '-' }}
                                    </td>
                                    <td>
                                        {{ $order->order_date ? $order->order_date->format('M d, Y') : '-' }}
                                    </td>
                                    <td>
                                        {{ $order->expected_date ? $order->expected_date->format('M d, Y') : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark font-size-12">
                                            {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('item', $order->items->count()) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">
                                            {{ format_currency_amount($order->total_amount, $order->currency) }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($order->status) {
                                                'Draft' => 'badge-draft',
                                                'Ordered' => 'badge-ordered',
                                                'Received' => 'badge-received',
                                                'Cancelled' => 'badge-cancelled',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} font-size-12 px-2 py-1">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <a href="{{ route('admin.purchase-orders.show', ['purchase_order' => $order->id]) }}"
                                               class="btn btn-sm btn-outline-secondary" title="View details">
                                                <i class="uil uil-eye"></i>
                                            </a>

                                            @if ($canReceive && $order->canReceive())
                                                <form method="POST" action="{{ route('admin.purchase-orders.receive', ['purchase_order' => $order->id]) }}"
                                                      onsubmit="return confirm('Stock in inventory for Purchase Order #{{ $order->po_number }}? Tracked items will have their stock incremented.');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success" title="Receive & Stock In">
                                                        <i class="uil uil-arrow-down-right me-1"></i>Receive
                                                    </button>
                                                </form>
                                            @elseif ($canEdit && $order->canMarkOrdered())
                                                <form method="POST" action="{{ route('admin.purchase-orders.mark-ordered', ['purchase_order' => $order->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark as Ordered">
                                                        <i class="uil uil-truck me-1"></i>Order
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- More Actions Dropdown --}}
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light dropdown-toggle px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More options">
                                                    <i class="uil uil-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.purchase-orders.show', ['purchase_order' => $order->id]) }}">
                                                            <i class="uil uil-eye me-2 text-muted"></i>View Details
                                                        </a>
                                                    </li>
                                                    @if ($canEdit && $order->canEdit())
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('admin.purchase-orders.edit', ['purchase_order' => $order->id]) }}">
                                                                <i class="uil uil-edit me-2 text-primary"></i>Edit Order
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if ($canEdit && $order->canMarkOrdered())
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.purchase-orders.mark-ordered', ['purchase_order' => $order->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="uil uil-truck me-2 text-primary"></i>Mark as Ordered
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    @if ($canReceive && $order->canReceive())
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.purchase-orders.receive', ['purchase_order' => $order->id]) }}"
                                                                  onsubmit="return confirm('Stock in inventory for Purchase Order #{{ $order->po_number }}?');">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-success">
                                                                    <i class="uil uil-arrow-down-right me-2"></i>Receive (Stock In)
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    @if ($canEdit && $order->canCancel())
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.purchase-orders.cancel', ['purchase_order' => $order->id]) }}"
                                                                  onsubmit="return confirm('Cancel purchase order #{{ $order->po_number }}?');">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="uil uil-times-circle me-2"></i>Cancel Order
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    @if ($canEdit && $order->canMarkDraft())
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.purchase-orders.mark-draft', ['purchase_order' => $order->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-secondary">
                                                                    <i class="uil uil-redo me-2"></i>{{ $order->status === 'Cancelled' ? 'Reopen as Draft' : 'Revert to Draft' }}
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    @if ($canDelete && ! $order->isReceived())
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.purchase-orders.destroy', ['purchase_order' => $order->id]) }}"
                                                                  onsubmit="return confirm('Are you sure you want to delete purchase order #{{ $order->po_number }}?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="uil uil-trash me-2"></i>Delete Order
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3 text-muted">
                                            <i class="uil uil-clipboard-blank font-size-48"></i>
                                        </div>
                                        <h5 class="text-muted">No purchase orders found</h5>
                                        <p class="text-muted mb-3">Create supplier orders to purchase materials and replenish inventory.</p>
                                        @if ($canCreate)
                                            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
                                                <i class="uil uil-plus me-1"></i>Create First Purchase Order
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
