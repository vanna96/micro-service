@extends('layouts.app')

@section('title', 'Telegram Notifications & Error Logs')
@section('page_title', 'Telegram Notifications & Error Logs')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="alert alert-border-left alert-light mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div>
                <i class="mdi mdi-telegram text-info me-2 fs-5 align-middle"></i>Managing Telegram notifications for tenant
                <strong>{{ $storeName }}</strong>.
            </div>
            <div>
                <a href="{{ route('admin.general-settings.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="uil-arrow-left me-1"></i>Back to General Settings
                </a>
            </div>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="uil uil-check-circle me-1"></i>{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="uil uil-exclamation-octagon me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Navigation Tabs: Order Receipts vs System Error Logs --}}
@php
    $activeTab = request('tab', 'orders');
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-justified" id="telegram-nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'orders' ? 'active' : '' }} d-flex align-items-center justify-content-center gap-2 py-3 fw-semibold fs-6"
                    id="orders-tab" data-bs-toggle="tab" data-bs-target="#tab-orders" type="button" role="tab"
                    aria-controls="tab-orders" aria-selected="{{ $activeTab === 'orders' ? 'true' : 'false' }}">
                    <i class="uil uil-receipt text-primary fs-4"></i>
                    <span>Order & POS Receipts</span>
                    @if ($isEnabled && $isConfigured)
                        <span class="badge bg-success ms-1">Active</span>
                    @elseif ($isEnabled)
                        <span class="badge bg-warning ms-1">Setup Needed</span>
                    @else
                        <span class="badge bg-secondary ms-1">Disabled</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'error_log' ? 'active' : '' }} d-flex align-items-center justify-content-center gap-2 py-3 fw-semibold fs-6"
                    id="errors-tab" data-bs-toggle="tab" data-bs-target="#tab-errors" type="button" role="tab"
                    aria-controls="tab-errors" aria-selected="{{ $activeTab === 'error_log' ? 'true' : 'false' }}">
                    <i class="uil uil-bug text-danger fs-4"></i>
                    <span>System Error Logs (laravel.log)</span>
                    @if ($isErrorLogEnabled && $isErrorLogConfigured)
                        <span class="badge bg-danger ms-1">Active</span>
                    @elseif ($isErrorLogEnabled)
                        <span class="badge bg-warning ms-1">Setup Needed</span>
                    @else
                        <span class="badge bg-secondary ms-1">Disabled</span>
                    @endif
                </button>
            </li>
        </ul>
    </div>
</div>

