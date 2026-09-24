@php
    $configurationFields = [
        'alias',
        'id',
        'db_connection',
        'db_name',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
    ];
    $hasConfigurationErrors = collect($configurationFields)->contains(fn ($field) => $errors->has($field));
    $hasTelegramErrors = $errors->has('general_settings.telegram_bot_token') || $errors->has('general_settings.telegram_chat_id');
    $activeTenantTab = $hasTelegramErrors ? 'telegram' : ($hasConfigurationErrors ? 'configuration' : 'general');
    $savedTenantDomain = $tenant->exists ? optional($tenant->domains->first())->domain : null;
@endphp

<div class="card card-flush">
    <div class="card-header border-bottom align-items-end">
        <ul class="nav nav-tabs nav-tabs-custom border-bottom-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button"
                    class="nav-link {{ $activeTenantTab === 'general' ? 'active' : '' }}"
                    id="tenant-general-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#tenant-general-pane"
                    role="tab"
                    aria-controls="tenant-general-pane"
                    aria-selected="{{ $activeTenantTab === 'general' ? 'true' : 'false' }}">
                    <i class="uil uil-setting me-1"></i>{{ __('General Setting') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button"
                    class="nav-link {{ $activeTenantTab === 'telegram' ? 'active' : '' }}"
                    id="tenant-telegram-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#tenant-telegram-pane"
                    role="tab"
                    aria-controls="tenant-telegram-pane"
                    aria-selected="{{ $activeTenantTab === 'telegram' ? 'true' : 'false' }}">
                    <i class="uil uil-telegram-alt me-1"></i>{{ __('Telegram Notifications') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button"
                    class="nav-link {{ $activeTenantTab === 'configuration' ? 'active' : '' }}"
                    id="tenant-configuration-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#tenant-configuration-pane"
                    role="tab"
                    aria-controls="tenant-configuration-pane"
                    aria-selected="{{ $activeTenantTab === 'configuration' ? 'true' : 'false' }}">
                    <i class="uil uil-database me-1"></i>{{ __('Configuration') }}
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade {{ $activeTenantTab === 'general' ? 'show active' : '' }}"
                id="tenant-general-pane"
                role="tabpanel"
                aria-labelledby="tenant-general-tab"
                tabindex="0">
                <div class="mb-4">
                    <h2 class="mb-1">{{ __('General Setting') }}</h2>
                    <p class="text-muted mb-0">{{ __('Manage the store profile, operational defaults, and public access information.') }}</p>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Store Name') }}</label>
                        <input type="text" name="general_settings[store_name]"
                            value="{{ old('general_settings.store_name', $generalSettings['store_name']) }}"
                            class="form-control @error('general_settings.store_name') is-invalid @enderror">
                        @error('general_settings.store_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Status') }}</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $tenant->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('Inactive companies cannot be used until they are activated again.') }}</div>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Contact Email') }}</label>
                        <input type="email" name="general_settings[contact_email]"
                            value="{{ old('general_settings.contact_email', $generalSettings['contact_email']) }}"
                            class="form-control @error('general_settings.contact_email') is-invalid @enderror">
                        @error('general_settings.contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Contact Phone') }}</label>
                        <input type="text" name="general_settings[contact_phone]"
                            value="{{ old('general_settings.contact_phone', $generalSettings['contact_phone']) }}"
                            class="form-control @error('general_settings.contact_phone') is-invalid @enderror">
                        @error('general_settings.contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label {{ $hasCurrencies ? 'required' : '' }}">{{ __('Currency') }}</label>
                        <select name="general_settings[currency]"
                            class="form-select @error('general_settings.currency') is-invalid @enderror"
                            @disabled(! $hasCurrencies)>
                            <option value="">{{ $hasCurrencies ? __('Select currency') : __('No active currencies available') }}</option>
                            @foreach ($currencyOptions as $currencyOption)
                                <option value="{{ $currencyOption->code }}"
                                    @selected(old('general_settings.currency', $generalSettings['currency']) === $currencyOption->code)>
                                    {{ $currencyOption->code }} - {{ $currencyOption->name }}
                                </option>
                            @endforeach
                        </select>
                        @if (! $hasCurrencies)
                            <div class="form-text">
                                {{ $tenant->exists ? __('Create an active currency before selecting the base currency.') : __('Create the company first, then add its currencies.') }}
                            </div>
                        @endif
                        @error('general_settings.currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Timezone') }}</label>
                        <select name="general_settings[timezone]" class="form-select @error('general_settings.timezone') is-invalid @enderror">
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}"
                                    @selected(old('general_settings.timezone', $generalSettings['timezone']) === $timezone)>
                                    {{ $timezone }}
                                </option>
                            @endforeach
                        </select>
                        @error('general_settings.timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Locale') }}</label>
                        <input type="text" name="general_settings[locale]"
                            value="{{ old('general_settings.locale', $generalSettings['locale']) }}"
                            class="form-control @error('general_settings.locale') is-invalid @enderror"
                            placeholder="en">
                        @error('general_settings.locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Public Domain') }}</label>
                        <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4">
                            <div class="d-flex flex-stack flex-grow-1">
                                <div class="fw-semibold text-break">
                                    <div class="fs-6 text-gray-700">{{ __('Company address') }}</div>
                                    <div class="fw-bold text-gray-900">
                                        {{ $savedTenantDomain ?: ((old('alias', $tenant->alias) ?: 'company') . '.' . env('TENANT_HOST', 'localhost')) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('Address') }}</label>
                        <textarea name="general_settings[address]" rows="3"
                            class="form-control @error('general_settings.address') is-invalid @enderror">{{ old('general_settings.address', $generalSettings['address']) }}</textarea>
                        @error('general_settings.address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('Receipt Footer') }}</label>
                        <textarea name="general_settings[receipt_footer]" rows="3"
                            class="form-control @error('general_settings.receipt_footer') is-invalid @enderror">{{ old('general_settings.receipt_footer', $generalSettings['receipt_footer']) }}</textarea>
                        <div class="form-text">{{ __('Use this for a thank-you note or a short policy line.') }}</div>
                        @error('general_settings.receipt_footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeTenantTab === 'telegram' ? 'show active' : '' }}"
                id="tenant-telegram-pane"
                role="tabpanel"
                aria-labelledby="tenant-telegram-tab"
                tabindex="0">
                
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="uil uil-telegram-alt text-info fs-3"></i>
                            {{ __('Telegram Notifications') }}
                        </h3>
                        <p class="text-muted mb-0">{{ __('Configure real-time Telegram alerts for sales receipts and system error logs for this tenant.') }}</p>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-soft-info text-info fs-6 px-2 py-1">Integrations</span>
                        <a href="{{ route('admin.telegram-notifications.index') }}" class="btn btn-link text-info text-decoration-none fw-semibold">
                            <i class="uil uil-external-link-alt me-1"></i>Open Dedicated Setup Page
                        </a>
                    </div>
                </div>

                {{-- ========================================================================= --}}
                {{-- LEGEND 1: ORDER & POS SALE NOTIFICATIONS --}}
                {{-- ========================================================================= --}}
                <fieldset class="border border-primary border-opacity-25 rounded-3 p-3 p-md-4 mb-4 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-primary border-opacity-25 rounded-pill bg-light text-primary shadow-xs d-flex align-items-center gap-2 mb-3">
                        <i class="uil uil-receipt fs-5"></i>
                        <span>{{ __('Order & POS Sale Notifications') }}</span>
                        <span class="badge bg-soft-primary text-primary fs-8 fw-semibold">{{ __('Sales Stream') }}</span>
                    </legend>

                    <p class="text-muted small mb-3">
                        {{ __('Send instant customer & POS receipts to your store staff, sales channel, or manager whenever an order is completed.') }}
                    </p>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md mb-1">
                                <input class="form-check-input" type="checkbox" id="tenant_telegram_notifications_enabled"
                                    name="general_settings[telegram_notifications_enabled]" value="1"
                                    @checked(old('general_settings.telegram_notifications_enabled', $generalSettings['telegram_notifications_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_telegram_notifications_enabled">
                                    {{ __('Enable Telegram Order Receipts') }}
                                </label>
                            </div>
                            <div class="text-muted small">
                                {{ __('When active, completed POS sales and online customer orders trigger instant receipt alerts.') }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_telegram_bot_token">{{ __('Telegram Bot Token') }}</label>
                            <input type="text" id="tenant_telegram_bot_token" name="general_settings[telegram_bot_token]"
                                value="{{ old('general_settings.telegram_bot_token', $generalSettings['telegram_bot_token'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.telegram_bot_token') is-invalid @enderror"
                                placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                            <div class="form-text">{{ __('Token generated by @BotFather on Telegram.') }}</div>
                            @error('general_settings.telegram_bot_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_telegram_chat_id">{{ __('Chat / Channel ID') }}</label>
                            <input type="text" id="tenant_telegram_chat_id" name="general_settings[telegram_chat_id]"
                                value="{{ old('general_settings.telegram_chat_id', $generalSettings['telegram_chat_id'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.telegram_chat_id') is-invalid @enderror"
                                placeholder="-1001234567890 or 123456789">
                            <div class="form-text">{{ __('Channel or group ID (starts with -100) or personal user ID.') }}</div>
                            @error('general_settings.telegram_chat_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Live Test Section: Orders --}}
                        <div class="col-12 mt-3 pt-3 border-top">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <div class="fw-semibold text-gray-800 small">
                                    <i class="uil uil-bolt-alt text-warning me-1"></i>{{ __('Test Order Receipt Delivery') }}
                                </div>
                                <div class="text-muted small">
                                    {{ __('Test credentials directly before saving') }}
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <button type="button" id="tenant-btn-test-sample" class="btn btn-primary btn-sm px-3">
                                    <i class="uil uil-receipt me-1"></i> {{ __('Send Real Sample Order Receipt') }}
                                </button>
                                <button type="button" id="tenant-btn-test-ping" class="btn btn-outline-info btn-sm px-3">
                                    <i class="uil uil-message me-1"></i> {{ __('Send Quick Ping') }}
                                </button>
                                <span id="tenant-test-spinner" class="spinner-border spinner-border-sm text-info d-none" role="status"></span>
                            </div>

                            <div id="tenant-test-alert" class="d-none alert alert-sm py-2 px-3 mt-2 mb-0" role="alert">
                                <span id="tenant-test-message"></span>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ========================================================================= --}}
                {{-- LEGEND 2: SYSTEM ERROR LOG NOTIFICATIONS --}}
                {{-- ========================================================================= --}}
                <fieldset class="border border-danger border-opacity-25 rounded-3 p-3 p-md-4 mb-3 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-danger border-opacity-25 rounded-pill bg-soft-danger text-danger shadow-xs d-flex align-items-center gap-2 mb-3">
                        <i class="uil uil-bug fs-5"></i>
                        <span>{{ __('System Error Log Notifications (laravel.log)') }}</span>
                        <span class="badge bg-danger text-white fs-8 fw-semibold">{{ __('Developer Alerts') }}</span>
                    </legend>

                    <p class="text-muted small mb-3">
                        {{ __('Automatically forward errors, 500 exceptions, and unhandled crashes to a dedicated Telegram channel.') }}
                    </p>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md mb-1">
                                <input class="form-check-input" type="checkbox" id="tenant_telegram_error_log_enabled"
                                    name="general_settings[telegram_error_log_enabled]" value="1"
                                    @checked(old('general_settings.telegram_error_log_enabled', $generalSettings['telegram_error_log_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_telegram_error_log_enabled">
                                    {{ __('Enable Error Logging to Telegram') }}
                                </label>
                            </div>
                            <div class="text-muted small mb-2">
                                {{ __('Captures unhandled exceptions and error logs to alert developers in real-time.') }}
                            </div>
                            <div class="alert alert-light border small text-muted mb-0 py-2">
                                <i class="uil uil-info-circle me-1 text-info"></i>
                                {{ __('If Bot Token and Chat ID below are left blank, error logs will automatically inherit the credentials configured in the Order section above.') }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_telegram_error_log_bot_token">{{ __('Error Log Bot Token (Optional override)') }}</label>
                            <input type="text" id="tenant_telegram_error_log_bot_token" name="general_settings[telegram_error_log_bot_token]"
                                value="{{ old('general_settings.telegram_error_log_bot_token', $generalSettings['telegram_error_log_bot_token'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.telegram_error_log_bot_token') is-invalid @enderror"
                                placeholder="{{ __('Inherit main bot token') }}">
                            <div class="form-text">{{ __('Leave blank to use the main bot token above.') }}</div>
                            @error('general_settings.telegram_error_log_bot_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_telegram_error_log_chat_id">{{ __('Error Log Chat ID (Optional override)') }}</label>
                            <input type="text" id="tenant_telegram_error_log_chat_id" name="general_settings[telegram_error_log_chat_id]"
                                value="{{ old('general_settings.telegram_error_log_chat_id', $generalSettings['telegram_error_log_chat_id'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.telegram_error_log_chat_id') is-invalid @enderror"
                                placeholder="{{ __('Inherit main chat ID or enter dedicated -100... ID') }}">
                            <div class="form-text">{{ __('Leave blank to use the main chat ID above, or enter a separate channel ID for developers.') }}</div>
                            @error('general_settings.telegram_error_log_chat_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Live Test Section: Error Logs --}}
                        <div class="col-12 mt-3 pt-3 border-top">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <div class="fw-semibold text-danger small">
                                    <i class="uil uil-bell text-danger me-1"></i>{{ __('Test Error Alert Delivery') }}
                                </div>
                                <div class="text-muted small">
                                    {{ __('Simulate a live crash alert') }}
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <button type="button" id="tenant-btn-test-error-sample" class="btn btn-danger btn-sm px-3">
                                    <i class="uil uil-bug me-1"></i> {{ __('Send Real Sample Error Alert') }}
                                </button>
                                <button type="button" id="tenant-btn-test-error-ping" class="btn btn-outline-danger btn-sm px-3">
                                    <i class="uil uil-bell me-1"></i> {{ __('Send Error Alert Ping') }}
                                </button>
                                <span id="tenant-error-test-spinner" class="spinner-border spinner-border-sm text-danger d-none" role="status"></span>
                            </div>

                            <div id="tenant-error-test-alert" class="d-none alert alert-sm py-2 px-3 mt-2 mb-0" role="alert">
                                <span id="tenant-error-test-message"></span>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="tab-pane fade {{ $activeTenantTab === 'configuration' ? 'show active' : '' }}"
                id="tenant-configuration-pane"
                role="tabpanel"
                aria-labelledby="tenant-configuration-tab"
                tabindex="0">
                <div class="mb-4">
                    <h2 class="mb-1">{{ __('Tenant Configuration') }}</h2>
                    <p class="text-muted mb-0">{{ __('Configure the tenant identity and database connection.') }}</p>
                </div>

                <div class="row g-3">
                    @if (! $tenant->exists)
                        <div class="col-md-6">
                            <label class="form-label required">{{ __('Tenant Alias') }}</label>
                            <input type="text" name="alias" value="{{ old('alias', $tenant->alias) }}" class="form-control @error('alias') is-invalid @enderror" placeholder="rechna" />
                            <div class="form-text">{{ __('Used in the public tenant address.') }}</div>
                            @error('alias')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">{{ __('Internal Tenant ID') }}</label>
                            <input type="text" name="id" value="{{ old('id', $tenant->id) }}" class="form-control @error('id') is-invalid @enderror" />
                            <div class="form-text">{{ __('Private tenancy key; it is not used as the public host.') }}</div>
                            @error('id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="col-md-6">
                            <label class="form-label required">{{ __('Tenant Alias') }}</label>
                            <input type="text" name="alias" value="{{ old('alias', $tenant->alias) }}" class="form-control @error('alias') is-invalid @enderror" />
                            <div class="form-text">{{ __('Used in the public tenant address.') }}</div>
                            @error('alias')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Internal Tenant ID') }}</label>
                            <input type="text" value="{{ $tenant->id }}" class="form-control" disabled />
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Connection') }}</label>
                        <input type="text" name="db_connection" value="{{ old('db_connection', $tenant->db_connection ?: 'mysql') }}" class="form-control @error('db_connection') is-invalid @enderror" />
                        @error('db_connection')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Name') }}</label>
                        <input type="text" name="db_name" value="{{ old('db_name', $tenant->db_name) }}" class="form-control @error('db_name') is-invalid @enderror" />
                        @error('db_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Host') }}</label>
                        <input type="text" name="db_host" value="{{ old('db_host', $tenant->db_host ?: '127.0.0.1') }}" class="form-control @error('db_host') is-invalid @enderror" />
                        @error('db_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Port') }}</label>
                        <input type="text" name="db_port" value="{{ old('db_port', $tenant->db_port ?: '3306') }}" class="form-control @error('db_port') is-invalid @enderror" />
                        @error('db_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Username') }}</label>
                        <input type="text" name="db_username" value="{{ old('db_username', $tenant->db_username) }}" class="form-control @error('db_username') is-invalid @enderror" />
                        @error('db_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">{{ __('Database Password') }}</label>
                        <input type="text" name="db_password" value="{{ old('db_password', $tenant->db_password) }}" class="form-control @error('db_password') is-invalid @enderror" />
                        @error('db_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function ($) {
    'use strict';

    function runTenantTelegramTest(type, $btn) {
        var botToken = $('#tenant_telegram_bot_token').val().trim();
        var chatId = $('#tenant_telegram_chat_id').val().trim();
        var $spinner = $('#tenant-test-spinner');
        var $alert = $('#tenant-test-alert');
        var $message = $('#tenant-test-message');

        $('#tenant-btn-test-sample, #tenant-btn-test-ping').prop('disabled', true);
        $spinner.removeClass('d-none');
        $alert.addClass('d-none').removeClass('alert-success alert-danger');

        $.ajax({
            url: "{{ route('admin.tenants.test-telegram') }}",
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: {
                tenant_id: "{{ $tenant->id ?? '' }}",
                telegram_bot_token: botToken,
                telegram_chat_id: chatId,
                type: type
            },
            success: function (response) {
                $('#tenant-btn-test-sample, #tenant-btn-test-ping').prop('disabled', false);
                $spinner.addClass('d-none');

                $alert.removeClass('d-none alert-danger').addClass('alert-success');
                $message.html('<strong>✓ Success!</strong> ' + (response.message || 'Notification delivered to Telegram. Check your app!'));
            },
            error: function (xhr) {
                $('#tenant-btn-test-sample, #tenant-btn-test-ping').prop('disabled', false);
                $spinner.addClass('d-none');

                var errorMsg = 'Failed to connect to Telegram API.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    errorMsg = xhr.responseJSON.errors[firstKey][0];
                }

                $alert.removeClass('d-none alert-success').addClass('alert-danger');
                $message.html('<strong>✕ Error:</strong> ' + errorMsg);
            }
        });
    }

    function runTenantTelegramErrorTest(type, $btn) {
        var botToken = $('#tenant_telegram_error_log_bot_token').val().trim() || $('#tenant_telegram_bot_token').val().trim();
        var chatId = $('#tenant_telegram_error_log_chat_id').val().trim() || $('#tenant_telegram_chat_id').val().trim();
        var $spinner = $('#tenant-error-test-spinner');
        var $alert = $('#tenant-error-test-alert');
        var $message = $('#tenant-error-test-message');

        $('#tenant-btn-test-error-sample, #tenant-btn-test-error-ping').prop('disabled', true);
        $spinner.removeClass('d-none');
        $alert.addClass('d-none').removeClass('alert-success alert-danger');

        $.ajax({
            url: "{{ route('admin.tenants.test-telegram') }}",
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: {
                tenant_id: "{{ $tenant->id ?? '' }}",
                telegram_bot_token: botToken,
                telegram_chat_id: chatId,
                type: type
            },
            success: function (response) {
                $('#tenant-btn-test-error-sample, #tenant-btn-test-error-ping').prop('disabled', false);
                $spinner.addClass('d-none');

                $alert.removeClass('d-none alert-danger').addClass('alert-success');
                $message.html('<strong>✓ Success!</strong> ' + (response.message || 'Error alert delivered to Telegram!'));
            },
            error: function (xhr) {
                $('#tenant-btn-test-error-sample, #tenant-btn-test-error-ping').prop('disabled', false);
                $spinner.addClass('d-none');

                var errorMsg = 'Failed to connect to Telegram API.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    errorMsg = xhr.responseJSON.errors[firstKey][0];
                }

                $alert.removeClass('d-none alert-success').addClass('alert-danger');
                $message.html('<strong>✕ Error:</strong> ' + errorMsg);
            }
        });
    }

    $('#tenant-btn-test-sample').on('click', function () {
        runTenantTelegramTest('sample_order', $(this));
    });

    $('#tenant-btn-test-ping').on('click', function () {
        runTenantTelegramTest('ping', $(this));
    });

    $('#tenant-btn-test-error-sample').on('click', function () {
        runTenantTelegramErrorTest('error_sample', $(this));
    });

    $('#tenant-btn-test-error-ping').on('click', function () {
        runTenantTelegramErrorTest('error_ping', $(this));
    });
})(jQuery);
</script>
@endpush
