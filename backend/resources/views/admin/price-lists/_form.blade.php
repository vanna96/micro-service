@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const applyHeaderPricingState = function () {
                    const methodSelect = document.getElementById('price-list-header-pricing-method');
                    const fixedInput = document.getElementById('price-list-header-fixed-price');
                    const discountInput = document.getElementById('price-list-header-discount-percent');
                    const canUseFixed = methodSelect && methodSelect.dataset.baseCurrencyEnabled === '1';

                    if (!methodSelect || !fixedInput || !discountInput) {
                        return;
                    }

                    if (methodSelect.value === 'fixed' && canUseFixed) {
                        discountInput.value = '';
                        fixedInput.removeAttribute('disabled');
                        discountInput.setAttribute('disabled', 'disabled');
                    } else if (methodSelect.value === 'fixed') {
                        fixedInput.setAttribute('disabled', 'disabled');
                        discountInput.setAttribute('disabled', 'disabled');
                    } else if (methodSelect.value === 'discount') {
                        fixedInput.value = '';
                        discountInput.removeAttribute('disabled');
                        fixedInput.setAttribute('disabled', 'disabled');
                    } else {
                        fixedInput.value = '';
                        discountInput.value = '';
                        fixedInput.setAttribute('disabled', 'disabled');
                        discountInput.setAttribute('disabled', 'disabled');
                    }
                };

                const methodSelect = document.getElementById('price-list-header-pricing-method');

                if (!methodSelect) {
                    return;
                }

                applyHeaderPricingState();
                methodSelect.addEventListener('change', applyHeaderPricingState);
            });
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Price List Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $priceList->code) }}" class="form-control @error('code') is-invalid @enderror" placeholder="retail-2026" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $priceList->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Retail 2026" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $priceList->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Optional internal notes for this price list.">{{ old('description', $priceList->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Header Pricing Method</label>
                        <select
                            id="price-list-header-pricing-method"
                            name="header_pricing_method"
                            data-base-currency-enabled="{{ $baseCurrency ? '1' : '0' }}"
                            class="form-select @error('header_pricing_method') is-invalid @enderror"
                        >
                            <option value="">No header rule</option>
                            <option value="fixed" @selected(old('header_pricing_method', $priceList->header_pricing_method) === 'fixed') @disabled(! $baseCurrency)>
                                Fixed Price{{ $baseCurrency ? ' (' . $baseCurrency->code . ')' : '' }}
                            </option>
                            <option value="discount" @selected(old('header_pricing_method', $priceList->header_pricing_method) === 'discount')>Discount %</option>
                        </select>
                        @error('header_pricing_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Header Fixed Price{{ $baseCurrency ? ' (' . $baseCurrency->code . ')' : '' }}</label>
                        <input id="price-list-header-fixed-price" type="number" min="0" step="{{ currency_input_step($baseCurrency, 2) }}" name="header_fixed_price" value="{{ format_currency_input(old('header_fixed_price', $priceList->header_fixed_price), $baseCurrency, 2) }}" class="form-control @error('header_fixed_price') is-invalid @enderror" placeholder="{{ $baseCurrency?->format_example ?: 'Set base currency first' }}" />
                        <div class="form-text">
                            @if ($baseCurrency)
                                Header fixed price uses the tenant base currency {{ $baseCurrency->code }}.
                            @else
                                Set a base currency in <a href="{{ route('admin.general-settings.index') }}">General</a> before using header fixed price.
                            @endif
                        </div>
                        @error('header_fixed_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Header Discount %</label>
                        <input id="price-list-header-discount-percent" type="number" min="0" max="100" step="1" name="header_discount_percent" value="{{ old('header_discount_percent', $priceList->header_discount_percent) }}" class="form-control @error('header_discount_percent') is-invalid @enderror" placeholder="Applies to all items" />
                        @error('header_discount_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-text">
                            Header pricing applies to all items in this price list. Any line item you add below will override the header rule for that specific item.
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="price-list-is-default" @checked(old('is_default', $priceList->is_default))>
                            <label class="form-check-label" for="price-list-is-default">
                                Make this the default active price list for the tenant
                            </label>
                        </div>
                        <div class="form-text">Only one price list can be default at a time. Inactive lists cannot stay default.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
