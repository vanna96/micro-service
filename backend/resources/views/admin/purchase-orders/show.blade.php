@extends('layouts.app')

@section('title', 'Purchase Order ' . $order->po_number)
@section('page_title', 'Purchase Order ' . $order->po_number)

@push('styles')
<style>
    .invoice-title { font-size: 18px; font-weight: 700; color: #2a3042; }
    .badge-draft { background-color: rgba(116, 120, 141, 0.18); color: #74788d; }
    .badge-ordered { background-color: rgba(85, 110, 230, 0.18); color: #556ee6; }
    .badge-received { background-color: rgba(52, 195, 143, 0.18); color: #34c38f; }
    .badge-cancelled { background-color: rgba(244, 106, 106, 0.18); color: #f46a6a; }
</style>
@endpush

@section('content')
@php
    $canEdit = admin_has_permission('purchase_orders.edit');
    $canReceive = admin_has_permission('purchase_orders.receive');
@endphp

<div class="row">
    <div class="col-12">
        @if (session('status'))
            <div class="alert alert-success alert-border-left alert-dismissible fade show mb-4" role="alert">
                <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-border-left alert-dismissible fade show mb-4" role="alert">
                <i class="mdi mdi-alert-outline me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-border-left alert-dismissible fade show mb-4" role="alert">
                <i class="mdi mdi-block-helper me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Top Action Toolbar --}}
        <div class="d-flex align-items-center justify-content-between mb-3 no-print">
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">
                <i class="uil uil-arrow-left me-1"></i>Back to Orders
            </a>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="uil uil-print me-1"></i>Print PO
                </button>

                @if ($canEdit && $order->canEdit())
                    <a href="{{ route('admin.purchase-orders.edit', ['purchase_order' => $order->id]) }}" class="btn btn-outline-primary">
                        <i class="uil uil-edit me-1"></i>Edit Order
                    </a>
                @endif

                @if ($canEdit && $order->canMarkOrdered())
                    <form method="POST" action="{{ route('admin.purchase-orders.mark-ordered', ['purchase_order' => $order->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="uil uil-truck me-1"></i>Mark as Ordered
                        </button>
                    </form>
                @endif

                @if ($canReceive && $order->canReceive())
                    <form method="POST" action="{{ route('admin.purchase-orders.receive', ['purchase_order' => $order->id]) }}"
                          onsubmit="return confirm('Stock in inventory for Purchase Order #{{ $order->po_number }}? All items with stock control enabled will have their warehouse stock incremented.');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="uil uil-arrow-down-right me-1"></i>Receive & Stock In
                        </button>
                    </form>
                @endif

                @if ($canEdit && $order->canCancel())
                    <form method="POST" action="{{ route('admin.purchase-orders.cancel', ['purchase_order' => $order->id]) }}"
                          onsubmit="return confirm('Cancel Purchase Order #{{ $order->po_number }}? This will mark the order as voided.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="uil uil-times-circle me-1"></i>Cancel Order
                        </button>
                    </form>
                @endif

                @if ($canEdit && $order->canMarkDraft())
                    <form method="POST" action="{{ route('admin.purchase-orders.mark-draft', ['purchase_order' => $order->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="uil uil-redo me-1"></i>{{ $order->status === 'Cancelled' ? 'Reopen as Draft' : 'Revert to Draft' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Order Card / Invoice Document --}}
        <div class="card">
            <div class="card-body p-4">
                {{-- Header --}}
                <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                    <div>
                        <h4 class="invoice-title mb-1">PURCHASE ORDER</h4>
                        <div class="text-primary fw-bold font-size-18 mb-1">#{{ $order->po_number }}</div>
                        <div class="text-muted font-size-12">
                            Created on {{ $order->created_at ? $order->created_at->format('M d, Y H:i') : '-' }}
                            @if ($order->creator)
                                by <strong>{{ $order->creator->name }}</strong>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        @php
                            $badgeClass = match($order->status) {
                                'Draft' => 'badge-draft',
                                'Ordered' => 'badge-ordered',
                                'Received' => 'badge-received',
                                'Cancelled' => 'badge-cancelled',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} font-size-14 px-3 py-2 mb-2 d-inline-block">
                            {{ $order->status }}
                        </span>
                        @if ($order->stock_received_at)
                            <div class="text-success font-size-12 fw-semibold">
                                <i class="uil uil-check-circle me-1"></i>Stocked in: {{ $order->stock_received_at->format('M d, Y H:i') }}
                            </div>
                        @else
                            <div class="text-muted font-size-12">
                                <i class="uil uil-clock me-1"></i>Pending warehouse receipt
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Vendor & Branch Details --}}
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <div class="text-muted font-size-12 text-uppercase fw-bold mb-2">Vendor / Supplier:</div>
                        @if ($order->vendor)
                            <h5 class="font-size-15 mb-1">{{ $order->vendor->name }}</h5>
                            <div class="text-muted font-size-13 mb-1">Code: <strong>{{ $order->vendor->code }}</strong></div>
                            @if ($order->vendor->phone)
                                <div class="text-muted font-size-13 mb-1"><i class="uil uil-phone me-1"></i>{{ $order->vendor->phone }}</div>
                            @endif
                            @if ($order->vendor->email)
                                <div class="text-muted font-size-13 mb-1"><i class="uil uil-envelope me-1"></i>{{ $order->vendor->email }}</div>
                            @endif
                            @if ($order->vendor->address)
                                <div class="text-muted font-size-13"><i class="uil uil-map-marker me-1"></i>{{ $order->vendor->address }}</div>
                            @endif
                        @else
                            <div class="text-muted">Direct / Generic Supplier</div>
                        @endif
                    </div>
                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                        <div class="text-muted font-size-12 text-uppercase fw-bold mb-2">Order Details:</div>
                        <div class="text-muted font-size-13 mb-1">Order Date: <span class="fw-semibold text-dark">{{ $order->order_date ? $order->order_date->format('M d, Y') : '-' }}</span></div>
                        <div class="text-muted font-size-13 mb-1">Expected Date: <span class="fw-semibold text-dark">{{ $order->expected_date ? $order->expected_date->format('M d, Y') : 'Not specified' }}</span></div>
                        <div class="text-muted font-size-13 mb-1">Destination Branch: <span class="fw-semibold text-dark">{{ $order->branch?->name ?: 'All / Default' }}</span></div>
                        <div class="text-muted font-size-13">Currency: <span class="fw-semibold text-dark">{{ $order->currency_code }}</span></div>
                    </div>
                </div>

                {{-- Items Table --}}
                <div class="table-responsive mb-4">
                    <table class="table table-nowrap align-middle table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Item Description</th>
                                <th>Variant / UOM</th>
                                <th class="text-center">Stock Control</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $index => $itemLine)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $itemLine->item?->name ?: 'Unknown Item' }}</div>
                                        <div class="text-muted font-size-11">
                                            SKU: {{ $itemLine->item?->sku ?: '-' }}
                                            @if ($itemLine->item && $itemLine->item->currency && (int) $itemLine->item->currency_id !== (int) $order->currency_id)
                                                <span class="ms-1 badge bg-soft-info text-info" title="Item catalog price is in a different currency">
                                                    Catalog: {{ format_currency_amount($itemLine->item->price, $itemLine->item->currency) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($itemLine->variant)
                                            <span class="badge bg-soft-primary text-primary">Variant: {{ $itemLine->variant->sku ?: $itemLine->variant->name }}</span>
                                        @elseif ($itemLine->unitOfMeasure)
                                            <span class="badge bg-soft-info text-info">UOM: {{ $itemLine->unitOfMeasure->name }}</span>
                                        @else
                                            <span class="text-muted font-size-12">Standard Base Unit</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($itemLine->item && $itemLine->item->stock_control)
                                            <span class="badge bg-soft-success text-success" title="Inventory stock is tracked and updated on receipt">
                                                <i class="uil uil-box me-1"></i>Tracked (Stock: {{ $itemLine->item->stock }})
                                            </span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary" title="Stock control is off. Quantity will not modify inventory">
                                                No Stock Control
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format((float) $itemLine->quantity, 2) }}
                                    </td>
                                    @php
                                        $orderCurrency = $order->currency ?: tenant_base_currency();
                                        $lineCurrency = $itemLine->item?->currency ?: $orderCurrency;
                                        $isDifferentCurrency = (int) ($lineCurrency?->id ?? 0) !== (int) ($orderCurrency?->id ?? 0);
                                    @endphp
                                    <td class="text-end">
                                        {{ format_currency_amount($itemLine->unit_cost, $lineCurrency) }}
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        {{ format_currency_amount($itemLine->subtotal, $lineCurrency) }}
                                        @if ($isDifferentCurrency && isset($exchangeRates['by_code']))
                                            @php
                                                $lineInOrder = app(\App\Repositories\PurchaseOrderRepository::class)->convertAmount(
                                                    (float) $itemLine->subtotal,
                                                    (string) ($lineCurrency?->code ?: 'USD'),
                                                    (string) ($orderCurrency?->code ?: 'USD'),
                                                    $exchangeRates['by_code'] ?? []
                                                );
                                            @endphp
                                            <div class="text-muted font-size-11 fw-normal">(≈ {{ format_currency_amount($lineInOrder, $orderCurrency) }})</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Totals and Notes --}}
                <div class="row">
                    <div class="col-sm-7">
                        @if ($order->notes)
                            <div class="p-3 bg-light rounded">
                                <h6 class="font-size-13 text-uppercase text-muted fw-bold mb-1">Notes / Instructions:</h6>
                                <p class="text-muted mb-0 font-size-13">{{ $order->notes }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="col-sm-5">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm text-end mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Subtotal:</td>
                                        <td class="fw-semibold">{{ format_currency_amount($order->subtotal, $order->currency) }}</td>
                                    </tr>
                                    @if ($order->tax_amount > 0)
                                        <tr>
                                            <td class="text-muted">Tax (+):</td>
                                            <td class="fw-semibold">{{ format_currency_amount($order->tax_amount, $order->currency) }}</td>
                                        </tr>
                                    @endif
                                    @if ($order->shipping_amount > 0)
                                        <tr>
                                            <td class="text-muted">Shipping (+):</td>
                                            <td class="fw-semibold">{{ format_currency_amount($order->shipping_amount, $order->currency) }}</td>
                                        </tr>
                                    @endif
                                    @if ($order->discount_amount > 0)
                                        <tr>
                                            <td class="text-muted">Discount (-):</td>
                                            <td class="fw-semibold text-danger">-{{ format_currency_amount($order->discount_amount, $order->currency) }}</td>
                                        </tr>
                                    @endif
                                    <tr class="border-top">
                                        <td class="fw-bold font-size-16 text-dark">Total Amount:</td>
                                        <td class="fw-bold font-size-16 text-primary">{{ format_currency_amount($order->total_amount, $order->currency) }}</td>
                                    </tr>
                                    @if (! empty($exchangeRates['remarks']))
                                        @php
                                            $hasDiff = $order->items->contains(function($it) use ($order) {
                                                return $it->item && $it->item->currency_id && (int) $it->item->currency_id !== (int) $order->currency_id;
                                            });
                                        @endphp
                                        @if ($hasDiff)
                                            <tr>
                                                <td colspan="2" class="pt-2 text-end">
                                                    <span class="badge bg-soft-info text-info font-size-11">
                                                        <i class="uil uil-info-circle me-1"></i>
                                                        {{ implode(' · ', array_values($exchangeRates['remarks'])) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endif
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
