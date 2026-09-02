<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Variation Master</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Variation Name</label>
                <input type="text" name="name" value="{{ old('name', $variation->name) }}"
                    class="form-control @error('name') is-invalid @enderror" placeholder="Size, Color, Storage" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Foreign Name</label>
                <input type="text" name="foreign_name" value="{{ old('foreign_name', $variation->foreign_name) }}"
                    class="form-control @error('foreign_name') is-invalid @enderror" placeholder="Optional">
                @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required">Behavior</label>
                <select name="type" id="variation-type" class="form-select @error('type') is-invalid @enderror">
                    <option value="variant" @selected(old('type', $variation->type) === 'variant')>Variation</option>
                    <option value="modifier" @selected(old('type', $variation->type) === 'modifier')>Add-on / Modifier</option>
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required">Selection</label>
                <select name="selection_type" id="variation-selection" class="form-select @error('selection_type') is-invalid @enderror">
                    <option value="single" @selected(old('selection_type', $variation->selection_type) === 'single')>Single</option>
                    <option value="multiple" @selected(old('selection_type', $variation->selection_type) === 'multiple')>Multiple</option>
                </select>
                @error('selection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required">Status</label>
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach (['Active', 'Inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $variation->status ?: 'Active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Minimum Selections</label>
                <input type="number" min="0" name="min_selections" value="{{ old('min_selections', $variation->min_selections) }}"
                    class="form-control @error('min_selections') is-invalid @enderror">
                @error('min_selections')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Maximum Selections</label>
                <input type="number" min="1" name="max_selections" value="{{ old('max_selections', $variation->max_selections) }}"
                    class="form-control @error('max_selections') is-invalid @enderror">
                @error('max_selections')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="is_required" value="0">
                    <input class="form-check-input" type="checkbox" name="is_required" value="1" id="variation-required"
                        @checked(old('is_required', $variation->is_required))>
                    <label class="form-check-label" for="variation-required">Required selection</label>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('variation-type');
        const selection = document.getElementById('variation-selection');
        const required = document.getElementById('variation-required');

        const syncVariationFields = function () {
            const locked = type.value === 'variant';
            if (locked) {
                selection.value = 'single';
                required.checked = true;
            }
            required.disabled = locked;
        };

        type.addEventListener('change', syncVariationFields);
        syncVariationFields();
    });
</script>
@endpush
