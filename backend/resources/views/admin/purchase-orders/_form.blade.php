@push('styles')
<link href="{{ global_asset('minible/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .admin-form-page { padding-bottom: 120px; }
    .admin-fixed-action-bar {
        position: fixed; right: 0; bottom: 0; left: 0; z-index: 1040;
        background: #ffffff; border-top: 1px solid #e9e9ef;
        box-shadow: 0 -4px 18px rgba(39, 48, 78, 0.08);
    }
    .item-row { transition: background 0.15s ease; }
    .item-row:hover { background-color: rgba(0, 0, 0, 0.015); }
    .totals-summary-card { background-color: #f8f9fa; border: 1px solid #eff2f7; border-radius: 8px; }
    .modal-item-row { cursor: pointer; user-select: none; transition: background-color 0.15s ease; }
    .modal-item-row:hover { background-color: rgba(85, 110, 230, 0.06); }
    .modal-item-row.selected { background-color: rgba(85, 110, 230, 0.12) !important; }

    /* Match Select2 height and border with Bootstrap form-control */
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single,
    .select2-container--default .select2-selection--single {
        height: calc(1.5em + 0.94rem + 2px);
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + 0.94rem);
        padding-left: 0.75rem;
        padding-right: 2rem;
        color: #495057;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.94rem + 2px);
        right: 0.5rem;
    }

    /* Input-group integration for Select2 in items table */
    .input-group > .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }
    .input-group > .select2-container .select2-selection--single {
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }
</style>
@endpush

