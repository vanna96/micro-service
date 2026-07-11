<div class="row g-2">
    <div class="col-xl-5">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Role Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label required">Role Key</label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="catalog-manager" />
                        <div class="form-text">Use a stable slug-like key for this role.</div>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Label</label>
                        <input type="text" name="label" value="{{ old('label', $role->label) }}" class="form-control @error('label') is-invalid @enderror" placeholder="Catalog Manager" />
                        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Optional notes about what this role is for.">{{ old('description', $role->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Permissions</h2>
                </div>
            </div>
            <div class="card-body">
                @error('permissions')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                @error('permissions.*')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                @foreach ($permissionGroups as $groupName => $permissions)
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-semibold mb-3">{{ $groupName }}</div>
                        <div class="row g-2">
                            @foreach ($permissions as $permission)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="permission-{{ $permission->id }}"
                                            @checked(in_array($permission->id, old('permissions', $selectedPermissions), true))>
                                        <label class="form-check-label" for="permission-{{ $permission->id }}">
                                            {{ $permission->label }}
                                        </label>
                                    </div>
                                    <div class="text-muted font-size-12">{{ $permission->name }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