<form id="telegram-settings-form" method="POST" action="{{ route('admin.telegram-notifications.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="active_tab" id="active_tab" value="{{ request('tab', 'orders') }}">

    <div class="tab-content" id="telegram-tab-content">
        {{-- ========================================================================= --}}
        {{-- TAB 1: ORDER & POS SALE RECEIPTS --}}
        {{-- ========================================================================= --}}
        <div class="tab-pane fade {{ $activeTab === 'orders' ? 'show active' : '' }}" id="tab-orders" role="tabpanel" aria-labelledby="orders-tab">
            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
                                <div>
                                    <span class="badge bg-soft-info text-info mb-2">Customer & Sales Stream</span>
                                    <h4 class="mb-1 d-flex align-items-center gap-2">
                                        <i class="uil uil-receipt text-primary fs-3"></i>
                                        Order & POS Sale Notifications
                                    </h4>
                                    <p class="text-muted mb-0">
                                        Send automatic receipts to your store staff, sales channel, or manager whenever an order is placed or checkout is completed.
                                    </p>
                                </div>
                                <div>
                                    @if ($isEnabled && $isConfigured)
                                        <span class="badge bg-soft-success text-success px-3 py-2 fs-6">
                                            <i class="mdi mdi-circle-medium text-success"></i> Connected
                                        </span>
                                    @elseif ($isEnabled)
                                        <span class="badge bg-soft-warning text-warning px-3 py-2 fs-6">
                                            <i class="mdi mdi-alert-circle-outline"></i> Setup Needed
                                        </span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary px-3 py-2 fs-6">
                                            <i class="mdi mdi-pause-circle-outline"></i> Disabled
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Enable Toggle --}}
                            <div class="p-3 bg-light rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <label class="form-check-label fw-bold fs-5 mb-1 cursor-pointer" for="telegram_notifications_enabled">
                                        Enable Order Receipt Notifications
                                    </label>
                                    <div class="text-muted small">
                                        Dispatched asynchronously in the background immediately upon POS sale or online checkout.
                                    </div>
                                </div>
                                <div class="form-check form-switch form-switch-lg">
                                    <input class="form-check-input" type="checkbox" id="telegram_notifications_enabled"
                                        name="telegram_notifications_enabled" value="1"
                                        @checked(old('telegram_notifications_enabled', $isEnabled))>
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Bot Token --}}
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="telegram_bot_token">
                                            Telegram Bot Token <span class="text-danger">*</span>
                                        </label>
                                        <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-primary small text-decoration-none">
                                            <i class="uil uil-external-link-alt me-1"></i>Get Token from @BotFather
                                        </a>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="uil uil-key-skeleton"></i></span>
                                        <input type="password" id="telegram_bot_token" name="telegram_bot_token"
                                            value="{{ old('telegram_bot_token', $botToken) }}"
                                            class="form-control font-monospace @error('telegram_bot_token') is-invalid @enderror"
                                            placeholder="e.g. 123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('telegram_bot_token', this)">
                                            <i class="uil uil-eye"></i>
                                        </button>
                                    </div>
                                    @error('telegram_bot_token')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    <div class="form-text">Bot token created via @BotFather in Telegram.</div>
                                </div>

                                {{-- Chat / Channel ID --}}
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="telegram_chat_id">
                                            Chat / Channel ID <span class="text-danger">*</span>
                                        </label>
                                        <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none" data-bs-toggle="collapse" data-bs-target="#chat-id-help" aria-expanded="false">
                                            <i class="uil uil-question-circle me-1"></i>How to find Chat ID?
                                        </button>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="uil uil-comment-alt-lines"></i></span>
                                        <input type="text" id="telegram_chat_id" name="telegram_chat_id"
                                            value="{{ old('telegram_chat_id', $chatId) }}"
                                            class="form-control font-monospace @error('telegram_chat_id') is-invalid @enderror"
                                            placeholder="e.g. -1001987654321 or 123456789">
                                    </div>
                                    @error('telegram_chat_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    <div class="form-text">
                                        The ID of the target channel or group. Channel and group IDs usually start with <code>-100</code>.
                                    </div>

                                    <div class="collapse mt-2" id="chat-id-help">
                                        <div class="card card-body bg-light border-0 small text-muted">
                                            <h6 class="text-dark fw-bold mb-2"><i class="uil uil-info-circle me-1 text-info"></i>How to get Chat ID:</h6>
                                            <ol class="mb-0 ps-3">
                                                <li class="mb-1"><strong>For a Telegram Group or Channel:</strong>
                                                    Add your bot as an <strong>Administrator</strong> with post message rights.
                                                    Then forward any message from the channel to <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>. The ID starting with <code>-100...</code> is your Chat ID.
                                                </li>
                                                <li><strong>For personal Telegram:</strong>
                                                    Message <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> to receive your personal ID.
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 mt-4 border-top">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="uil uil-save me-1"></i> Save Order Notification Settings
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Live Testing Card: Orders --}}
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="uil uil-receipt text-primary fs-4"></i>
                                Live Test Order Receipts
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-3">
                                Send a live test receipt to verify formatting, currency symbol (<code>{{ $currency }}</code>), and Telegram delivery before taking real orders.
                            </p>

                            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                                <button type="button" id="btn-send-sample-order" class="btn btn-primary px-4 py-2 fw-semibold">
                                    <i class="uil uil-receipt me-1"></i> Send Real Sample Order Receipt
                                </button>
                                <button type="button" id="btn-send-test" class="btn btn-outline-info px-3 py-2 fw-semibold">
                                    <i class="uil uil-message me-1"></i> Send Connection Ping
                                </button>
                                <span id="test-spinner" class="spinner-border spinner-border-sm text-info d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </span>
                            </div>

                            <div id="test-result-alert" class="d-none alert mt-3 mb-0" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i id="test-result-icon" class="fs-4"></i>
                                    <div>
                                        <strong id="test-result-title"></strong>
                                        <div id="test-result-message" class="mt-1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Sidebar: Order Receipts --}}
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="uil uil-eye text-primary"></i>
                                Telegram Receipt Preview
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="p-3 rounded-3" style="background: #182533; color: #fff; font-size: 13px; line-height: 1.5;">
                                <div class="fw-bold mb-2 text-warning">🛒 New POS Sale Completed!</div>
                                <div class="text-light opacity-75">🏪 <strong>Store:</strong> {{ $storeName }}</div>
                                <div class="text-light opacity-75">🧾 <strong>Invoice:</strong> <span class="badge bg-secondary text-light">INV-TEST-0042</span></div>
                                <div class="text-light opacity-75">👤 <strong>Customer:</strong> Sophea Meas</div>
                                <div class="text-light opacity-75">💳 <strong>Payment:</strong> Cash</div>
                                <hr class="my-2 border-secondary">
                                <div class="fw-semibold mb-1">Items (2):</div>
                                <div class="d-flex justify-content-between text-light opacity-75">
                                    <span>• 1x Iced Americano (M)</span>
                                    <code>$3.50</code>
                                </div>
                                <div class="d-flex justify-content-between text-light opacity-75">
                                    <span>• 2x Croissant</span>
                                    <code>$4.00</code>
                                </div>
                                <hr class="my-2 border-secondary">
                                <div class="d-flex justify-content-between fw-bold text-success fs-6">
                                    <span>💵 Total Paid:</span>
                                    <span>$7.50</span>
                                </div>
                                <div class="mt-2 text-muted small fst-italic">⏱️ Just now</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- TAB 2: SYSTEM ERROR LOGS (laravel.log) --}}
        {{-- ========================================================================= --}}
        <div class="tab-pane fade {{ $activeTab === 'error_log' ? 'show active' : '' }}" id="tab-errors" role="tabpanel" aria-labelledby="errors-tab">
            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
                                <div>
                                    <span class="badge bg-soft-danger text-danger mb-2">Technical & Developer Stream</span>
                                    <h4 class="mb-1 d-flex align-items-center gap-2">
                                        <i class="uil uil-bug text-danger fs-3"></i>
                                        System Error Log Notifications (laravel.log)
                                    </h4>
                                    <p class="text-muted mb-0">
                                        Sends real-time error alerts directly to your development team whenever an error or unhandled exception is written to <code>laravel.log</code>.
                                    </p>
                                </div>
                                <div>
                                    @if ($isErrorLogEnabled && $isErrorLogConfigured)
                                        <span class="badge bg-soft-danger text-danger px-3 py-2 fs-6">
                                            <i class="mdi mdi-circle-medium text-danger"></i> Monitoring Active
                                        </span>
                                    @elseif ($isErrorLogEnabled)
                                        <span class="badge bg-soft-warning text-warning px-3 py-2 fs-6">
                                            <i class="mdi mdi-alert-circle-outline"></i> Setup Needed
                                        </span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary px-3 py-2 fs-6">
                                            <i class="mdi mdi-pause-circle-outline"></i> Disabled
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Enable Error Log Toggle --}}
                            <div class="p-3 bg-light rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <label class="form-check-label fw-bold fs-5 mb-1 cursor-pointer" for="telegram_error_log_enabled">
                                        Enable System Error Logging to Telegram
                                    </label>
                                    <div class="text-muted small">
                                        Monitors <code>laravel.log</code> and instantly forwards exceptions, 500 errors, and <code>Log::error()</code> calls.
                                    </div>
                                </div>
                                <div class="form-check form-switch form-switch-lg">
                                    <input class="form-check-input" type="checkbox" id="telegram_error_log_enabled"
                                        name="telegram_error_log_enabled" value="1"
                                        @checked(old('telegram_error_log_enabled', $isErrorLogEnabled))>
                                </div>
                            </div>

                            <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
                                <i class="uil uil-info-circle fs-4"></i>
                                <div class="small">
                                    <strong>Inheritance & Dedicated Channels:</strong>
                                    If you leave the Bot Token and Chat ID below blank, error logs will automatically use the credentials configured in the <strong>Order Receipts</strong> tab (or global <code>.env</code>).
                                    Provide separate credentials below if you want errors sent to a private <strong>#dev-errors</strong> channel.
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Error Log Bot Token --}}
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="telegram_error_log_bot_token">
                                            Error Log Bot Token <span class="text-muted small fw-normal">(Optional override)</span>
                                        </label>
                                        @if ($resolvedErrorToken)
                                            <span class="badge bg-soft-success text-success small">Inherited Token Active</span>
                                        @endif
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="uil uil-key-skeleton"></i></span>
                                        <input type="password" id="telegram_error_log_bot_token" name="telegram_error_log_bot_token"
                                            value="{{ old('telegram_error_log_bot_token', $errorLogBotToken) }}"
                                            class="form-control font-monospace @error('telegram_error_log_bot_token') is-invalid @enderror"
                                            placeholder="{{ $resolvedErrorToken ? 'Inheriting token (or enter custom token)' : 'e.g. 123456789:ABCdefGHI...' }}">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('telegram_error_log_bot_token', this)">
                                            <i class="uil uil-eye"></i>
                                        </button>
                                    </div>
                                    @error('telegram_error_log_bot_token')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    <div class="form-text">Leave blank to inherit the main bot token.</div>
                                </div>

                                {{-- Error Log Chat ID --}}
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="telegram_error_log_chat_id">
                                            Error Log Chat / Channel ID <span class="text-muted small fw-normal">(Optional override)</span>
                                        </label>
                                        @if ($resolvedErrorChat)
                                            <span class="badge bg-soft-success text-success small">Inherited Chat ID Active</span>
                                        @endif
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="uil uil-comment-alt-lines"></i></span>
                                        <input type="text" id="telegram_error_log_chat_id" name="telegram_error_log_chat_id"
                                            value="{{ old('telegram_error_log_chat_id', $errorLogChatId) }}"
                                            class="form-control font-monospace @error('telegram_error_log_chat_id') is-invalid @enderror"
                                            placeholder="{{ $resolvedErrorChat ? 'Inheriting ID: ' . $resolvedErrorChat : 'e.g. -100987654321' }}">
                                    </div>
                                    @error('telegram_error_log_chat_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    <div class="form-text">
                                        Target chat for error alerts. Recommend setting to a private developer group or tech channel.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                        <i class="uil uil-shield-exclamation text-danger fs-5"></i>
                                        <span class="small text-muted">Minimum Log Level:</span>
                                        <span class="badge bg-danger">{{ $errorLogLevel }}</span>
                                        <span class="small text-muted">(Captures <code>ERROR</code>, <code>CRITICAL</code>, <code>ALERT</code>, and <code>EMERGENCY</code>)</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 mt-4 border-top">
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="uil uil-save me-1"></i> Save Error Log Settings
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Live Testing Card: Error Logs --}}
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="uil uil-bug text-danger fs-4"></i>
                                Live Test Error Log Alert
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-3">
                                Test that error logging reaches your Telegram channel with realistic exception stack traces, request paths, and client IP details.
                            </p>

                            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                                <button type="button" id="btn-send-sample-error" class="btn btn-danger px-4 py-2 fw-semibold">
                                    <i class="uil uil-bug me-1"></i> Send Real Sample Error Alert
                                </button>
                                <button type="button" id="btn-send-error-ping" class="btn btn-outline-danger px-3 py-2 fw-semibold">
                                    <i class="uil uil-bell me-1"></i> Send Error Alert Ping
                                </button>
                                <span id="error-test-spinner" class="spinner-border spinner-border-sm text-danger d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </span>
                            </div>

                            <div id="error-test-result-alert" class="d-none alert mt-3 mb-0" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i id="error-test-result-icon" class="fs-4"></i>
                                    <div>
                                        <strong id="error-test-result-title"></strong>
                                        <div id="error-test-result-message" class="mt-1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Sidebar: Error Alert Preview --}}
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="uil uil-eye text-danger"></i>
                                Error Alert Preview
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="p-3 rounded-3" style="background: #111827; border: 1px solid rgba(255,255,255,0.08); color: #e5e7eb; font-size: 12.5px; line-height: 1.6; font-family: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                                    <span class="fw-bold text-danger">🚨 [ERROR] Laravel Error Alert</span>
                                    <span class="badge bg-soft-danger text-danger font-monospace" style="font-size: 10px;">local</span>
                                </div>
                                <div class="text-light opacity-90 ps-1" style="white-space: pre-wrap; word-break: break-word; font-size: 12px; line-height: 1.75;">
 ├ <strong class="text-white">Store :</strong> <span class="text-info">{{ $storeName }}</span>
 ├ <strong class="text-white">Env :</strong> <span class="text-warning">local</span>
 ├ <strong class="text-white">Exception :</strong> <span class="text-danger fw-semibold">BadMethodCallException</span>
 ├ <strong class="text-white">Time :</strong> <span class="text-white-50">2026-09-14 16:30:42</span>
 ├ <strong class="text-white">Route :</strong> <span class="text-light">GET /next/auth/session</span>
 ├ <strong class="text-white">Client IP :</strong> <span class="text-light">172.20.0.4</span>
 ├ <strong class="text-white">User :</strong> Guest
 └ <strong class="text-white">Error Details :</strong>
 <div class="tg-code-box my-1 rounded" style="background: #090e17; border: 1px solid #1e2c3f; overflow: hidden; max-width: 100%;">
     <div class="d-flex align-items-center justify-content-between px-2 py-1" style="background: #152232; border-bottom: 1px solid #1e2c3f; font-size: 11px; color: #5a7491; cursor: pointer;" onclick="copyTelegramSnippet(this, 'Message:\nCall to undefined method App\\Http\\Middleware\\EnforceSecurityFirewall::checkRateLimit()\n\nSource:\napp/Http/Middleware/EnforceSecurityFirewall.php:103\n\nTrace:\n#0 app/Http/Middleware/EnforceSecurityFirewall.php:103\n   ↳ EnforceSecurityFirewall->handle()\n#1 app/Http/Controllers/Auth/NextPortalAuthController.php:45\n   ↳ NextPortalAuthController->session()')">
         <span class="tg-copy-text">copy</span>
         <i class="uil uil-copy"></i>
     </div>
     <pre class="p-2 mb-0 font-monospace" style="color: #60a5fa; font-size: 11px; line-height: 1.55; white-space: pre-wrap; word-break: break-word; background: transparent; border: 0;"><span class="text-muted">Message:</span>
