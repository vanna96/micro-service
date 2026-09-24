@extends('layouts.app')

@section('title', 'Edit Price List')
@section('page_title', 'Edit Price List')

@push('styles')
    <link href="{{ global_asset('minible/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .admin-form-page {
            padding-bottom: 110px;
        }

        .admin-fixed-action-bar {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1055;
            background: #ffffff;
            border-top: 1px solid #e9e9ef;
            box-shadow: 0 -4px 18px rgba(39, 48, 78, 0.08);
        }

        .select2-container .select2-selection--single {
            height: calc(1.5em + 0.94rem + 2px);
            border: 1px solid #ced4da;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + 0.94rem);
            padding-left: 0.75rem;
            padding-right: 2rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.94rem + 2px);
            right: 0.5rem;
        }

        .select2-container {
            width: 100% !important;
        }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.price-lists.update', ['price_list' => $priceList->id]) }}"
        class="admin-form-page">
        @csrf
        @method('PUT')

        <div class="alert alert-border-left alert-light mb-4" role="alert">
            <i class="mdi mdi-database me-2"></i>Editing price list in tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @include('admin.price-lists._form')

        <div class="admin-fixed-action-bar">
            <div class="container-fluid">
                <div class="d-flex justify-content-end gap-2 py-3">
                    <a href="{{ route('admin.price-lists.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Price List</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h2>Price List Lines</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-border-left alert-light mb-4" role="alert">
                        <i class="mdi mdi-information-outline me-2"></i>Header pricing applies to all items in this price list.
                        Add only the line items that should override the header rule.
                        @if ($baseCurrency)
                            Header fixed price uses <strong>{{ $baseCurrency->code }}</strong> as the tenant base currency.
                        @endif
                    </div>

                    <form method="POST"
                        action="{{ route('admin.price-lists.lines.store', ['price_list' => $priceList->id]) }}"
                        id="price-list-line-create-form"
                        class="border rounded-3 p-3 mb-4">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label required">Item</label>
                                <select name="item_id"
                                    class="form-select price-list-item-combobox @error('item_id') is-invalid @enderror">
                                    <option value="">Select item</option>
                                    @foreach ($itemOptions as $itemOption)
                                        <option
                                            value="{{ $itemOption->id }}"
                                            data-has-currency="{{ $itemOption->currency ? '1' : '0' }}"
                                            data-currency-code="{{ $itemOption->currency?->code }}"
                                            data-input-step="{{ currency_input_step($itemOption->currency, 2) }}"
                                            data-decimal-places="{{ $itemOption->currency?->decimal_places }}"
                                            data-format-example="{{ $itemOption->currency?->format_example ?? '2.22' }}"
                                            @selected(old('item_id') == $itemOption->id)
                                        >
                                            {{ $itemOption->name }} ({{ $itemOption->sku }}){{ $itemOption->currency ? ' · ' . $itemOption->currency->code : ' · No currency' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('item_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label required">Method</label>
                                <select name="pricing_method"
                                    class="form-select pricing-method-select @error('pricing_method') is-invalid @enderror">
                                    @foreach (['fixed' => 'Fixed Price', 'discount' => 'Discount %'] as $methodValue => $methodLabel)
                                        <option value="{{ $methodValue }}" @selected(old('pricing_method', 'fixed') === $methodValue)>{{ $methodLabel }}</option>
                                    @endforeach
                                </select>
                                @error('pricing_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">Fixed Price</label>
                                <input type="number" min="0" step="0.01" name="fixed_price" value="{{ format_currency_input(old('fixed_price'), null, 2) }}"
                                    class="form-control fixed-price-input @error('fixed_price') is-invalid @enderror" />
                                @error('fixed_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">Discount %</label>
                                <input type="number" min="0" max="100" step="1" name="discount_percent"
                                    value="{{ old('discount_percent') }}"
                                    class="form-control discount-percent-input @error('discount_percent') is-invalid @enderror" />
                                @error('discount_percent')<div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-lg-1">
                                <label class="form-label required">Status</label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror">
                                    @foreach (['Active', 'Inactive'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-1">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                            <div class="col-12 d-none fixed-price-feedback-row">
                                <div class="form-text fixed-price-feedback mb-0"></div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Method</th>
                                    <th>Value</th>
                                    <th>Preview</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($priceList->lines as $line)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $line->item?->name ?: 'Deleted item' }}</div>
                                            <div class="text-muted font-size-12">
                                                {{ $line->item?->sku ?: '-' }}{{ $line->item?->currency ? ' · ' . $line->item->currency->code : ' · No currency' }}
                                            </div>
                                        </td>
                                        <td colspan="4">
                                            <form id="price-list-line-update-{{ $line->id }}" method="POST"
                                                action="{{ route('admin.price-lists.lines.update', ['price_list' => $priceList->id, 'line' => $line->id]) }}"
                                                data-has-fixed-currency="{{ $line->item?->currency ? '1' : '0' }}"
                                                data-currency-code="{{ $line->item?->currency?->code }}"
                                                data-currency-step="{{ currency_input_step($line->item?->currency, 2) }}"
                                                data-decimal-places="{{ $line->item?->currency?->decimal_places }}"
                                                data-format-example="{{ $line->item?->currency?->format_example ?? '2.22' }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-lg-3">
                                                        <select name="pricing_method" class="form-select pricing-method-select">
                                                            <option value="fixed" @selected($line->pricing_method === 'fixed')>
                                                                Fixed Price</option>
                                                            <option value="discount"
                                                                @selected($line->pricing_method === 'discount')>Discount %
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <input type="number" min="0" step="{{ currency_input_step($line->item?->currency, 2) }}" name="fixed_price"
                                                            value="{{ format_currency_input(old('fixed_price.' . $line->id, $line->fixed_price), $line->item?->currency, 2) }}"
                                                            class="form-control fixed-price-input" placeholder="Fixed price" />
                                                        <div class="form-text fixed-price-help">
                                                            @if ($line->item?->currency)
                                                                {{ $line->item->currency->code }} fixed prices allow up to {{ $line->item->currency->decimal_places }} decimal place(s). Example: {{ $line->item->currency->format_example }}.
                                                            @else
                                                                Assign an active currency to the linked item first.
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-2">
                                                        <input type="number" min="0" max="100" step="1" name="discount_percent"
                                                            value="{{ old('discount_percent.' . $line->id, $line->discount_percent) }}"
                                                            class="form-control discount-percent-input"
                                                            placeholder="Discount %" />
                                                    </div>
                                                    <div class="col-lg-2">
                                                        <select name="status" class="form-select">
                                                            @foreach (['Active', 'Inactive'] as $status)
                                                                <option value="{{ $status }}" @selected($line->status === $status)>
                                                                    {{ $status }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-2">
                                                        <div class="text-muted font-size-12">
                                                            Base: {{ format_currency_amount($line->item?->price ?? 0, $line->item?->currency) }}
                                                        </div>
                                                        <div class="fw-semibold">
                                                            Final: {{ format_currency_amount($line->resolved_final_price, $line->item?->currency) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-nowrap">
                                            <div class="d-flex flex-nowrap align-items-center gap-2">
                                                <button type="submit" form="price-list-line-update-{{ $line->id }}"
                                                    class="btn btn-sm btn-outline-primary">Update</button>
                                                <form method="POST" class="d-inline mb-0"
                                                    action="{{ route('admin.price-lists.lines.destroy', ['price_list' => $priceList->id, 'line' => $line->id]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Remove this price list line?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No line items added yet.</td>
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

@push('scripts')
    <script src="{{ global_asset('minible/assets/libs/select2/js/select2.min.js') }}"></script>
    <script>
        $(function () {
            $('.price-list-item-combobox').select2({
                width: '100%',
                placeholder: 'Select item',
                allowClear: true
            });

            const applyMethodState = function (container) {
                const methodSelect = container.querySelector('.pricing-method-select');
                const fixedInput = container.querySelector('.fixed-price-input');
                const discountInput = container.querySelector('.discount-percent-input');
                const fixedPriceHelp = container.querySelector('.fixed-price-help');
                const fixedPriceFeedback = container.querySelector('.fixed-price-feedback');
                const fixedPriceFeedbackRow = container.querySelector('.fixed-price-feedback-row');
                const itemSelect = container.querySelector('.price-list-item-combobox');

                let canUseFixed = container.dataset.hasFixedCurrency !== '0';
                let hasSelectedItem = !itemSelect;
                let currencyCode = container.dataset.currencyCode || '';
                let inputStep = container.dataset.currencyStep || '0.01';
                let decimalPlaces = container.dataset.decimalPlaces || '';
                let formatExample = container.dataset.formatExample || '2.22';

                if (!methodSelect || !fixedInput || !discountInput) {
                    return;
                }

                if (itemSelect) {
                    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                    hasSelectedItem = !!selectedOption?.value;
                    canUseFixed = selectedOption?.dataset.hasCurrency === '1';
                    currencyCode = selectedOption?.dataset.currencyCode || '';
                    inputStep = selectedOption?.dataset.inputStep || '0.01';
                    decimalPlaces = selectedOption?.dataset.decimalPlaces || '';
                    formatExample = selectedOption?.dataset.formatExample || '2.22';
                }

                fixedInput.setAttribute('step', inputStep);
                fixedInput.setAttribute(
                    'placeholder',
                    !hasSelectedItem ? 'Select item first' : (canUseFixed ? formatExample : 'Assign currency to item first')
                );

                if (fixedPriceHelp) {
                    fixedPriceHelp.textContent = !hasSelectedItem
                        ? 'Select an item to load its currency format.'
                        : (canUseFixed
                            ? `${currencyCode} fixed prices allow up to ${decimalPlaces} decimal place(s). Example: ${formatExample}.`
                            : 'Assign an active currency to the selected item first.');
                }

                if (fixedPriceFeedback && fixedPriceFeedbackRow) {
                    if (methodSelect.value === 'fixed' && !hasSelectedItem) {
                        fixedPriceFeedback.textContent = 'Select an item first to enter a fixed price.';
                        fixedPriceFeedback.classList.remove('text-danger');
                        fixedPriceFeedback.classList.add('text-muted');
                        fixedPriceFeedbackRow.classList.remove('d-none');
                    } else if (methodSelect.value === 'fixed' && !canUseFixed) {
                        fixedPriceFeedback.textContent = 'Assign an active currency to the selected item first.';
                        fixedPriceFeedback.classList.add('text-danger');
                        fixedPriceFeedback.classList.remove('text-muted');
                        fixedPriceFeedbackRow.classList.remove('d-none');
                    } else {
                        fixedPriceFeedback.textContent = '';
                        fixedPriceFeedback.classList.remove('text-danger');
                        fixedPriceFeedback.classList.add('text-muted');
                        fixedPriceFeedbackRow.classList.add('d-none');
                    }
                }

                if (methodSelect.value === 'fixed' && hasSelectedItem && canUseFixed) {
                    fixedInput.removeAttribute('disabled');
                    discountInput.setAttribute('disabled', 'disabled');
                } else if (methodSelect.value === 'fixed') {
                    fixedInput.setAttribute('disabled', 'disabled');
                    discountInput.setAttribute('disabled', 'disabled');
                } else {
                    discountInput.removeAttribute('disabled');
                    fixedInput.setAttribute('disabled', 'disabled');
                }
            };

            document.querySelectorAll('form').forEach(function (form) {
                if (!form.querySelector('.pricing-method-select')) {
                    return;
                }

                applyMethodState(form);

                form.querySelector('.pricing-method-select').addEventListener('change', function () {
                    applyMethodState(form);
                });

                const itemSelect = form.querySelector('.price-list-item-combobox');

                if (itemSelect) {
                    itemSelect.addEventListener('change', function () {
                        applyMethodState(form);
                    });

                    $(itemSelect).on('select2:select select2:clear', function () {
                        applyMethodState(form);
                    });
                }
            });
        });
    </script>
@endpush
