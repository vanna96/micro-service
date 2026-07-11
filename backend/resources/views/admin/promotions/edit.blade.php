@extends('layouts.app')

@section('title', 'Edit Promotion')
@section('page_title', 'Edit Promotion')

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
    <form method="POST" action="{{ route('admin.promotions.update', ['promotion' => $promotion->id]) }}" enctype="multipart/form-data" class="admin-form-page">
        @csrf
        @method('PUT')

        <div class="alert alert-border-left alert-light mb-4" role="alert">
            <i class="mdi mdi-ticket-percent-outline me-2"></i>Editing promotion in tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @include('admin.promotions._form')

        <div class="admin-fixed-action-bar">
            <div class="container-fluid">
                <div class="d-flex justify-content-end gap-2 py-3">
                    <a href="{{ route('admin.promotions.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Promotion</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h2>Promotion Items</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-border-left alert-light mb-4" role="alert">
                        <i class="mdi mdi-information-outline me-2"></i>{{ $promotion->rule_summary }}. Promotions remain separate from price lists.
                    </div>

                    @if ($promotion->usesItemLines())
                        <form method="POST" action="{{ route('admin.promotions.lines.store', ['promotion' => $promotion->id]) }}" class="border rounded-3 p-3 mb-4">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-{{ $promotion->supportsLinePricing() ? '4' : '6' }}">
                                    <label class="form-label required">Item</label>
                                    <select name="item_id" class="form-select promotion-item-combobox @error('item_id') is-invalid @enderror">
                                        <option value="">Select item</option>
                                        @foreach ($itemOptions as $itemOption)
                                            <option value="{{ $itemOption->id }}" @selected(old('item_id') == $itemOption->id)>
                                                {{ $itemOption->name }} ({{ $itemOption->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('item_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                @if ($promotion->supportsLinePricing())
                                    <div class="col-lg-2">
                                        <label class="form-label required">Method</label>
                                        <select name="pricing_method" class="form-select pricing-method-select @error('pricing_method') is-invalid @enderror">
                                            @foreach (['fixed' => 'Fixed Price', 'discount' => 'Discount %'] as $methodValue => $methodLabel)
                                                <option value="{{ $methodValue }}" @selected(old('pricing_method', 'fixed') === $methodValue)>{{ $methodLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('pricing_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="form-label">Fixed Price</label>
                                        <input type="number" min="0" step="0.01" name="fixed_price" value="{{ old('fixed_price') }}" class="form-control fixed-price-input @error('fixed_price') is-invalid @enderror" />
                                        @error('fixed_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="form-label">Discount %</label>
                                        <input type="number" min="0" max="100" step="1" name="discount_percent" value="{{ old('discount_percent') }}" class="form-control discount-percent-input @error('discount_percent') is-invalid @enderror" />
                                        @error('discount_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @elseif ($promotion->type === \App\Models\Promotion::TYPE_BOGO)
                                    <div class="col-lg-3">
                                        <label class="form-label required">Role</label>
                                        <select name="line_role" class="form-select @error('line_role') is-invalid @enderror">
                                            <option value="{{ \App\Models\Promotion::BOGO_ROLE_BUY }}" @selected(old('line_role', \App\Models\Promotion::BOGO_ROLE_BUY) === \App\Models\Promotion::BOGO_ROLE_BUY)">Buy Item</option>
                                            <option value="{{ \App\Models\Promotion::BOGO_ROLE_GET }}" @selected(old('line_role') === \App\Models\Promotion::BOGO_ROLE_GET)">Reward Item</option>
                                        </select>
                                        @error('line_role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                                <div class="col-lg-2">
                                    <label class="form-label required">Status</label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                                        @foreach (['Active', 'Inactive'] as $status)
                                            <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-1">
                                    <button type="submit" class="btn btn-primary w-100">Add</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-info mb-4">
                            This promotion type does not use attached items.
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>{{ $promotion->supportsLinePricing() ? 'Method' : 'Role' }}</th>
                                    <th>Value</th>
                                    <th>{{ $promotion->supportsLinePricing() ? 'Preview' : 'Meaning' }}</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($promotion->lines as $line)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $line->item?->name ?: 'Deleted item' }}</div>
                                            <div class="text-muted font-size-12">{{ $line->item?->sku ?: '-' }}</div>
                                        </td>
                                        <td colspan="4">
                                            <form id="promotion-line-update-{{ $line->id }}" method="POST" action="{{ route('admin.promotions.lines.update', ['promotion' => $promotion->id, 'line' => $line->id]) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="row g-2 align-items-end">
                                                    @if ($promotion->supportsLinePricing())
                                                        <div class="col-lg-3">
                                                            <select name="pricing_method" class="form-select pricing-method-select">
                                                                <option value="fixed" @selected($line->pricing_method === 'fixed')>Fixed Price</option>
                                                                <option value="discount" @selected($line->pricing_method === 'discount')>Discount %</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <input type="number" min="0" step="0.01" name="fixed_price" value="{{ old('fixed_price.' . $line->id, $line->fixed_price) }}" class="form-control fixed-price-input" placeholder="Fixed price" />
                                                        </div>
                                                        <div class="col-lg-2">
                                                            <input type="number" min="0" max="100" step="1" name="discount_percent" value="{{ old('discount_percent.' . $line->id, $line->discount_percent) }}" class="form-control discount-percent-input" placeholder="Discount %" />
                                                        </div>
                                                    @elseif ($promotion->type === \App\Models\Promotion::TYPE_BOGO)
                                                        <div class="col-lg-3">
                                                            <select name="line_role" class="form-select">
                                                                <option value="{{ \App\Models\Promotion::BOGO_ROLE_BUY }}" @selected($line->line_role === \App\Models\Promotion::BOGO_ROLE_BUY)">Buy Item</option>
                                                                <option value="{{ \App\Models\Promotion::BOGO_ROLE_GET }}" @selected($line->line_role === \App\Models\Promotion::BOGO_ROLE_GET)">Reward Item</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <input type="text" class="form-control" value="Buy {{ (int) $promotion->buy_quantity }} Get {{ (int) $promotion->get_quantity }}" disabled />
                                                        </div>
                                                        <div class="col-lg-2">
                                                            <input type="text" class="form-control" value="{{ $line->line_role === \App\Models\Promotion::BOGO_ROLE_BUY ? 'Triggers reward' : 'Discounted item' }}" disabled />
                                                        </div>
                                                    @else
                                                        <div class="col-lg-3">
                                                            <input type="text" class="form-control" value="Eligible Item" disabled />
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <input type="text" class="form-control" value="Buy {{ (int) $promotion->buy_quantity }} Get {{ (int) $promotion->get_quantity }}" disabled />
                                                        </div>
                                                        <div class="col-lg-2">
                                                            <input type="text" class="form-control" value="Automatic" disabled />
                                                        </div>
                                                    @endif
                                                    <div class="col-lg-2">
                                                        <select name="status" class="form-select">
                                                            @foreach (['Active', 'Inactive'] as $status)
                                                                <option value="{{ $status }}" @selected($line->status === $status)>{{ $status }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-2">
                                                        @if ($promotion->supportsLinePricing())
                                                            <div class="text-muted font-size-12">
                                                                Base: ${{ number_format((float) ($line->item?->price ?? 0), 2) }}
                                                            </div>
                                                            <div class="fw-semibold">
                                                                Final: ${{ number_format($line->resolved_final_price, 2) }}
                                                            </div>
                                                        @elseif ($promotion->type === \App\Models\Promotion::TYPE_BOGO)
                                                            <div class="text-muted font-size-12">
                                                                {{ $line->line_role_label }}
                                                            </div>
                                                            <div class="fw-semibold">
                                                                {{ $line->line_role === \App\Models\Promotion::BOGO_ROLE_BUY ? 'Required in cart' : 'Free when qualified' }}
                                                            </div>
                                                        @else
                                                            <div class="fw-semibold">
                                                                {{ $line->status }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-nowrap">
                                            <div class="d-flex flex-nowrap align-items-center gap-2">
                                                <button type="submit" form="promotion-line-update-{{ $line->id }}" class="btn btn-sm btn-outline-primary">Update</button>
                                                <form method="POST" class="d-inline mb-0" action="{{ route('admin.promotions.lines.destroy', ['promotion' => $promotion->id, 'line' => $line->id]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this promotion item?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No promotion items added yet.</td>
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
            $('.promotion-item-combobox').select2({
                width: '100%',
                placeholder: 'Select item',
                allowClear: true
            });

            const applyMethodState = function (container) {
                const methodSelect = container.querySelector('.pricing-method-select');
                const fixedInput = container.querySelector('.fixed-price-input');
                const discountInput = container.querySelector('.discount-percent-input');

                if (!methodSelect || !fixedInput || !discountInput) {
                    return;
                }

                if (methodSelect.value === 'fixed') {
                    fixedInput.removeAttribute('disabled');
                    discountInput.setAttribute('disabled', 'disabled');
                    discountInput.value = '';
                } else {
                    discountInput.removeAttribute('disabled');
                    fixedInput.setAttribute('disabled', 'disabled');
                    fixedInput.value = '';
                }
            };

            document.querySelectorAll('form').forEach(function (formElement) {
                if (!formElement.querySelector('.pricing-method-select')) {
                    return;
                }

                applyMethodState(formElement);

                formElement.querySelector('.pricing-method-select').addEventListener('change', function () {
                    applyMethodState(formElement);
                });
            });
        });
    </script>
@endpush