<span class="text-light">Call to undefined method App\Http\Middleware\EnforceSecurityFirewall::checkRateLimit()</span>

<span class="text-muted">Source:</span>
<span class="text-info">app/Http/Middleware/EnforceSecurityFirewall.php:103</span>

<span class="text-muted">Trace:</span>
<span class="text-info">#0 app/Http/Middleware/EnforceSecurityFirewall.php:103
   ↳ EnforceSecurityFirewall-&gt;handle()
#1 app/Http/Controllers/Auth/NextPortalAuthController.php:45
   ↳ NextPortalAuthController-&gt;session()</span></pre>
 </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('uil-eye');
        icon.classList.add('uil-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('uil-eye-slash');
        icon.classList.add('uil-eye');
    }
}

function copyTelegramSnippet(el, text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        const label = el.querySelector('.tg-copy-text');
        const icon = el.querySelector('i');
        const origText = label ? label.textContent : '';
        const origIcon = icon ? icon.className : '';

        if (label) {
            label.textContent = 'copied!';
            label.style.color = '#34d399';
        }
        if (icon) {
            icon.className = 'uil uil-check text-success';
        }

        setTimeout(() => {
            if (label) {
                label.textContent = origText;
                label.style.color = '#5a7491';
            }
            if (icon) {
                icon.className = origIcon;
            }
        }, 1500);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Preserve active tab across reloads or form submissions
    const activeTabInput = document.getElementById('active_tab');
    const tabButtons = document.querySelectorAll('#telegram-nav-tabs button[data-bs-toggle="tab"]');

    // Check URL query param ?tab=errors
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || '{{ session("active_tab", "orders") }}';
    if (initialTab === 'errors') {
        const errorTabTrigger = document.getElementById('errors-tab');
        if (errorTabTrigger && window.bootstrap && bootstrap.Tab) {
            new bootstrap.Tab(errorTabTrigger).show();
            activeTabInput.value = 'errors';
        }
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('shown.bs.tab', function (e) {
            if (e.target.id === 'errors-tab') {
                activeTabInput.value = 'errors';
            } else {
                activeTabInput.value = 'orders';
            }
        });
    });

    // Helper for AJAX testing
    function executeTelegramTest(payload, spinnerEl, alertEl, iconEl, titleEl, msgEl, buttons) {
        buttons.forEach(b => b.disabled = true);
        spinnerEl.classList.remove('d-none');
        alertEl.className = 'alert mt-3 mb-0 d-none';

        fetch('{{ route("admin.telegram-notifications.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(async response => {
            const data = await response.json();
            spinnerEl.classList.add('d-none');
            buttons.forEach(b => b.disabled = false);

            alertEl.classList.remove('d-none');
            if (response.ok && data.success) {
                alertEl.className = 'alert alert-success mt-3 mb-0';
                iconEl.className = 'uil uil-check-circle text-success fs-4';
                titleEl.textContent = 'Success!';
                msgEl.textContent = data.message || 'Notification sent successfully to Telegram!';
            } else {
                alertEl.className = 'alert alert-danger mt-3 mb-0';
                iconEl.className = 'uil uil-exclamation-octagon text-danger fs-4';
                titleEl.textContent = 'Delivery Failed';
                msgEl.textContent = data.message || 'Failed to send message to Telegram.';
            }
        })
        .catch(err => {
            spinnerEl.classList.add('d-none');
            buttons.forEach(b => b.disabled = false);

            alertEl.className = 'alert alert-danger mt-3 mb-0';
            alertEl.classList.remove('d-none');
            iconEl.className = 'uil uil-exclamation-octagon text-danger fs-4';
            titleEl.textContent = 'Network Error';
            msgEl.textContent = err.message || 'Could not connect to server.';
        });
    }

    // ORDER TEST BUTTONS
    const btnSampleOrder = document.getElementById('btn-send-sample-order');
    const btnPing = document.getElementById('btn-send-test');
    const orderSpinner = document.getElementById('test-spinner');
    const orderAlert = document.getElementById('test-result-alert');
    const orderIcon = document.getElementById('test-result-icon');
    const orderTitle = document.getElementById('test-result-title');
    const orderMsg = document.getElementById('test-result-message');

    if (btnSampleOrder && btnPing) {
        btnSampleOrder.addEventListener('click', function () {
            executeTelegramTest({
                type: 'sample_order',
                telegram_bot_token: document.getElementById('telegram_bot_token').value,
                telegram_chat_id: document.getElementById('telegram_chat_id').value
            }, orderSpinner, orderAlert, orderIcon, orderTitle, orderMsg, [btnSampleOrder, btnPing]);
        });

        btnPing.addEventListener('click', function () {
            executeTelegramTest({
                type: 'ping',
                telegram_bot_token: document.getElementById('telegram_bot_token').value,
                telegram_chat_id: document.getElementById('telegram_chat_id').value
            }, orderSpinner, orderAlert, orderIcon, orderTitle, orderMsg, [btnSampleOrder, btnPing]);
        });
    }

    // ERROR LOG TEST BUTTONS
    const btnSampleError = document.getElementById('btn-send-sample-error');
    const btnErrorPing = document.getElementById('btn-send-error-ping');
    const errorSpinner = document.getElementById('error-test-spinner');
    const errorAlert = document.getElementById('error-test-result-alert');
    const errorIcon = document.getElementById('error-test-result-icon');
    const errorTitle = document.getElementById('error-test-result-title');
    const errorMsg = document.getElementById('error-test-result-message');

    if (btnSampleError && btnErrorPing) {
        btnSampleError.addEventListener('click', function () {
            executeTelegramTest({
                type: 'error_sample',
                telegram_error_log_bot_token: document.getElementById('telegram_error_log_bot_token').value,
                telegram_error_log_chat_id: document.getElementById('telegram_error_log_chat_id').value,
                telegram_bot_token: document.getElementById('telegram_bot_token').value,
                telegram_chat_id: document.getElementById('telegram_chat_id').value
            }, errorSpinner, errorAlert, errorIcon, errorTitle, errorMsg, [btnSampleError, btnErrorPing]);
        });

        btnErrorPing.addEventListener('click', function () {
            executeTelegramTest({
                type: 'error_ping',
                telegram_error_log_bot_token: document.getElementById('telegram_error_log_bot_token').value,
                telegram_error_log_chat_id: document.getElementById('telegram_error_log_chat_id').value,
                telegram_bot_token: document.getElementById('telegram_bot_token').value,
                telegram_chat_id: document.getElementById('telegram_chat_id').value
            }, errorSpinner, errorAlert, errorIcon, errorTitle, errorMsg, [btnSampleError, btnErrorPing]);
        });
    }
});
</script>
@endpush
