@once
    @push('styles')
        <style>
            .customer-profile-preview {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #f5f6f8;
            }

            .customer-profile-placeholder {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                background: #eef1ff;
                color: #5b73e8;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 2.25rem;
                font-weight: 700;
                border: 4px solid #f5f6f8;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(function () {
                const profileInput = document.getElementById('customer-profile-input');
                const profilePreview = document.getElementById('customer-profile-preview');
                const profilePlaceholder = document.getElementById('customer-profile-placeholder');

                if (profileInput && profilePreview && profilePlaceholder) {
                    profileInput.addEventListener('change', function (event) {
                        const file = event.target.files && event.target.files[0];

                        if (!file) {
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function (loadEvent) {
                            profilePreview.src = loadEvent.target.result;
                            profilePreview.classList.remove('d-none');
                            profilePlaceholder.classList.add('d-none');
                        };

                        reader.readAsDataURL(file);
                    });
                }
            });
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Customer Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $customer->code) }}" class="form-control @error('code') is-invalid @enderror" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="form-control @error('name') is-invalid @enderror" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="form-control @error('email') is-invalid @enderror" />
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="form-control @error('phone') is-invalid @enderror" />
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" value="{{ old('address', $customer->address) }}" class="form-control @error('address') is-invalid @enderror" />
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="5" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $customer->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Profile Photo</h2>
                </div>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    @if ($customer->profile_image_url)
                        <img id="customer-profile-preview" src="{{ $customer->profile_image_url }}" alt="Customer profile" class="customer-profile-preview">
                        <div id="customer-profile-placeholder" class="customer-profile-placeholder d-none">
                            {{ strtoupper(substr($customer->name ?: 'C', 0, 1)) }}
                        </div>
                    @else
                        <img id="customer-profile-preview" src="" alt="Customer profile" class="customer-profile-preview d-none">
                        <div id="customer-profile-placeholder" class="customer-profile-placeholder">
                            {{ strtoupper(substr($customer->name ?: 'C', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <input id="customer-profile-input" type="file" name="profile" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('profile') is-invalid @enderror" />
                <div class="form-text">Upload JPG, PNG, or WEBP up to 2MB.</div>
                @error('profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Status</h2>
                </div>
            </div>
            <div class="card-body">
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach (['Active', 'Inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $customer->status ?: 'Active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
