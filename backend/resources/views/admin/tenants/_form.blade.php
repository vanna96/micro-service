<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Tenant Configuration</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @if (! $tenant->exists)
                        <div class="col-md-6">
                            <label class="form-label required">Tenant ID</label>
                            <input type="text" name="id" value="{{ old('id', $tenant->id) }}" class="form-control @error('id') is-invalid @enderror" />
                            <div class="form-text">This becomes the tenant subdomain prefix.</div>
                            @error('id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="col-md-6">
                            <label class="form-label">Tenant ID</label>
                            <input type="text" value="{{ $tenant->id }}" class="form-control" disabled />
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label required">Database Connection</label>
                        <input type="text" name="db_connection" value="{{ old('db_connection', $tenant->db_connection ?: 'mysql') }}" class="form-control @error('db_connection') is-invalid @enderror" />
                        @error('db_connection')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Database Name</label>
                        <input type="text" name="db_name" value="{{ old('db_name', $tenant->db_name) }}" class="form-control @error('db_name') is-invalid @enderror" />
                        @error('db_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Database Host</label>
                        <input type="text" name="db_host" value="{{ old('db_host', $tenant->db_host ?: '127.0.0.1') }}" class="form-control @error('db_host') is-invalid @enderror" />
                        @error('db_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Database Port</label>
                        <input type="text" name="db_port" value="{{ old('db_port', $tenant->db_port ?: '3306') }}" class="form-control @error('db_port') is-invalid @enderror" />
                        @error('db_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Database Username</label>
                        <input type="text" name="db_username" value="{{ old('db_username', $tenant->db_username) }}" class="form-control @error('db_username') is-invalid @enderror" />
                        @error('db_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Database Password</label>
                        <input type="text" name="db_password" value="{{ old('db_password', $tenant->db_password) }}" class="form-control @error('db_password') is-invalid @enderror" />
                        @error('db_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Publishing</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-8">
                    <label class="form-label required">Status</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach (['Active', 'Inactive'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $tenant->status ?: 'Active') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if ($tenant->exists)
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6">
                        <div class="d-flex flex-stack flex-grow-1">
                            <div class="fw-semibold">
                                <div class="fs-6 text-gray-700">Domain</div>
                                <div class="fw-bold text-gray-900">{{ optional($tenant->domains->first())->domain ?: ($tenant->id . '.' . env('TENANT_HOST', 'localhost')) }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
