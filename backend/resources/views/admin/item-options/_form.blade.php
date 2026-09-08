<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Option Details</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Variation</label>
                <select name="item_variation_id" class="form-select @error('item_variation_id') is-invalid @enderror" required>
                    <option value="">Select variation</option>
                    @foreach ($variationOptions as $variationOption)
                        <option value="{{ $variationOption->id }}" @selected((string) old('item_variation_id', $option->item_variation_id) === (string) $variationOption->id)>
                            {{ $variationOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('item_variation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label required">Option Name</label>
                <input type="text" name="name" value="{{ old('name', $option->name) }}"
                    class="form-control @error('name') is-invalid @enderror" placeholder="Red, Big, 128 GB" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Foreign Name</label>
                <input type="text" name="foreign_name" value="{{ old('foreign_name', $option->foreign_name) }}"
                    class="form-control @error('foreign_name') is-invalid @enderror" placeholder="Optional">
                @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">SKU Suffix</label>
                <input type="text" name="sku_suffix" value="{{ old('sku_suffix', $option->sku_suffix) }}"
                    class="form-control @error('sku_suffix') is-invalid @enderror" placeholder="RED or XL">
                @error('sku_suffix')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Color HEX</label>
                <input type="text" name="color_hex" value="{{ old('color_hex', $option->color_hex) }}"
                    class="form-control @error('color_hex') is-invalid @enderror" placeholder="#FF0000">
                @error('color_hex')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Price Adjustment</label>
                <input type="number" step="0.00000001" name="price_adjustment" value="{{ old('price_adjustment', $option->price_adjustment ?? 0) }}"
                    class="form-control @error('price_adjustment') is-invalid @enderror">
                @error('price_adjustment')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $option->sort_order ?? 0) }}"
                    class="form-control @error('sort_order') is-invalid @enderror">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required">Status</label>
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach (['Active', 'Inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $option->status ?: 'Active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_default" value="0">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="option-default"
                        @checked(old('is_default', $option->is_default))>
                    <label class="form-check-label" for="option-default">Default option for this variation</label>
                </div>
            </div>
        </div>
    </div>
</div>
