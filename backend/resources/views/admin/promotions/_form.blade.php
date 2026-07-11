@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const typeSelect = document.getElementById('promotion-type');
                const imageInput = document.getElementById('promotion-image-input');
                const previewWrapper = document.getElementById('promotion-image-preview-wrapper');
                const previewImage = document.getElementById('promotion-image-preview');
                const previewEmpty = document.getElementById('promotion-image-preview-empty');
                let previewObjectUrl = null;

                if (!typeSelect) {
                    return;
                }

                const updateTypeState = function () {
                    const selectedType = typeSelect.value;

                    document.querySelectorAll('[data-promotion-type-group]').forEach(function (element) {
                        const allowedTypes = (element.dataset.promotionTypeGroup || '').split(',');
                        const isVisible = allowedTypes.includes(selectedType);

                        element.classList.toggle('d-none', !isVisible);

                        element.querySelectorAll('input, select, textarea').forEach(function (field) {
                            if (field.dataset.keepEnabled === 'true') {
                                return;
                            }

                            if (isVisible) {
                                field.removeAttribute('disabled');
                            } else {
                                field.setAttribute('disabled', 'disabled');
                            }
                        });
                    });
                };

                updateTypeState();
                typeSelect.addEventListener('change', updateTypeState);

                if (!imageInput || !previewWrapper || !previewImage || !previewEmpty) {
                    return;
                }

                imageInput.addEventListener('change', function (event) {
                    const selectedFile = event.target.files && event.target.files[0] ? event.target.files[0] : null;

                    if (previewObjectUrl) {
                        URL.revokeObjectURL(previewObjectUrl);
                        previewObjectUrl = null;
                    }

                    if (!selectedFile) {
                        previewImage.setAttribute('src', '');
                        previewImage.classList.add('d-none');
                        previewEmpty.classList.remove('d-none');
                        previewWrapper.classList.add('d-none');

                        return;
                    }

                    previewObjectUrl = URL.createObjectURL(selectedFile);
                    previewImage.setAttribute('src', previewObjectUrl);
                    previewImage.classList.remove('d-none');
                    previewEmpty.classList.add('d-none');
                    previewWrapper.classList.remove('d-none');
                });
            });
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Promotion Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $promotion->code) }}" class="form-control @error('code') is-invalid @enderror" placeholder="new-year-2026" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $promotion->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="New Year 2026" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Type</label>
                        <select id="promotion-type" name="type" class="form-select @error('type') is-invalid @enderror" data-keep-enabled="true">
                            @foreach ([
                                \App\Models\Promotion::TYPE_ITEM_PRICE => 'Item Price',
                                \App\Models\Promotion::TYPE_SUBTOTAL_DISCOUNT => 'Subtotal Discount',
                                \App\Models\Promotion::TYPE_BOGO => 'Buy X Get Y',
                            ] as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected(old('type', $promotion->type ?: \App\Models\Promotion::TYPE_ITEM_PRICE) === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" data-keep-enabled="true">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $promotion->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Start At</label>
                        <input type="datetime-local" name="start_at" value="{{ old('start_at', optional($promotion->start_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('start_at') is-invalid @enderror" data-keep-enabled="true" />
                        @error('start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">End At</label>
                        <input type="datetime-local" name="end_at" value="{{ old('end_at', optional($promotion->end_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('end_at') is-invalid @enderror" data-keep-enabled="true" />
                        @error('end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_SUBTOTAL_DISCOUNT }}">
                        <label class="form-label required">Threshold Amount</label>
                        <input type="number" min="0.01" step="0.01" name="threshold_amount" value="{{ old('threshold_amount', $promotion->threshold_amount) }}" class="form-control @error('threshold_amount') is-invalid @enderror" placeholder="50.00" />
                        @error('threshold_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_SUBTOTAL_DISCOUNT }}">
                        <label class="form-label required">Reward Discount %</label>
                        <input type="number" min="1" max="100" step="1" name="reward_discount_percent" value="{{ old('reward_discount_percent', $promotion->reward_discount_percent) }}" class="form-control @error('reward_discount_percent') is-invalid @enderror" placeholder="10" />
                        @error('reward_discount_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_BOGO }}">
                        <label class="form-label required">Buy Quantity</label>
                        <input type="number" min="1" step="1" name="buy_quantity" value="{{ old('buy_quantity', $promotion->buy_quantity) }}" class="form-control @error('buy_quantity') is-invalid @enderror" placeholder="1" />
                        @error('buy_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_BOGO }}">
                        <label class="form-label required">Get Quantity</label>
                        <input type="number" min="1" step="1" name="get_quantity" value="{{ old('get_quantity', $promotion->get_quantity) }}" class="form-control @error('get_quantity') is-invalid @enderror" placeholder="1" />
                        @error('get_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Optional internal notes for this promotion." data-keep-enabled="true">{{ old('description', $promotion->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_ITEM_PRICE }}">
                        <div class="form-text">
                            {{ $promotion->exists
                                ? 'Item Price promotions apply fixed-price or percentage discounts to the specific items attached below.'
                                : 'Save this promotion first, then attach the specific items and pricing rules on the next screen.' }}
                        </div>
                    </div>
                    <div class="col-12 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_SUBTOTAL_DISCOUNT }}">
                        <div class="form-text">
                            Subtotal Discount promotions apply automatically when the cart subtotal reaches the configured threshold.
                        </div>
                    </div>
                    <div class="col-12 d-none" data-promotion-type-group="{{ \App\Models\Promotion::TYPE_BOGO }}">
                        <div class="form-text">
                            {{ $promotion->exists
                                ? 'Buy X Get Y promotions can reward the same item or a different item. Attach one buy item and one reward item below.'
                                : 'Save this promotion first, then choose the buy item and the reward item on the next screen.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Promotion Image</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Image</label>
                    <input id="promotion-image-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" />
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div id="promotion-image-preview-wrapper" class="border rounded overflow-hidden mb-3 d-none">
                    <img id="promotion-image-preview" src="" alt="Selected promotion preview" class="img-fluid w-100 d-none">
                    <div id="promotion-image-preview-empty" class="p-3 text-muted d-none">No image selected.</div>
                </div>
                @if ($promotion->image_url)
                    <div class="border rounded overflow-hidden mb-3">
                        <img src="{{ $promotion->image_url }}" alt="{{ $promotion->name }}" class="img-fluid w-100">
                    </div>
                @endif
                <div class="form-text">
                    Upload one image to visually represent this promotion in tenant-facing surfaces.
                </div>
            </div>
        </div>
    </div>
</div>