<div class="row">
    {{-- Header Section --}}
    <div class="col-lg-12">
        <div class="card mb-4">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0">Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="po_number">PO Number <span class="text-danger">*</span></label>
                        <input type="text" id="po_number" name="po_number"
                               class="form-control @error('po_number') is-invalid @enderror"
                               value="{{ old('po_number', $purchaseOrder->po_number) }}" required />
                        @error('po_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="vendor_id">Vendor / Supplier</label>
                        <select id="vendor_id" name="vendor_id" class="form-select select2 @error('vendor_id') is-invalid @enderror">
                            <option value="">-- Select Vendor --</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}" {{ (string) old('vendor_id', $purchaseOrder->vendor_id) === (string) $v->id ? 'selected' : '' }}>
                                    {{ $v->name }} ({{ $v->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('vendor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="branch_id">Destination Branch</label>
                        <select id="branch_id" name="branch_id" class="form-select select2 @error('branch_id') is-invalid @enderror">
                            <option value="">-- Main / Default Branch --</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (string) old('branch_id', $purchaseOrder->branch_id) === (string) $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="status">Order Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="{{ \App\Models\PurchaseOrder::STATUS_DRAFT }}" {{ old('status', $purchaseOrder->status) === \App\Models\PurchaseOrder::STATUS_DRAFT ? 'selected' : '' }}>
                                📝 {{ __('Draft (Preparing)') }}
                            </option>
                            <option value="{{ \App\Models\PurchaseOrder::STATUS_ORDERED }}" {{ old('status', $purchaseOrder->status) === \App\Models\PurchaseOrder::STATUS_ORDERED ? 'selected' : '' }}>
                                🚚 {{ __('Ordered (Placed with Supplier)') }}
                            </option>
                            @if ($purchaseOrder->exists)
                                <option value="{{ \App\Models\PurchaseOrder::STATUS_CANCELLED }}" {{ old('status', $purchaseOrder->status) === \App\Models\PurchaseOrder::STATUS_CANCELLED ? 'selected' : '' }}>
                                    ❌ {{ __('Cancelled (Voided)') }}
                                </option>
                            @endif
                            @if ($purchaseOrder->canReceive())
                                <option value="{{ \App\Models\PurchaseOrder::STATUS_RECEIVED }}" {{ old('status', $purchaseOrder->status) === \App\Models\PurchaseOrder::STATUS_RECEIVED ? 'selected' : '' }}>
                                    ✅ {{ __('Received (Stock In to Inventory)') }}
                                </option>
                            @elseif ($purchaseOrder->isReceived())
                                <option value="{{ \App\Models\PurchaseOrder::STATUS_RECEIVED }}" selected disabled>
                                    ✅ {{ __('Received (Stock In to Inventory)') }}
                                </option>
                            @endif
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="order_date">Order Date <span class="text-danger">*</span></label>
                        <input type="date" id="order_date" name="order_date"
                               class="form-control @error('order_date') is-invalid @enderror"
                               value="{{ old('order_date', $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : now()->toDateString()) }}" required />
                        @error('order_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="expected_date">Expected Delivery Date</label>
                        <input type="date" id="expected_date" name="expected_date"
                               class="form-control @error('expected_date') is-invalid @enderror"
                               value="{{ old('expected_date', $purchaseOrder->expected_date ? $purchaseOrder->expected_date->format('Y-m-d') : '') }}" />
                        @error('expected_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="currency_id">Currency</label>
                        <select id="currency_id" name="currency_id" class="form-select @error('currency_id') is-invalid @enderror">
                            @foreach ($currencies as $curr)
                                <option value="{{ $curr->id }}" data-code="{{ $curr->code }}"
                                        {{ (string) old('currency_id', $purchaseOrder->currency_id) === (string) $curr->id ? 'selected' : '' }}>
                                    {{ $curr->code }} ({{ $curr->symbol ?: $curr->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('currency_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="notes">Notes & References</label>
                        <input type="text" id="notes" name="notes" class="form-control"
                               placeholder="e.g. Quotation #, delivery instructions"
                               value="{{ old('notes', $purchaseOrder->notes) }}" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Section --}}
    <div class="col-lg-12">
        {{-- Missing Exchange Rate Alert Banner --}}
        <div id="exchangeRateMissingBanner" class="d-none mb-4">
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-3 p-3 mb-0">
                <div class="avatar-xs flex-shrink-0">
                    <span class="avatar-title rounded-circle bg-warning text-white font-size-16">
                        <i class="uil uil-exclamation-triangle"></i>
                    </span>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading fw-bold mb-1 text-dark">Exchange Rate Required For Current Date</h6>
                    <p class="mb-2 font-size-13 text-muted" id="exchangeRateMissingText">
                        Today's exchange rate for this item's currency is not configured. Exchange rates must be set for the current day before this order can be saved.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="{{ route('admin.rate-index.index') }}" id="exchangeRateManageLink" target="_blank" class="btn btn-sm btn-warning text-dark fw-semibold shadow-sm">
                            <i class="uil uil-external-link-alt me-1"></i>Set Up Exchange Rate in Exchange Rates
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshExchangeRates" onclick="refreshExchangeRates(this)">
                            <i class="uil uil-sync me-1"></i>Refresh Rates
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Purchase Items</h5>
                    <p class="text-muted font-size-12 mb-0">Select catalog items with <strong>stock control enabled</strong> to order and stock in upon receipt.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-primary waves-effect waves-light" onclick="openItemPickerModal()">
                        <i class="uil uil-apps me-1 font-size-14"></i>Choose Multiple Items
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addItemRowBtn">
                        <i class="uil uil-plus me-1"></i>Add Row
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 35%;">Item <span class="text-danger">*</span></th>
                                <th style="width: 20%;">Variant / UOM</th>
                                <th style="width: 15%;">Quantity <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Unit Cost <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Line Total</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemRowsContainer">
                            {{-- Rows populated via JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Totals Summary Section --}}
    <div class="col-lg-8"></div>
    <div class="col-lg-4">
        <div class="card totals-summary-card p-3 mb-4">
            <h6 class="text-uppercase font-size-12 text-muted fw-bold mb-3">Order Summary</h6>

            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Subtotal:</span>
                <span class="fw-semibold" id="displaySubtotal">$0.00</span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="tax_amount" class="text-muted mb-0 font-size-13">Tax Amount (+):</label>
                <input type="number" step="0.01" min="0" id="tax_amount" name="tax_amount"
                       class="form-control form-control-sm text-end" style="width: 110px;"
                       value="{{ old('tax_amount', $purchaseOrder->tax_amount ?: 0) }}" />
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="shipping_amount" class="text-muted mb-0 font-size-13">Shipping (+):</label>
                <input type="number" step="0.01" min="0" id="shipping_amount" name="shipping_amount"
                       class="form-control form-control-sm text-end" style="width: 110px;"
                       value="{{ old('shipping_amount', $purchaseOrder->shipping_amount ?: 0) }}" />
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <label for="discount_amount" class="text-muted mb-0 font-size-13">Discount (-):</label>
                <input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount"
                       class="form-control form-control-sm text-end" style="width: 110px;"
                       value="{{ old('discount_amount', $purchaseOrder->discount_amount ?: 0) }}" />
            </div>

            <hr class="my-2" />

            <div class="d-flex justify-content-between align-items-center font-size-16">
                <span class="fw-bold text-dark">Total Amount:</span>
                <span class="fw-bold text-primary" id="displayTotal">$0.00</span>
            </div>

            <div id="exchangeRateMissingSummaryAlert" class="alert alert-soft-warning py-2 px-2 font-size-12 mt-3 mb-0 d-none">
                <div class="d-flex align-items-start gap-2">
                    <i class="uil uil-exclamation-triangle font-size-16 text-warning mt-1"></i>
                    <div>
                        <strong class="d-block text-dark">Exchange Rate Not Set</strong>
                        <span id="exchangeRateMissingSummaryText">Cannot calculate final total in header currency.</span>
                        <div class="mt-1">
                            <a href="{{ route('admin.rate-index.index') }}" target="_blank" class="fw-semibold text-primary text-decoration-underline font-size-11" id="summaryExchangeRateLink">
                                <i class="uil uil-arrow-up-right me-1"></i>Configure Rate
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="exchangeRateNotice" class="alert alert-soft-info py-2 px-2 font-size-12 mt-3 mb-0 d-none">
                <div class="d-flex align-items-center">
                    <i class="uil uil-info-circle me-1 font-size-14 text-info"></i>
                    <span id="exchangeRateRemarkText"></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Sticky Bottom Action Bar --}}
<div class="admin-fixed-action-bar py-3">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2 px-4">
        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="uil uil-arrow-left me-1"></i>Back to Orders
        </a>
        <div class="d-flex flex-wrap gap-2">
            @if ($purchaseOrder->exists && $purchaseOrder->canCancel())
                <button type="submit" name="submit_action" value="cancel" class="btn btn-outline-danger px-3"
                        onclick="return confirm('Cancel this purchase order #{{ $purchaseOrder->po_number }}?');">
                    <i class="uil uil-times-circle me-1"></i>Cancel Order
                </button>
            @endif

            <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary px-3">
                <i class="uil uil-file-alt me-1"></i>Save as Draft
            </button>

            <button type="submit" name="submit_action" value="ordered" class="btn btn-primary px-4">
                <i class="uil uil-truck me-1"></i>Save as Ordered
            </button>

            @if ($purchaseOrder->canReceive())
                <button type="submit" name="submit_action" value="receive" class="btn btn-success px-4"
                        onclick="return confirm('Save and immediately stock in inventory for this order?');">
                    <i class="uil uil-arrow-down-right me-1"></i>Save & Receive (Stock In)
                </button>
            @endif
        </div>
    </div>
</div>

{{-- Modal for Picking Multiple Items --}}
<div class="modal fade" id="itemPickerModal" tabindex="-1" aria-labelledby="itemPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-primary text-white font-size-14">
                            <i class="uil uil-box"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="modal-title font-size-16 mb-0" id="itemPickerModalLabel">Choose Purchase Items</h5>
                        <p class="text-muted font-size-12 mb-0">Select one or multiple catalog items to add to your order</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                {{-- Search and filter toolbar --}}
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-7">
                        <div class="position-relative">
                            <input type="text" class="form-control" id="modalItemSearchInput"
                                   placeholder="Search by item name, SKU, barcode, category..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <span class="badge bg-soft-info text-info font-size-12 me-2">
                            <i class="uil uil-box me-1"></i><span id="modalCountAll">0</span> Items Found
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="modalSelectAllVisibleBtn">
                            <i class="uil uil-check-square me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="modalClearSelectionBtn">
                            <i class="uil uil-times me-1"></i>Clear
                        </button>
                    </div>
                </div>

                {{-- Table container --}}
                <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="modalItemsTable">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr>
                                <th style="width: 45px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="modalMasterCheckbox">
                                </th>
                                <th style="min-width: 250px;">Item Details</th>
                                <th style="min-width: 140px;">Stock Control</th>
                                <th style="min-width: 110px;" class="text-end">Current Stock</th>
                                <th style="min-width: 140px;" class="text-end">Catalog Cost</th>
                                <th style="min-width: 130px;">Options</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsTableBody">
                            {{-- Populated by JS --}}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="badge bg-soft-primary text-primary font-size-13 px-3 py-2 rounded-pill" id="modalSelectedCountBadge">
                        0 items selected
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 waves-effect waves-light" id="modalConfirmAddBtn" disabled>
                        <i class="uil uil-plus-circle me-1"></i>Add Selected Items
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $baseCurrency = $baseCurrency ?: tenant_base_currency();
    $catalogItemsData = $items->map(function($it) use ($baseCurrency) {
        $curr = $it->currency ?: $baseCurrency;
        return [
            'id' => $it->id,
            'name' => $it->name,
            'sku' => $it->sku,
            'barcode' => $it->barcode ?? '',
            'category_name' => $it->category?->name ?? '',
            'cost_price' => (float) ($it->cost_price ?: $it->price ?: 0),
            'currency_id' => $curr?->id,
            'currency_code' => strtoupper((string) ($curr?->code ?: 'USD')),
            'currency_symbol' => (string) ($curr?->symbol ?: ($curr?->code ?: '$')),
            'currency_decimals' => currency_decimal_places($curr),
            'stock_control' => (bool) $it->stock_control,
            'current_stock' => (int) $it->stock,
            'has_variants' => $it->variants && $it->variants->isNotEmpty(),
            'variants' => $it->variants ? $it->variants->map(function($v) {
                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->sku ?: 'Variant #' . $v->id,
                    'stock' => (int) $v->stock,
                    'price' => (float) ($v->price ?: 0),
                ];
            })->values()->all() : [],
            'has_uom' => $it->uomGroup && $it->uomGroup->units && $it->uomGroup->units->isNotEmpty(),
            'uom_units' => ($it->uomGroup && $it->uomGroup->units) ? $it->uomGroup->units->map(function($u) {
                return [
                    'id' => $u->unit_of_measure_id ?: $u->id,
                    'name' => $u->unit?->name ?: $u->unit?->code ?: 'Unit',
                    'factor' => (float) ($u->conversion_factor_to_base ?: 1),
                ];
            })->values()->all() : [],
        ];
    })->values()->all();

    $existingLinesData = old('items', ($purchaseOrder->items && $purchaseOrder->items->isNotEmpty()) ? $purchaseOrder->items->map(function($line) {
        return [
            'item_id' => $line->item_id,
            'item_variant_id' => $line->item_variant_id,
            'uom_id' => $line->uom_id,
            'quantity' => (float) $line->quantity,
            'unit_cost' => (float) $line->unit_cost,
            'notes' => $line->notes,
        ];
    })->values()->all() : []);

    $safeExchangeRates = $exchangeRates ?? [
        'date' => $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : now()->toDateString(),
        'is_today' => true,
        'by_id' => [],
        'by_code' => [],
        'configured_ids' => [],
        'configured_codes' => [],
        'remarks' => [],
        'manage_url' => route('admin.rate-index.index'),
    ];
@endphp

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/select2/js/select2.min.js') }}"></script>
<script>
    // Initial pre-loaded items (only contains existing PO items or old('items') on validation failure)
    const initialItems = @json($catalogItemsData);
    const existingLines = @json($existingLinesData);
    const currenciesMap = @json($currencies->keyBy('id'));
    let exchangeRates = @json($safeExchangeRates);

    // Dynamic client-side cache of items
    const itemCache = new Map();
    initialItems.forEach(it => itemCache.set(String(it.id), it));

    let rowIndex = 0;
    let currentPickerTargetRowIndex = null;
    let selectedItemIdsInModal = new Set();
    let modalLoadedItems = [];
    let modalSearchTimer = null;
    let itemPickerModalInstance = null;

    function getCurrentPoCurrency() {
        const select = document.getElementById('currency_id');
        const id = select ? select.value : null;
        if (id && currenciesMap[id]) {
            return {
                id: parseInt(id, 10),
                code: currenciesMap[id].code,
                symbol: currenciesMap[id].symbol || currenciesMap[id].code,
                decimal_places: parseInt(currenciesMap[id].decimal_places, 10) !== undefined ? parseInt(currenciesMap[id].decimal_places, 10) : 2
            };
        }
        return {
            id: id ? parseInt(id, 10) : 1,
            code: 'USD',
            symbol: '$',
            decimal_places: 2
        };
    }

    function formatCurrency(amount, symbol = '$', decimals = 2) {
        amount = parseFloat(amount) || 0;
        const formatted = amount.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        return `${symbol}${formatted}`;
    }

    function convertAmountToPoCurrency(amount, fromCode, toCode) {
        amount = parseFloat(amount) || 0;
        if (amount <= 0) return 0;
        fromCode = (fromCode || 'USD').toUpperCase().trim();
        toCode = (toCode || 'USD').toUpperCase().trim();
        if (fromCode === toCode) return amount;

        const rates = (exchangeRates && exchangeRates.by_code) ? exchangeRates.by_code : {};
        const fromRate = parseFloat(rates[fromCode]) || 1.0;
        const toRate = parseFloat(rates[toCode]) || 1.0;

        const baseAmount = fromRate > 0 ? (amount / fromRate) : amount;
        return baseAmount * (toRate > 0 ? toRate : 1.0);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initRowSelect2(idx) {
        if (!window.jQuery || !jQuery.fn.select2) return;

        const $select = jQuery(`#item-select-${idx}`);
        $select.select2({
            width: '100%',
            placeholder: '-- Type item name, SKU, or click browse --',
            allowClear: true,
            ajax: {
                url: "{{ route('admin.purchase-orders.search-items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term || '',
                        limit: 25
                    };
                },
                processResults: function (data) {
                    const items = data.items || [];
                    items.forEach(it => itemCache.set(String(it.id), it));
                    return {
                        results: items.map(it => ({
                            id: it.id,
                            text: `${it.name} (${it.sku || 'No SKU'}) [Stock: ${it.current_stock}]`,
                            itemData: it
                        }))
                    };
                },
                cache: true
            }
        }).on('select2:select', function (e) {
            if (e.params && e.params.data && e.params.data.itemData) {
                itemCache.set(String(e.params.data.itemData.id), e.params.data.itemData);
            }
            handleItemChange(idx);
        }).on('select2:clear', function () {
            handleItemChange(idx);
        });
    }

    function renderItemRow(data = {}) {
        const index = rowIndex++;
        const itemId = data.item_id || '';
        const variantId = data.item_variant_id || '';
        const uomId = data.uom_id || '';
        const quantity = data.quantity !== undefined ? data.quantity : 1;
        const unitCost = data.unit_cost !== undefined ? data.unit_cost : 0;
        const notes = data.notes || '';

        const item = itemId ? itemCache.get(String(itemId)) : null;
        let itemOptionsHtml = '<option value="">-- Type to search or browse --</option>';
        if (item) {
            itemOptionsHtml = `<option value="${item.id}" selected>${escapeHtml(item.name)} (${escapeHtml(item.sku || 'No SKU')}) [Stock: ${item.current_stock}]</option>`;
        }

        const itemCurrSymbol = item ? (item.currency_symbol || '$') : '$';
        const itemStep = item && item.currency_decimals === 0 ? '1' : '0.01';

        const rowHtml = `
            <tr class="item-row" id="row-${index}">
                <td>
                    <div class="input-group">
                        <select name="items[${index}][item_id]" class="form-select item-select" id="item-select-${index}" required>
                            ${itemOptionsHtml}
                        </select>
                        <button type="button" class="btn btn-outline-primary" title="Browse / Choose multiple items from catalog" onclick="openItemPickerModal(${index})">
                            <i class="uil uil-search font-size-14"></i>
                        </button>
                    </div>
                    <div class="font-size-11 text-muted mt-1 item-info" id="item-info-${index}"></div>
                </td>
                <td>
                    <div class="row g-1">
                        <div class="col-12 variant-wrapper" id="variant-wrapper-${index}" style="display: none;">
                            <select name="items[${index}][item_variant_id]" class="form-select variant-select" id="variant-select-${index}">
                                <option value="">-- Variant --</option>
                            </select>
                        </div>
                        <div class="col-12 uom-wrapper" id="uom-wrapper-${index}" style="display: none;">
                            <select name="items[${index}][uom_id]" class="form-select uom-select" id="uom-select-${index}">
                                <option value="">-- Base Unit --</option>
                            </select>
                        </div>
                    </div>
                </td>
                <td>
                    <input type="number" step="0.0001" min="0.0001" name="items[${index}][quantity]"
                           class="form-control quantity-input text-end" value="${quantity}" required
                           oninput="calculateTotals()" />
                </td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text line-curr-symbol font-size-12 px-2 bg-light text-muted" id="curr-symbol-${index}">
                            ${itemCurrSymbol}
                        </span>
                        <input type="number" step="${itemStep}" min="0" name="items[${index}][unit_cost]"
                               class="form-control unit-cost-input text-end" value="${unitCost}" required
                               oninput="calculateTotals()" />
                    </div>
                </td>
                <td class="text-end fw-semibold line-total" id="line-total-${index}">
                    0.00
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(${index})">
                        <i class="uil uil-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        document.getElementById('itemRowsContainer').insertAdjacentHTML('beforeend', rowHtml);

        initRowSelect2(index);

        if (itemId) {
            handleItemChange(index, variantId, uomId, unitCost);
        }
        calculateTotals();
    }

    function handleItemChange(idx, presetVariantId = null, presetUomId = null, presetCost = null) {
        const select = document.getElementById(`item-select-${idx}`);
        const itemId = select ? select.value : null;
        const itemInfo = document.getElementById(`item-info-${idx}`);
        const variantWrapper = document.getElementById(`variant-wrapper-${idx}`);
        const variantSelect = document.getElementById(`variant-select-${idx}`);
        const uomWrapper = document.getElementById(`uom-wrapper-${idx}`);
        const uomSelect = document.getElementById(`uom-select-${idx}`);
        const unitCostInput = document.querySelector(`#row-${idx} .unit-cost-input`);

        if (!itemId) {
            if (itemInfo) itemInfo.innerHTML = '';
            if (variantWrapper) variantWrapper.style.display = 'none';
            if (uomWrapper) uomWrapper.style.display = 'none';
            calculateTotals();
            return;
        }

        const applyItemData = (item) => {
            const itemCurrSymbol = item.currency_symbol || '$';
            const itemCurrCode = item.currency_code || 'USD';
            const itemDecimals = item.currency_decimals !== undefined ? item.currency_decimals : 2;
            const itemStep = itemDecimals === 0 ? '1' : '0.01';

            // Update currency symbol badge on unit cost input
            const currSymbolEl = document.getElementById(`curr-symbol-${idx}`);
            if (currSymbolEl) {
                currSymbolEl.textContent = itemCurrSymbol;
            }

            if (unitCostInput) {
                unitCostInput.step = itemStep;
            }

            // Display current stock, stock control, and catalog currency info
            let badges = [];
            if (item.stock_control) {
                badges.push('<span class="badge bg-soft-success text-success">Stock: ' + item.current_stock + ' (Tracked)</span>');
            } else {
                badges.push('<span class="badge bg-soft-secondary text-secondary">Stock: Off</span>');
            }

            const itemFormatted = formatCurrency(item.cost_price, itemCurrSymbol, itemDecimals);
            badges.push(`<span class="badge bg-soft-info text-info"><i class="uil uil-tag-alt me-1"></i>Catalog Price: ${itemFormatted} (${itemCurrCode})</span>`);

            if (itemInfo) itemInfo.innerHTML = badges.join(' ');

            // Auto-fill unit cost with item's own catalog price (no currency conversion)
            if ((!presetCost || Number(presetCost) === 0) && Number(item.cost_price) > 0 && (!unitCostInput.value || Number(unitCostInput.value) === 0)) {
                unitCostInput.value = item.cost_price;
            }

            // Variants dropdown
            if (item.has_variants && item.variants.length > 0) {
                variantWrapper.style.display = 'block';
                let vHtml = '<option value="">-- Select Variant --</option>';
                item.variants.forEach(v => {
                    const sel = String(v.id) === String(presetVariantId) ? 'selected' : '';
                    vHtml += `<option value="${v.id}" ${sel}>${escapeHtml(v.name)} (Stock: ${v.stock})</option>`;
                });
                variantSelect.innerHTML = vHtml;
            } else {
                variantWrapper.style.display = 'none';
                variantSelect.innerHTML = '<option value="">-- None --</option>';
            }

            // UOM units dropdown
            if (item.has_uom && item.uom_units.length > 0) {
                uomWrapper.style.display = 'block';
                let uHtml = '<option value="">-- Base UOM --</option>';
                item.uom_units.forEach(u => {
                    const sel = String(u.id) === String(presetUomId) ? 'selected' : '';
                    const factorInfo = u.factor > 1 ? ` (x${u.factor})` : '';
                    uHtml += `<option value="${u.id}" ${sel}>${escapeHtml(u.name)}${factorInfo}</option>`;
                });
                uomSelect.innerHTML = uHtml;
            } else {
                uomWrapper.style.display = 'none';
                uomSelect.innerHTML = '<option value="">-- Base Unit --</option>';
            }

            calculateTotals();
        };

        const item = itemCache.get(String(itemId));
        if (item) {
            applyItemData(item);
        } else {
            fetch(`{{ route('admin.purchase-orders.search-items') }}?q=${encodeURIComponent(itemId)}`)
                .then(res => res.json())
                .then(res => {
                    const found = (res.items || []).find(it => String(it.id) === String(itemId)) || (res.items && res.items[0]);
                    if (found) {
                        itemCache.set(String(found.id), found);
                        applyItemData(found);
                    }
                })
                .catch(err => console.error('Error fetching item details:', err));
        }
    }

    function removeItemRow(idx) {
        if (window.jQuery && jQuery.fn.select2) {
            try {
                jQuery(`#item-select-${idx}`).select2('destroy');
            } catch (e) {}
        }
        const row = document.getElementById(`row-${idx}`);
        if (row) {
            row.remove();
            calculateTotals();
        }
    }

    function refreshExchangeRates(triggerBtn = null) {
        const btn = triggerBtn || document.getElementById('btnRefreshExchangeRates') || document.querySelector('#exchangeRateMissingBanner button');
        const orderDateInput = document.getElementById('order_date');
        const date = orderDateInput ? orderDateInput.value : '';
        const url = `{{ route('admin.purchase-orders.rates') }}?date=${encodeURIComponent(date)}`;

        let origHtml = '';
        if (btn) {
            origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Checking...';
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.rates) {
                    exchangeRates = data.rates;
                    calculateTotals();
                }
            })
            .catch(err => console.error('Failed to refresh exchange rates:', err))
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml || '<i class="uil uil-sync me-1"></i>Refresh Rates';
                }
            });
    }

    function calculateTotals() {
        const poCurr = getCurrentPoCurrency();
        const currentDate = document.getElementById('order_date')?.value || new Date().toISOString().slice(0, 10);
        let currencyTotals = {};
        let orderSubtotalInPoCurr = 0;
        let differentCurrencyItems = false;
        let missingCurrencies = new Set();

        const configuredCodes = (exchangeRates && exchangeRates.configured_codes)
            ? Object.values(exchangeRates.configured_codes).map(c => String(c).toUpperCase().trim())
            : [poCurr.code];

        const rows = document.querySelectorAll('#itemRowsContainer tr.item-row');

        rows.forEach(row => {
            const rowIdStr = row.id.replace('row-', '');
            const rIdx = parseInt(rowIdStr, 10);
            const select = document.getElementById(`item-select-${rIdx}`);
            const itemId = select ? select.value : null;
            const item = itemId ? itemCache.get(String(itemId)) : null;

            const qtyInput = row.querySelector('.quantity-input');
            const costInput = row.querySelector('.unit-cost-input');
            const totalCell = row.querySelector('.line-total');
            const currSymbolEl = document.getElementById(`curr-symbol-${rIdx}`);

            const qty = Math.max(0, parseFloat(qtyInput ? qtyInput.value : 0) || 0);
            const cost = Math.max(0, parseFloat(costInput ? costInput.value : 0) || 0);
            const lineTotal = qty * cost;

            const currSymbol = item?.currency_symbol || poCurr.symbol;
            const currCode = (item?.currency_code || poCurr.code).toUpperCase().trim();
            const currDecimals = item?.currency_decimals !== undefined ? item.currency_decimals : poCurr.decimal_places;

            if (currSymbolEl) {
                currSymbolEl.textContent = currSymbol;
            }

            const lineTotalFormatted = formatCurrency(lineTotal, currSymbol, currDecimals);

            if (!currencyTotals[currCode]) {
                currencyTotals[currCode] = {
                    code: currCode,
                    symbol: currSymbol,
                    decimals: currDecimals,
                    subtotal: 0
                };
            }
            currencyTotals[currCode].subtotal += lineTotal;

            if (currCode !== poCurr.code && lineTotal > 0) {
                differentCurrencyItems = true;
                const isConfigured = configuredCodes.includes(currCode);

                if (!isConfigured) {
                    missingCurrencies.add(currCode);
                    if (totalCell) {
                        totalCell.innerHTML = `<div>${lineTotalFormatted}</div><div class="text-danger font-size-11 fw-semibold mt-1"><i class="uil uil-exclamation-triangle me-1"></i>Rate not set for ${escapeHtml(currentDate)}</div>`;
                    }
                } else {
                    const lineInPo = convertAmountToPoCurrency(lineTotal, currCode, poCurr.code);
                    orderSubtotalInPoCurr += lineInPo;
                    const poApprox = formatCurrency(lineInPo, poCurr.symbol, poCurr.decimal_places);
                    if (totalCell) {
                        totalCell.innerHTML = `<div>${lineTotalFormatted}</div><div class="text-muted font-size-11 fw-normal">(≈ ${poApprox})</div>`;
                    }
                }
            } else {
                orderSubtotalInPoCurr += lineTotal;
                if (totalCell) {
                    totalCell.textContent = lineTotalFormatted;
                }
            }
        });

        const tax = Math.max(0, parseFloat(document.getElementById('tax_amount')?.value || 0) || 0);
        const shipping = Math.max(0, parseFloat(document.getElementById('shipping_amount')?.value || 0) || 0);
        const discount = Math.max(0, parseFloat(document.getElementById('discount_amount')?.value || 0) || 0);

        const subtotalEl = document.getElementById('displaySubtotal');
        const totalEl = document.getElementById('displayTotal');
        const banner = document.getElementById('exchangeRateMissingBanner');
        const bannerText = document.getElementById('exchangeRateMissingText');
        const manageLink = document.getElementById('exchangeRateManageLink');
        const summaryAlert = document.getElementById('exchangeRateMissingSummaryAlert');
        const summaryAlertText = document.getElementById('exchangeRateMissingSummaryText');
        const summaryLink = document.getElementById('summaryExchangeRateLink');
        const rateNotice = document.getElementById('exchangeRateNotice');
        const rateRemarkText = document.getElementById('exchangeRateRemarkText');

        const currKeys = Object.keys(currencyTotals);
        const grandTotal = Math.max(0, orderSubtotalInPoCurr + tax + shipping - discount);

        if (missingCurrencies.size > 0) {
            const missingList = Array.from(missingCurrencies).join(', ');
            const manageUrl = exchangeRates?.manage_url || "{{ route('admin.rate-index.index') }}";

            // Show Missing Rate Banner
            if (banner) {
                banner.classList.remove('d-none');
                if (bannerText) {
                    bannerText.innerHTML = `Exchange rate for <strong>${escapeHtml(missingList)}</strong> is not configured for order date <strong>${escapeHtml(currentDate)}</strong>. Exchange rates must be set for the current day before this order can be submitted with foreign currency items.`;
                }
                if (manageLink) manageLink.href = manageUrl;
            }

            // Show Missing Rate Summary Alert
            if (summaryAlert) {
                summaryAlert.classList.remove('d-none');
                if (summaryAlertText) {
                    summaryAlertText.innerHTML = `Missing <strong>${escapeHtml(missingList)}</strong> rate for <strong>${escapeHtml(currentDate)}</strong>.`;
                }
                if (summaryLink) summaryLink.href = manageUrl;
            }

            if (rateNotice) rateNotice.classList.add('d-none');

            // Show breakdown in subtotal
            let subStrs = [];
            currKeys.forEach(k => {
                const ct = currencyTotals[k];
                subStrs.push(formatCurrency(ct.subtotal, ct.symbol, ct.decimals));
            });
            if (subtotalEl) {
                subtotalEl.innerHTML = `<div>${subStrs.join(' + ')}</div><div class="text-danger font-size-11 fw-semibold mt-1"><i class="uil uil-exclamation-triangle me-1"></i>Rate setup required</div>`;
            }

            // Total indicator
            if (totalEl) {
                totalEl.innerHTML = `<span class="badge bg-soft-warning text-warning border border-warning px-2 py-1 font-size-12"><i class="uil uil-exclamation-circle me-1"></i>Pending ${escapeHtml(missingList)} Rate</span>`;
            }
        } else {
            // Hide Missing Rate Banners
            if (banner) banner.classList.add('d-none');
            if (summaryAlert) summaryAlert.classList.add('d-none');

            if (currKeys.length <= 1 && !differentCurrencyItems) {
                if (subtotalEl) subtotalEl.textContent = formatCurrency(orderSubtotalInPoCurr, poCurr.symbol, poCurr.decimal_places);
                if (rateNotice) rateNotice.classList.add('d-none');
            } else {
                let subStrs = [];
                currKeys.forEach(k => {
                    const ct = currencyTotals[k];
                    subStrs.push(formatCurrency(ct.subtotal, ct.symbol, ct.decimals));
                });
                if (subtotalEl) {
                    subtotalEl.innerHTML = `<div>${formatCurrency(orderSubtotalInPoCurr, poCurr.symbol, poCurr.decimal_places)}</div><div class="text-muted font-size-11 fw-normal mt-1">${subStrs.join(' + ')}</div>`;
                }

                if (rateNotice && rateRemarkText) {
                    const remarks = exchangeRates?.remarks || {};
                    const remarkList = Object.values(remarks);
                    if (remarkList.length > 0) {
                        rateRemarkText.textContent = `Exchange rate applied: ${remarkList.join(' · ')}`;
                        rateNotice.classList.remove('d-none');
                    } else {
                        rateNotice.classList.add('d-none');
                    }
                } else if (rateNotice) {
                    rateNotice.classList.add('d-none');
                }
            }

            if (totalEl) {
                totalEl.textContent = formatCurrency(grandTotal, poCurr.symbol, poCurr.decimal_places);
            }
        }
    }

    // Modal functions for choosing multiple items
    function openItemPickerModal(targetRowIndex = null) {
        currentPickerTargetRowIndex = targetRowIndex;
        selectedItemIdsInModal.clear();

        // If opened from a specific row that already has an item selected, pre-select it
        if (targetRowIndex !== null) {
            const select = document.getElementById(`item-select-${targetRowIndex}`);
            if (select && select.value) {
                selectedItemIdsInModal.add(parseInt(select.value, 10));
            }
        }

        const searchInput = document.getElementById('modalItemSearchInput');
        if (searchInput) searchInput.value = '';

        updateModalSelectionUI();
        fetchModalItems('');

        const modalEl = document.getElementById('itemPickerModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!itemPickerModalInstance) {
                itemPickerModalInstance = new bootstrap.Modal(modalEl);
            }
            itemPickerModalInstance.show();
        } else if (window.jQuery) {
            jQuery('#itemPickerModal').modal('show');
        }

        setTimeout(() => {
            if (searchInput) searchInput.focus();
        }, 350);
    }

    function fetchModalItems(query = '') {
        const tbody = document.getElementById('modalItemsTableBody');
        const countAll = document.getElementById('modalCountAll');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
                        Searching catalog items...
                    </td>
                </tr>
            `;
        }

        fetch(`{{ route('admin.purchase-orders.search-items') }}?q=${encodeURIComponent(query)}&limit=30`)
            .then(res => res.json())
            .then(res => {
                modalLoadedItems = res.items || [];
                modalLoadedItems.forEach(it => itemCache.set(String(it.id), it));

                if (countAll) countAll.textContent = modalLoadedItems.length;
                renderModalItems();
            })
            .catch(err => {
                console.error('Modal search error:', err);
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4 text-danger">
                                <i class="uil uil-exclamation-triangle font-size-24 d-block mb-1"></i>
                                Failed to load items. Please try again.
                            </td>
                        </tr>
                    `;
                }
            });
    }

    function renderModalItems() {
        const tbody = document.getElementById('modalItemsTableBody');
        if (!tbody) return;

        if (modalLoadedItems.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="uil uil-search-alt font-size-24 d-block mb-1"></i>
                        No purchaseable items found matching your search.
                    </td>
                </tr>
            `;
            updateMasterCheckboxState([]);
            return;
        }

        let rowsHtml = '';
        modalLoadedItems.forEach(it => {
            const isSelected = selectedItemIdsInModal.has(it.id);
            const stockBadge = it.stock_control
                ? `<span class="badge bg-soft-success text-success"><i class="uil uil-check-circle me-1"></i>Tracked</span>`
                : `<span class="badge bg-soft-secondary text-secondary">Untracked</span>`;

            let configBadges = '';
            if (it.has_variants && it.variants.length > 0) {
                configBadges += `<span class="badge bg-soft-info text-info me-1">${it.variants.length} Variants</span>`;
            }
            if (it.has_uom && it.uom_units.length > 0) {
                configBadges += `<span class="badge bg-soft-warning text-warning">${it.uom_units.length} UOMs</span>`;
            }
            if (!configBadges) {
                configBadges = `<span class="text-muted font-size-12">—</span>`;
            }

            const categoryHtml = it.category_name
                ? `<span class="badge bg-light text-dark font-size-11 border me-1">${escapeHtml(it.category_name)}</span>`
                : '';

            const itemCurrSymbol = it.currency_symbol || '$';
            const itemCurrCode = it.currency_code || 'USD';
            const itemDecimals = it.currency_decimals !== undefined ? it.currency_decimals : 2;

            const costDisplay = `<div class="fw-semibold text-dark">${formatCurrency(it.cost_price, itemCurrSymbol, itemDecimals)} <span class="font-size-11 text-muted">(${itemCurrCode})</span></div>`;

            rowsHtml += `
                <tr class="modal-item-row ${isSelected ? 'table-primary selected' : ''}" data-item-id="${it.id}" onclick="toggleModalItemSelection(${it.id})">
                    <td class="text-center" onclick="event.stopPropagation()">
                        <input type="checkbox" class="form-check-input modal-item-checkbox" data-item-id="${it.id}"
                               ${isSelected ? 'checked' : ''} onchange="toggleModalItemSelection(${it.id})" />
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(it.name)}</div>
                        <div class="font-size-12 text-muted">
                            ${categoryHtml}
                            <span class="font-monospace">SKU: ${escapeHtml(it.sku || 'N/A')}</span>
                            ${it.barcode ? `<span class="ms-2 font-monospace text-muted">| Barcode: ${escapeHtml(it.barcode)}</span>` : ''}
                        </div>
                    </td>
                    <td>${stockBadge}</td>
                    <td class="text-end fw-semibold ${it.current_stock <= 0 && it.stock_control ? 'text-danger' : 'text-dark'}">
                        ${it.stock_control ? it.current_stock : '<span class="text-muted">-</span>'}
                    </td>
                    <td class="text-end">
                        ${costDisplay}
                    </td>
                    <td>${configBadges}</td>
                </tr>
            `;
        });

        tbody.innerHTML = rowsHtml;
        updateMasterCheckboxState(modalLoadedItems);
    }

    function toggleModalItemSelection(itemId) {
        itemId = parseInt(itemId, 10);
        if (selectedItemIdsInModal.has(itemId)) {
            selectedItemIdsInModal.delete(itemId);
        } else {
            selectedItemIdsInModal.add(itemId);
        }

        const row = document.querySelector(`.modal-item-row[data-item-id="${itemId}"]`);
        const checkbox = document.querySelector(`.modal-item-checkbox[data-item-id="${itemId}"]`);
        const isSelected = selectedItemIdsInModal.has(itemId);

        if (row) {
            row.classList.toggle('table-primary', isSelected);
            row.classList.toggle('selected', isSelected);
        }
        if (checkbox) {
            checkbox.checked = isSelected;
        }

        updateModalSelectionUI();
    }

    function updateModalSelectionUI() {
        const count = selectedItemIdsInModal.size;
        const countBadge = document.getElementById('modalSelectedCountBadge');
        const confirmBtn = document.getElementById('modalConfirmAddBtn');

        if (countBadge) {
            countBadge.textContent = count === 1 ? '1 item selected' : `${count} items selected`;
            countBadge.className = count > 0
                ? 'badge bg-primary font-size-13 px-3 py-2 rounded-pill'
                : 'badge bg-soft-secondary text-secondary font-size-13 px-3 py-2 rounded-pill';
        }

        if (confirmBtn) {
            confirmBtn.disabled = count === 0;
            confirmBtn.innerHTML = count > 0
                ? `<i class="uil uil-plus-circle me-1"></i>Add ${count} ${count === 1 ? 'Item' : 'Items'}`
                : `<i class="uil uil-plus-circle me-1"></i>Add Selected Items`;
        }

        const visibleCheckboxes = document.querySelectorAll('.modal-item-checkbox');
        const masterCb = document.getElementById('modalMasterCheckbox');
        if (masterCb && visibleCheckboxes.length > 0) {
            const allChecked = Array.from(visibleCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(visibleCheckboxes).some(cb => cb.checked);
            masterCb.checked = allChecked;
            masterCb.indeterminate = !allChecked && someChecked;
        } else if (masterCb) {
            masterCb.checked = false;
            masterCb.indeterminate = false;
        }
    }

    function updateMasterCheckboxState(visibleItems) {
        const masterCb = document.getElementById('modalMasterCheckbox');
        if (!masterCb) return;

        if (visibleItems.length === 0) {
            masterCb.checked = false;
            masterCb.indeterminate = false;
            return;
        }

        const allChecked = visibleItems.every(it => selectedItemIdsInModal.has(it.id));
        const someChecked = visibleItems.some(it => selectedItemIdsInModal.has(it.id));

        masterCb.checked = allChecked;
        masterCb.indeterminate = !allChecked && someChecked;
    }

    function selectAllVisibleInModal() {
        const visibleCheckboxes = document.querySelectorAll('.modal-item-checkbox');
        visibleCheckboxes.forEach(cb => {
            const id = parseInt(cb.dataset.itemId, 10);
            selectedItemIdsInModal.add(id);
        });
        renderModalItems();
        updateModalSelectionUI();
    }

    function clearModalSelection() {
        selectedItemIdsInModal.clear();
        renderModalItems();
        updateModalSelectionUI();
    }

    function addSelectedModalItemsToOrder() {
        if (selectedItemIdsInModal.size === 0) return;

        const idsToAdd = Array.from(selectedItemIdsInModal);
        let idIndex = 0;

        // 1. If opened from a specific row, populate that row first
        if (currentPickerTargetRowIndex !== null) {
            const rowSelect = document.getElementById(`item-select-${currentPickerTargetRowIndex}`);
            if (rowSelect) {
                const firstId = idsToAdd[idIndex++];
                const it = itemCache.get(String(firstId));
                if (it) {
                    if (!rowSelect.querySelector(`option[value="${firstId}"]`)) {
                        const opt = new Option(`${it.name} (${it.sku || 'No SKU'}) [Stock: ${it.current_stock}]`, it.id, true, true);
                        rowSelect.add(opt);
                    }
                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery(rowSelect).val(firstId).trigger('change');
                    } else {
                        rowSelect.value = firstId;
                    }
                    handleItemChange(currentPickerTargetRowIndex);
                }
            }
        }

        // 2. For remaining items, check if there are any existing empty rows in the table to reuse
        const allRows = document.querySelectorAll('#itemRowsContainer tr.item-row');
        allRows.forEach(row => {
            if (idIndex >= idsToAdd.length) return;
            const rowIdStr = row.id.replace('row-', '');
            const rIdx = parseInt(rowIdStr, 10);
            const sel = document.getElementById(`item-select-${rIdx}`);
            if (sel && (!sel.value || sel.value === '')) {
                const nextId = idsToAdd[idIndex++];
                const it = itemCache.get(String(nextId));
                if (it) {
                    if (!sel.querySelector(`option[value="${nextId}"]`)) {
                        const opt = new Option(`${it.name} (${it.sku || 'No SKU'}) [Stock: ${it.current_stock}]`, it.id, true, true);
                        sel.add(opt);
                    }
                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery(sel).val(nextId).trigger('change');
                    } else {
                        sel.value = nextId;
                    }
                    handleItemChange(rIdx);
                }
            }
        });

        // 3. For any still remaining items, render new rows
        while (idIndex < idsToAdd.length) {
            const nextId = idsToAdd[idIndex++];
            const it = itemCache.get(String(nextId));
            const defaultCost = it ? it.cost_price : 0;
            renderItemRow({
                item_id: nextId,
                unit_cost: defaultCost,
                quantity: 1,
            });
        }

        calculateTotals();

        // Close modal
        if (itemPickerModalInstance) {
            itemPickerModalInstance.hide();
        } else if (window.jQuery) {
            jQuery('#itemPickerModal').modal('hide');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Init Select2 if loaded
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('.select2').select2({
                width: '100%'
            });
        }

        // Track user manual changes to currency
        document.getElementById('currency_id')?.addEventListener('change', (e) => {
            e.target.dataset.userChanged = 'true';
            calculateTotals();
        });

        // Dynamic rates refresh when order date changes
        document.getElementById('order_date')?.addEventListener('change', () => {
            refreshExchangeRates();
        });

        // Form submission safety guard when exchange rate is missing
        const parentForm = document.querySelector('form.admin-form-page') || document.querySelector('form');
        if (parentForm) {
            parentForm.addEventListener('submit', (e) => {
                const banner = document.getElementById('exchangeRateMissingBanner');
                if (banner && !banner.classList.contains('d-none')) {
                    e.preventDefault();
                    banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    banner.classList.add('border-danger', 'shadow');
                    setTimeout(() => banner.classList.remove('border-danger', 'shadow'), 2500);
                    alert('Cannot save purchase order: Foreign currency items require an exchange rate configured for the current order date. Please configure the exchange rate or change item currencies.');
                    return false;
                }
            });
        }

        // Render existing or initial line
        if (existingLines.length > 0) {
            existingLines.forEach(line => renderItemRow(line));
        } else {
            renderItemRow(); // At least 1 empty row
        }

        document.getElementById('addItemRowBtn')?.addEventListener('click', () => renderItemRow());

        document.getElementById('tax_amount')?.addEventListener('input', calculateTotals);
        document.getElementById('shipping_amount')?.addEventListener('input', calculateTotals);
        document.getElementById('discount_amount')?.addEventListener('input', calculateTotals);

        // Modal debounced server search
        document.getElementById('modalItemSearchInput')?.addEventListener('input', (e) => {
            clearTimeout(modalSearchTimer);
            modalSearchTimer = setTimeout(() => {
                fetchModalItems(e.target.value.trim());
            }, 300);
        });

        document.getElementById('modalMasterCheckbox')?.addEventListener('change', (e) => {
            if (e.target.checked) {
                selectAllVisibleInModal();
            } else {
                clearModalSelection();
            }
        });

        document.getElementById('modalSelectAllVisibleBtn')?.addEventListener('click', selectAllVisibleInModal);
        document.getElementById('modalClearSelectionBtn')?.addEventListener('click', clearModalSelection);
        document.getElementById('modalConfirmAddBtn')?.addEventListener('click', addSelectedModalItemsToOrder);
    });
</script>
@endpush
