<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Currency Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $currency->code) }}" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="USD" maxlength="3" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $currency->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="US Dollar" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Symbol</label>
                        <input type="text" name="symbol" value="{{ old('symbol', $currency->symbol) }}" class="form-control @error('symbol') is-invalid @enderror" placeholder="$" />
                        @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Decimal Places</label>
                        <input type="number" min="0" max="8" step="1" name="decimal_places" value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}" class="form-control @error('decimal_places') is-invalid @enderror" />
                        <div class="form-text">Example: USD = 2, KHR = 0.</div>
                        @error('decimal_places')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" step="1" name="sort_order" value="{{ old('sort_order', $currency->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $currency->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
