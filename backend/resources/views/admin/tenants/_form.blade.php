@php
    $configurationFields = [
        'alias',
        'domain',
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
    $hasMailErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'general_settings.mail_') || $key === 'recipient_email');
    $hasSocialErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'general_settings.facebook_'));
    $activeTenantTab = $hasSocialErrors ? 'social' : ($hasMailErrors ? 'mail' : ($hasTelegramErrors ? 'telegram' : ($hasConfigurationErrors ? 'configuration' : 'general')));
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
                    class="nav-link {{ $activeTenantTab === 'mail' ? 'active' : '' }}"
                    id="tenant-mail-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#tenant-mail-pane"
                    role="tab"
                    aria-controls="tenant-mail-pane"
                    aria-selected="{{ $activeTenantTab === 'mail' ? 'true' : 'false' }}">
                    <i class="uil uil-envelope-alt me-1"></i>{{ __('Email Notification') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button"
                    class="nav-link {{ $activeTenantTab === 'social' ? 'active' : '' }}"
                    id="tenant-social-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#tenant-social-pane"
                    role="tab"
                    aria-controls="tenant-social-pane"
                    aria-selected="{{ $activeTenantTab === 'social' ? 'true' : 'false' }}">
                    <i class="uil uil-facebook-f me-1"></i>{{ __('Social Login') }}
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
                        <input type="text" name="domain" id="tenant_domain_input"
                            value="{{ old('domain', $savedTenantDomain) }}"
                            class="form-control @error('domain') is-invalid @enderror"
                            placeholder="{{ (old('alias', $tenant->alias) ?: 'store') . '.' . config('tenancy.tenant_host', 'localhost') }}">
                        <div class="form-text">{{ __('Public store domain or sub-subdomain (e.g. rechna.vanna-pos.duckdns.org). Leave empty to use alias with default host.') }}</div>
                        @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

            {{-- ========================================================================= --}}
            {{-- TAB: EMAIL NOTIFICATION (DATABASE-BACKED MULTI-TENANT SMTP) --}}
            {{-- ========================================================================= --}}
            <div class="tab-pane fade {{ $activeTenantTab === 'mail' ? 'show active' : '' }}"
                id="tenant-mail-pane"
                role="tabpanel"
                aria-labelledby="tenant-mail-tab"
                tabindex="0">

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="uil uil-envelope-alt text-primary fs-3"></i>
                            {{ __('Email Notification') }}
                        </h3>
                        <p class="text-muted mb-0">{{ __('Configure custom outgoing mail server settings stored in database for this specific tenant instead of server .env.') }}</p>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-soft-primary text-primary fs-6 px-2 py-1">{{ __('Database Stored') }}</span>
                    </div>
                </div>

                {{-- ========================================================================= --}}
                {{-- LEGEND 1: SMTP OUTGOING SERVER CREDENTIALS --}}
                {{-- ========================================================================= --}}
                <fieldset class="border border-primary border-opacity-25 rounded-3 p-3 p-md-4 mb-4 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-primary border-opacity-25 rounded-pill bg-light text-primary shadow-xs d-flex align-items-center gap-2 mb-3">
                        <i class="uil uil-server-network fs-5"></i>
                        <span>{{ __('SMTP Outgoing Mail Settings (Database)') }}</span>
                        <span class="badge bg-soft-primary text-primary fs-8 fw-semibold">{{ __('Per-Tenant Mailer') }}</span>
                    </legend>

                    <p class="text-muted small mb-3">
                        {{ __('Customer OTP verification codes, order receipts, and notifications sent within this tenant scope will use these database settings instead of server .env.') }}
                    </p>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md mb-1">
                                <input class="form-check-input" type="checkbox" id="tenant_mail_notifications_enabled"
                                    name="general_settings[mail_notifications_enabled]" value="1"
                                    @checked(old('general_settings.mail_notifications_enabled', $generalSettings['mail_notifications_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_mail_notifications_enabled">
                                    {{ __('Enable Custom Email Configuration for this Tenant') }}
                                </label>
                            </div>
                            <div class="text-muted small">
                                {{ __('When active, customer registrations and OTP verification codes from Next.js web & mobile will send via this tenant’s dedicated mail credentials.') }}
                            </div>
                        </div>

                        {{-- MAIL_MAILER --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="tenant_mail_mailer">
                                {{ __('MAIL_MAILER (Mailer Driver)') }}
                            </label>
                            <select id="tenant_mail_mailer" name="general_settings[mail_mailer]"
                                class="form-select @error('general_settings.mail_mailer') is-invalid @enderror">
                                <option value="smtp" @selected(old('general_settings.mail_mailer', $generalSettings['mail_mailer'] ?? 'smtp') === 'smtp')>SMTP (Recommended)</option>
                                <option value="sendmail" @selected(old('general_settings.mail_mailer', $generalSettings['mail_mailer'] ?? '') === 'sendmail')>Sendmail</option>
                                <option value="log" @selected(old('general_settings.mail_mailer', $generalSettings['mail_mailer'] ?? '') === 'log')>Log (Testing / Debug)</option>
                            </select>
                            <div class="form-text">{{ __('Default is smtp.') }}</div>
                            @error('general_settings.mail_mailer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_HOST --}}
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" for="tenant_mail_host">
                                {{ __('MAIL_HOST (SMTP Host)') }}
                            </label>
                            <input type="text" id="tenant_mail_host" name="general_settings[mail_host]"
                                value="{{ old('general_settings.mail_host', $generalSettings['mail_host'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.mail_host') is-invalid @enderror"
                                placeholder="mailpit or smtp.gmail.com">
                            <div class="form-text">{{ __('SMTP server hostname (e.g. mailpit, smtp.gmail.com, smtp.mailgun.org).') }}</div>
                            @error('general_settings.mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_PORT --}}
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="tenant_mail_port">
                                {{ __('MAIL_PORT') }}
                            </label>
                            <input type="number" id="tenant_mail_port" name="general_settings[mail_port]"
                                value="{{ old('general_settings.mail_port', $generalSettings['mail_port'] ?? 1025) }}"
                                class="form-control font-monospace @error('general_settings.mail_port') is-invalid @enderror"
                                placeholder="1025 or 587">
                            <div class="form-text">{{ __('Port number (1025 for Mailpit, 587 for TLS, 465 for SSL).') }}</div>
                            @error('general_settings.mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_USERNAME --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="tenant_mail_username">
                                {{ __('MAIL_USERNAME') }}
                            </label>
                            <input type="text" id="tenant_mail_username" name="general_settings[mail_username]"
                                value="{{ old('general_settings.mail_username', $generalSettings['mail_username'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.mail_username') is-invalid @enderror"
                                placeholder="null or username@example.com">
                            <div class="form-text">{{ __('Leave blank or null if your SMTP server does not require authentication.') }}</div>
                            @error('general_settings.mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_PASSWORD --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="tenant_mail_password">
                                {{ __('MAIL_PASSWORD') }}
                            </label>
                            <div class="input-group">
                                <input type="password" id="tenant_mail_password" name="general_settings[mail_password]"
                                    value="{{ old('general_settings.mail_password', $generalSettings['mail_password'] ?? '') }}"
                                    class="form-control font-monospace @error('general_settings.mail_password') is-invalid @enderror"
                                    placeholder="••••••••">
                                <button class="btn btn-outline-secondary" type="button" id="btn-toggle-mail-password" title="Show/Hide Password">
                                    <i class="uil uil-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">{{ __('SMTP account password or app password.') }}</div>
                            @error('general_settings.mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_ENCRYPTION --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="tenant_mail_encryption">
                                {{ __('MAIL_ENCRYPTION') }}
                            </label>
                            <select id="tenant_mail_encryption" name="general_settings[mail_encryption]"
                                class="form-select @error('general_settings.mail_encryption') is-invalid @enderror">
                                <option value="null" @selected(old('general_settings.mail_encryption', $generalSettings['mail_encryption'] ?? 'null') === 'null')>None / null (Mailpit / Port 1025 / Port 25)</option>
                                <option value="tls" @selected(old('general_settings.mail_encryption', $generalSettings['mail_encryption'] ?? '') === 'tls')>TLS / STARTTLS (Port 587)</option>
                                <option value="ssl" @selected(old('general_settings.mail_encryption', $generalSettings['mail_encryption'] ?? '') === 'ssl')>SSL / SMTPS (Port 465)</option>
                            </select>
                            <div class="form-text">{{ __('Encryption type (null for Mailpit, TLS for port 587).') }}</div>
                            @error('general_settings.mail_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_FROM_ADDRESS --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_mail_from_address">
                                {{ __('MAIL_FROM_ADDRESS (Sender Email)') }}
                            </label>
                            <input type="email" id="tenant_mail_from_address" name="general_settings[mail_from_address]"
                                value="{{ old('general_settings.mail_from_address', $generalSettings['mail_from_address'] ?? ($generalSettings['contact_email'] ?? 'hello@example.com')) }}"
                                class="form-control font-monospace @error('general_settings.mail_from_address') is-invalid @enderror"
                                placeholder="hello@example.com">
                            <div class="form-text">{{ __('Address shown in customer inboxes as the sender.') }}</div>
                            @error('general_settings.mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- MAIL_FROM_NAME --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_mail_from_name">
                                {{ __('MAIL_FROM_NAME (Sender Name)') }}
                            </label>
                            <input type="text" id="tenant_mail_from_name" name="general_settings[mail_from_name]"
                                value="{{ old('general_settings.mail_from_name', $generalSettings['mail_from_name'] ?? ($generalSettings['store_name'] ?? $tenant->alias)) }}"
                                class="form-control @error('general_settings.mail_from_name') is-invalid @enderror"
                                placeholder="Rechna Store">
                            <div class="form-text">{{ __('Display name for outgoing emails (e.g. Rechna Store).') }}</div>
                            @error('general_settings.mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                {{-- ========================================================================= --}}
                {{-- LEGEND 2: AUTOMATED EMAIL TRIGGERS --}}
                {{-- ========================================================================= --}}
                <fieldset class="border border-info border-opacity-25 rounded-3 p-3 p-md-4 mb-4 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-info border-opacity-25 rounded-pill bg-soft-info text-info shadow-xs d-flex align-items-center gap-2 mb-3">
                        <i class="uil uil-envelope-check fs-5"></i>
                        <span>{{ __('Automated Email Notifications') }}</span>
                        <span class="badge bg-info text-white fs-8 fw-semibold">{{ __('Customer Triggers') }}</span>
                    </legend>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md mb-1">
                                <input class="form-check-input" type="checkbox" id="tenant_mail_order_notifications_enabled"
                                    name="general_settings[mail_order_notifications_enabled]" value="1"
                                    @checked(old('general_settings.mail_order_notifications_enabled', $generalSettings['mail_order_notifications_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_mail_order_notifications_enabled">
                                    {{ __('Send Customer Order & POS Sale Receipts via Email') }}
                                </label>
                            </div>
                            <div class="text-muted small mb-2">
                                {{ __('When enabled, completed orders and sales send an email receipt to the customer.') }}
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ========================================================================= --}}
                {{-- LIVE SMTP TEST SECTION --}}
                {{-- ========================================================================= --}}
                <fieldset class="border border-secondary border-opacity-25 rounded-3 p-3 p-md-4 mb-3 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-secondary border-opacity-25 rounded-pill bg-light text-dark shadow-xs d-flex align-items-center gap-2 mb-3">
                        <i class="uil uil-envelope-send fs-5 text-primary"></i>
                        <span>{{ __('Test Email Delivery (Live SMTP Test)') }}</span>
                    </legend>

                    <p class="text-muted small mb-3">
                        {{ __('Send an immediate test email to verify your database SMTP configuration before saving or during diagnostics.') }}
                    </p>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold" for="tenant_test_mail_recipient">{{ __('Recipient Test Email') }}</label>
                            <input type="email" id="tenant_test_mail_recipient" class="form-control font-monospace"
                                placeholder="your-email@example.com"
                                value="{{ auth()->user()?->email ?? ($generalSettings['contact_email'] ?? '') }}">
                            <div class="form-text">{{ __('Email address where the test verification email will be delivered.') }}</div>
                        </div>

                        <div class="col-md-5 d-flex align-items-end pt-md-2">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" id="tenant-btn-test-mail" class="btn btn-primary px-3">
                                    <i class="uil uil-envelope-send me-1"></i> {{ __('Send Real Test Email') }}
                                </button>
                                <span id="tenant-mail-test-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                            </div>
                        </div>

                        <div class="col-12">
                            <div id="tenant-mail-test-alert" class="d-none alert alert-sm py-2 px-3 mt-1 mb-0" role="alert">
                                <span id="tenant-mail-test-message"></span>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="tab-pane fade {{ $activeTenantTab === 'social' ? 'show active' : '' }}"
                id="tenant-social-pane"
                role="tabpanel"
                aria-labelledby="tenant-social-tab"
                tabindex="0">
                <div class="mb-4">
                    <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                        <i class="uil uil-facebook-f text-primary fs-3"></i>
                        {{ __('Facebook Login') }}
                    </h3>
                    <p class="text-muted mb-0">{{ __('Use a separate Facebook application for this tenant. Credentials are stored in the tenant settings database and are never sent to the storefront browser.') }}</p>
                </div>

                <fieldset class="border border-primary border-opacity-25 rounded-3 p-3 p-md-4 mb-4 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-primary border-opacity-25 rounded-pill bg-light text-primary">
                        {{ __('Facebook OAuth Configuration') }}
                    </legend>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md">
                                <input class="form-check-input" type="checkbox" id="tenant_facebook_login_enabled"
                                    name="general_settings[facebook_login_enabled]" value="1"
                                    @checked(old('general_settings.facebook_login_enabled', $generalSettings['facebook_login_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_facebook_login_enabled">
                                    {{ __('Enable Facebook Login for this Tenant') }}
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_facebook_app_id">{{ __('Facebook App ID') }}</label>
                            <input type="text" id="tenant_facebook_app_id" name="general_settings[facebook_app_id]"
                                value="{{ old('general_settings.facebook_app_id', $generalSettings['facebook_app_id'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.facebook_app_id') is-invalid @enderror"
                                autocomplete="off">
                            @error('general_settings.facebook_app_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_facebook_app_secret">{{ __('Facebook App Secret') }}</label>
                            <input type="password" id="tenant_facebook_app_secret" name="general_settings[facebook_app_secret]"
                                value="" class="form-control font-monospace @error('general_settings.facebook_app_secret') is-invalid @enderror"
                                placeholder="{{ ! empty($generalSettings['facebook_app_secret']) ? __('Saved — leave blank to keep current secret') : '' }}"
                                autocomplete="new-password">
                            @error('general_settings.facebook_app_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="tenant_facebook_graph_version">{{ __('Graph API Version') }}</label>
                            <input type="text" id="tenant_facebook_graph_version" name="general_settings[facebook_graph_version]"
                                value="{{ old('general_settings.facebook_graph_version', $generalSettings['facebook_graph_version'] ?? 'v24.0') }}"
                                class="form-control font-monospace @error('general_settings.facebook_graph_version') is-invalid @enderror"
                                placeholder="v24.0">
                            @error('general_settings.facebook_graph_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">{{ __('Valid OAuth Redirect URI') }}</label>
                            <input type="text" readonly class="form-control font-monospace bg-light"
                                value="{{ $savedTenantDomain ? 'https://'.$savedTenantDomain.'/api/shop/auth/facebook/callback' : __('Save the tenant domain to generate the callback URL') }}">
                            <div class="form-text">{{ __('Copy this exact URL into Facebook Login → Settings → Valid OAuth Redirect URIs.') }}</div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border border-danger border-opacity-25 rounded-3 p-3 p-md-4 mb-4 bg-white shadow-sm">
                    <legend class="float-none w-auto px-3 py-1 fs-6 fw-bold border border-danger border-opacity-25 rounded-pill bg-light text-danger">
                        {{ __('Google OAuth Configuration') }}
                    </legend>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md">
                                <input class="form-check-input" type="checkbox" id="tenant_google_login_enabled"
                                    name="general_settings[google_login_enabled]" value="1"
                                    @checked(old('general_settings.google_login_enabled', $generalSettings['google_login_enabled'] ?? false))>
                                <label class="form-check-label fw-semibold" for="tenant_google_login_enabled">
                                    {{ __('Enable Google Login for this Tenant') }}
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_google_client_id">{{ __('Google Client ID') }}</label>
                            <input type="text" id="tenant_google_client_id" name="general_settings[google_client_id]"
                                value="{{ old('general_settings.google_client_id', $generalSettings['google_client_id'] ?? '') }}"
                                class="form-control font-monospace @error('general_settings.google_client_id') is-invalid @enderror"
                                autocomplete="off">
                            @error('general_settings.google_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="tenant_google_client_secret">{{ __('Google Client Secret') }}</label>
                            <input type="password" id="tenant_google_client_secret" name="general_settings[google_client_secret]"
                                value="" class="form-control font-monospace @error('general_settings.google_client_secret') is-invalid @enderror"
                                placeholder="{{ ! empty($generalSettings['google_client_secret']) ? __('Saved — leave blank to keep current secret') : '' }}"
                                autocomplete="new-password">
                            @error('general_settings.google_client_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">{{ __('Authorized redirect URI') }}</label>
                            <input type="text" readonly class="form-control font-monospace bg-light"
                                value="{{ $savedTenantDomain ? 'https://'.$savedTenantDomain.'/api/shop/auth/google/callback' : __('Save the tenant domain to generate the callback URL') }}">
                            <div class="form-text">{{ __('Copy this exact URL into Google Cloud Console → Credentials → Authorized redirect URIs.') }}</div>
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

    // Toggle Mail Password Visibility
    $('#btn-toggle-mail-password').on('click', function () {
        var $input = $('#tenant_mail_password');
        var $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('uil-eye').addClass('uil-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('uil-eye-slash').addClass('uil-eye');
        }
    });

    // Test Mail Delivery via AJAX
    function runTenantMailTest($btn) {
        var recipient = ($('#tenant_test_mail_recipient').val() || '').trim();
        var $spinner = $('#tenant-mail-test-spinner');
        var $alert = $('#tenant-mail-test-alert');
        var $message = $('#tenant-mail-test-message');

        if (!recipient) {
            $alert.removeClass('d-none alert-success').addClass('alert-danger');
            $message.html('<strong>✕ Error:</strong> Please enter a recipient email address to send a test message.');
            $('#tenant_test_mail_recipient').focus();
            return;
        }

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $alert.addClass('d-none').removeClass('alert-success alert-danger');

        $.ajax({
            url: "{{ route('admin.tenants.test-mail') }}",
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: {
                tenant_id: "{{ $tenant->id ?? '' }}",
                recipient_email: recipient,
                mail_mailer: $('#tenant_mail_mailer').val(),
                mail_host: $('#tenant_mail_host').val(),
                mail_port: $('#tenant_mail_port').val(),
                mail_encryption: $('#tenant_mail_encryption').val(),
                mail_username: $('#tenant_mail_username').val(),
                mail_password: $('#tenant_mail_password').val(),
                mail_from_address: $('#tenant_mail_from_address').val(),
                mail_from_name: $('#tenant_mail_from_name').val()
            },
            success: function (response) {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $alert.removeClass('d-none alert-danger').addClass('alert-success');
                $message.html('<strong>✓ Success!</strong> ' + (response.message || 'Test email delivered successfully!'));
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                var errorMsg = 'Failed to connect to SMTP server.';
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

    $('#tenant-btn-test-mail').on('click', function () {
        runTenantMailTest($(this));
    });
})(jQuery);
</script>
@endpush
