@once
    @push('styles')
        <link href="{{ global_asset('minible/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
        <style>
            .profile-upload-preview {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #f5f6f8;
            }

            .profile-upload-placeholder {
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

    @push('scripts')
        <script src="{{ global_asset('minible/assets/libs/select2/js/select2.min.js') }}"></script>
        <script>
            $(function () {
                $('.country-code-combobox').select2({
                    width: '100%',
                    placeholder: 'Select country code',
                    allowClear: true
                });

                const profileInput = document.getElementById('profile-upload-input');
                const profilePreview = document.getElementById('profile-upload-preview');
                const profilePlaceholder = document.getElementById('profile-upload-placeholder');

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

@php
    $showTenantAssignments = $showTenantAssignments ?? true;
    $roleOptions = $roleOptions ?? collect();
    $selectedRoles = $selectedRoles ?? [];
    $profileImageUrl = $user->profile_id ? $user->profile_image_url : null;
@endphp

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>User Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-control @error('username') is-invalid @enderror" />
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" />
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" />
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="form-control @error('first_name') is-invalid @enderror" />
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="form-control @error('last_name') is-invalid @enderror" />
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        @php
                            $selectedCountryCode = (string) old('country_code', $user->country_code ?: '855');
                            $countryCodes = country_code();
                        @endphp
                        <label class="form-label">Country Code</label>
                        <select
                            name="country_code"
                            class="form-select country-code-combobox @error('country_code') is-invalid @enderror"
                            data-placeholder="Select country code"
                        >
                            <option value="">Select country code</option>
                            @if ($selectedCountryCode !== '' && ! array_key_exists($selectedCountryCode, $countryCodes))
                                <option value="{{ $selectedCountryCode }}" selected>+{{ $selectedCountryCode }}</option>
                            @endif
                            @foreach ($countryCodes as $code => $country)
                                <option value="{{ $code }}" @selected($selectedCountryCode === (string) $code)>
                                    +{{ $code }} ({{ $country }})
                                </option>
                            @endforeach
                        </select>
                        @error('country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                            <option value="">Select gender</option>
                            @foreach (['Male', 'Female'] as $gender)
                                <option value="{{ $gender }}" @selected(old('gender', $user->gender) === $gender)>{{ $gender }}</option>
                            @endforeach
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" value="{{ old('dob', optional($user->dob)->format('Y-m-d') ?: $user->dob) }}" class="form-control @error('dob') is-invalid @enderror" />
                        @error('dob')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Security</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label {{ $user->exists ? '' : 'required' }}">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" />
                        @if ($user->exists)
                            <div class="form-text">Leave blank to keep the current password.</div>
                        @endif
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label {{ $user->exists ? '' : 'required' }}">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" />
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
                    @if ($profileImageUrl)
                        <img id="profile-upload-preview" src="{{ $profileImageUrl }}" alt="Profile photo" class="profile-upload-preview">
                        <div id="profile-upload-placeholder" class="profile-upload-placeholder d-none">
                            {{ strtoupper(substr($user->name ?: $user->username ?: 'U', 0, 1)) }}
                        </div>
                    @else
                        <img id="profile-upload-preview" src="" alt="Profile photo" class="profile-upload-preview d-none">
                        <div id="profile-upload-placeholder" class="profile-upload-placeholder">
                            {{ strtoupper(substr($user->name ?: $user->username ?: 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <input id="profile-upload-input" type="file" name="profile" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('profile') is-invalid @enderror" />
                <div class="form-text">Upload JPG, PNG, or WEBP up to 2MB.</div>
                @error('profile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Status</h2>
                </div>
            </div>
            <div class="card-body">
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach (['Active', 'Inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status ?: 'Active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        @if ($showTenantAssignments)
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h2>Tenants</h2>
                    </div>
                </div>
                <div class="card-body">
                    <select name="tenants[]" class="form-select @error('tenants') is-invalid @enderror" multiple size="8">
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(in_array($tenant->id, old('tenants', $selectedTenants), true))>
                                {{ $tenant->id }} ({{ $tenant->db_name }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Hold Ctrl or Cmd to select multiple tenants.</div>
                    @error('tenants')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('tenants.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        @elseif ($roleOptions->isNotEmpty())
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h2>Roles</h2>
                    </div>
                </div>
                <div class="card-body">
                    <select name="roles[]" class="form-select @error('roles') is-invalid @enderror" multiple size="8">
                        @foreach ($roleOptions as $roleOption)
                            <option value="{{ $roleOption->id }}" @selected(in_array($roleOption->id, old('roles', $selectedRoles), true))>
                                {{ $roleOption->label }} ({{ $roleOption->name }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Assign at least one role to define what this tenant user can access.</div>
                    @error('roles')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('roles.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        @endif
    </div>
</div>
