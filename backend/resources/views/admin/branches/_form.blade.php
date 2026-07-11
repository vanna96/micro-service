<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Branch Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $branch->code) }}" class="form-control @error('code') is-invalid @enderror" placeholder="kampot-flagship" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $branch->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Kampot Flagship" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Foreign Name</label>
                        <input type="text" name="foreign_name" value="{{ old('foreign_name', $branch->foreign_name) }}" class="form-control @error('foreign_name') is-invalid @enderror" />
                        @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" value="{{ old('location', $branch->location) }}" class="form-control @error('location') is-invalid @enderror" placeholder="Kampot City" />
                        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" step="1" name="sort_order" value="{{ old('sort_order', $branch->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $branch->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
