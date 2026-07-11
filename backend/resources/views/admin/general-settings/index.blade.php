@extends('layouts.app')

@section('title', 'General')
@section('page_title', 'General')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="alert alert-border-left alert-light mb-4" role="alert">
            <i class="mdi mdi-cog-outline me-2"></i>Managing general settings for tenant
            <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.general-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
                        <div>
                            <span class="badge bg-soft-primary text-primary mb-3">Settings</span>
                            <h4 class="mb-2">General tenant preferences</h4>
                            <p class="text-muted mb-0">
                                Keep the store profile and operational defaults in one place for this tenant.
                            </p>
                        </div>
                        <span class="badge bg-soft-success text-success">Live</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Store Name</label>
                            <input type="text" name="store_name"
                                value="{{ old('store_name', $generalSettings['store_name']) }}"
                                class="form-control @error('store_name') is-invalid @enderror">
                            @error('store_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email"
                                value="{{ old('contact_email', $generalSettings['contact_email']) }}"
                                class="form-control @error('contact_email') is-invalid @enderror">
                            @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="contact_phone"
                                value="{{ old('contact_phone', $generalSettings['contact_phone']) }}"
                                class="form-control @error('contact_phone') is-invalid @enderror">
                            @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Currency</label>
                            <select name="currency" class="form-select @error('currency') is-invalid @enderror" @disabled(! $hasCurrencies)>
                                <option value="">{{ $hasCurrencies ? 'Select currency' : 'Create currency first' }}</option>
                                @foreach ($currencyOptions as $currencyOption)
                                    <option value="{{ $currencyOption->code }}"
                                        @selected(old('currency', $generalSettings['currency']) === $currencyOption->code)>
                                        {{ $currencyOption->code }} - {{ $currencyOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if (! $hasCurrencies)
                                <div class="form-text">
                                    Create an active currency in <a href="{{ route('admin.currencies.index') }}">Currency</a>
                                    before choosing a base currency.
                                </div>
                            @endif
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Timezone</label>
                            <select name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}"
                                        @selected(old('timezone', $generalSettings['timezone']) === $timezone)>
                                        {{ $timezone }}
                                    </option>
                                @endforeach
                            </select>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Locale</label>
                            <input type="text" name="locale"
                                value="{{ old('locale', $generalSettings['locale']) }}"
                                class="form-control @error('locale') is-invalid @enderror"
                                placeholder="en">
                            @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $generalSettings['address']) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Receipt Footer</label>
                            <textarea name="receipt_footer" rows="3" class="form-control @error('receipt_footer') is-invalid @enderror">{{ old('receipt_footer', $generalSettings['receipt_footer']) }}</textarea>
                            <div class="form-text">Use this for a thank-you note or a short policy line.</div>
                            @error('receipt_footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="mb-3">What this controls</h5>
                    <div class="list-group list-group-flush mb-4">
                        <div class="list-group-item px-0">
                            <div class="fw-semibold">Store profile</div>
                            <div class="text-muted small">Business name, phone, email, and address details.</div>
                        </div>
                        <div class="list-group-item px-0">
                            <div class="fw-semibold">Operational defaults</div>
                            <div class="text-muted small">Base currency, timezone, and locale for the tenant.</div>
                        </div>
                        <div class="list-group-item px-0 pb-0">
                            <div class="fw-semibold">Receipt messaging</div>
                            <div class="text-muted small">A short footer message for customer-facing printouts.</div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="uil-save me-1"></i>Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
