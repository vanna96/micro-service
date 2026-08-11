<input type="hidden" name="alternate_quantity" value="{{ old('alternate_quantity', $unit->alternate_quantity ?? 1) }}">
<input type="hidden" name="base_quantity" value="{{ old('base_quantity', $unit->base_quantity ?? 1) }}">
<input type="hidden" name="decimal_places" value="{{ old('decimal_places', $unit->decimal_places ?? 2) }}">
<input type="hidden" name="sort_order" value="{{ old('sort_order', $unit->sort_order ?? 0) }}">
<input type="hidden" name="is_base_unit" value="{{ old('is_base_unit', (int) (bool) $unit->is_base_unit) }}">

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Unit of Measure - Setup</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info alert-border-left" role="alert">
                    <i class="mdi mdi-information-outline me-2"></i>
                    Maintain the UoM name/code here. Conversion ratios are maintained in <strong>UOM Group → Group Definition</strong>.
                </div>

                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label required">UoM Code</label>
                        <input type="text" name="code" value="{{ old('code', $unit->code) }}" class="form-control @error('code') is-invalid @enderror" placeholder="KG" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Symbol</label>
                        <input type="text" name="symbol" value="{{ old('symbol', $unit->symbol) }}" class="form-control @error('symbol') is-invalid @enderror" placeholder="kg" />
                        @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">UoM Name</label>
                        <input type="text" name="name" value="{{ old('name', $unit->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Kilogram" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foreign Name</label>
                        <input type="text" name="foreign_name" value="{{ old('foreign_name', $unit->foreign_name) }}" class="form-control @error('foreign_name') is-invalid @enderror" />
                        @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $unit->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
