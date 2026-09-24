@extends('layouts.app')

@section('page_title', __('Security Center'))

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    /* Sleek Responsive Table Scroll Container */
    .security-table-container {
        position: relative;
        background: #fff;
    }
    .security-table-responsive {
        width: 100%;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
        position: relative;
    }
    .security-table-responsive::-webkit-scrollbar,
    .dataTables_wrapper > .row:nth-child(2) > .col-sm-12::-webkit-scrollbar {
        height: 10px;
    }
    .security-table-responsive::-webkit-scrollbar-track,
    .dataTables_wrapper > .row:nth-child(2) > .col-sm-12::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 6px;
    }
    .security-table-responsive::-webkit-scrollbar-thumb,
    .dataTables_wrapper > .row:nth-child(2) > .col-sm-12::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 6px;
        border: 2px solid #f1f5f9;
    }
    .security-table-responsive::-webkit-scrollbar-thumb:hover,
    .dataTables_wrapper > .row:nth-child(2) > .col-sm-12::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
    .dataTables_wrapper > .row:nth-child(2) > .col-sm-12 {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
        position: relative;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    /* Resizable Table Styling */
    table.table-resizable {
        border-collapse: separate !important;
        border-spacing: 0;
        table-layout: fixed !important;
        margin-bottom: 0 !important;
        width: max-content !important;
        max-width: none !important;
    }
    table.table-resizable thead th {
        position: relative !important;
        user-select: none;
        vertical-align: middle;
        white-space: nowrap !important;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 20px !important;
    }
    table.table-resizable tbody td {
        vertical-align: middle;
        white-space: nowrap !important;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 0.65rem 0.75rem !important;
    }

    /* Draggable Column Resize Handle */
    .col-resizer {
        position: absolute;
        top: 0;
        right: 0;
        width: 8px;
        height: 100%;
        cursor: col-resize !important;
        user-select: none;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .col-resizer::after {
        content: '';
        width: 2px;
        height: 50%;
        background-color: transparent;
        transition: background-color 0.15s ease, height 0.15s ease;
        border-radius: 1px;
    }
    .col-resizer:hover::after,
    thead th:hover .col-resizer::after {
        background-color: #3b82f6;
        height: 85%;
    }
    .col-resizer.resizing::after {
        background-color: #2563eb;
        height: 100%;
    }

    /* Real-Time Column Resize Guide Line */
    .table-resize-guide {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #2563eb;
        pointer-events: none;
        z-index: 9999;
        display: none;
        box-shadow: 0 0 6px rgba(37, 99, 235, 0.4);
    }
    body.is-col-resizing {
        cursor: col-resize !important;
        user-select: none !important;
    }

    /* Visual Hint Badge */
    .resize-hint-badge {
        font-size: 11px;
        font-weight: 500;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        padding: 3px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* Google Maps Location Link */
    .map-link-hover {
        transition: all 0.15s ease-in-out;
        border-radius: 4px;
        line-height: 1.4;
    }
    .map-link-hover:hover {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.25);
    }
    .map-link-hover:hover i.text-danger {
        color: #fee2e2 !important;
    }
    .map-link-hover:hover i.opacity-75 {
        opacity: 1 !important;
        color: #ffffff !important;
    }
    .hover-primary {
        transition: color 0.15s ease;
    }
    .hover-primary:hover {
        color: #2563eb !important;
        text-decoration: underline !important;
    }

    /* Clean Non-Wrapping Security Navigation Tabs */
    .security-tabs-nav {
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
        margin-bottom: -1px;
    }
    .security-tabs-nav::-webkit-scrollbar {
        display: none;
    }
    .security-tabs-nav .nav-item {
        flex-shrink: 0;
    }
    .security-tabs-nav .nav-link {
        padding: 0.85rem 1.15rem !important;
        font-size: 0.84rem;
        letter-spacing: 0.01em;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        color: #64748b;
        border: none !important;
        position: relative;
        transition: color 0.15s ease, background-color 0.15s ease;
    }
    @media (min-width: 1400px) {
        .security-tabs-nav .nav-link {
            padding: 0.95rem 1.35rem !important;
            font-size: 0.875rem;
        }
    }
    .security-tabs-nav .nav-link:hover {
        color: #1e293b;
        background-color: rgba(59, 130, 246, 0.03);
    }
    .security-tabs-nav .nav-link.active {
        color: #2563eb !important;
        font-weight: 600;
        background-color: transparent !important;
    }
    .security-tabs-nav .nav-link.active::after {
        background: #2563eb !important;
        height: 2.5px !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Status Flash Message --}}
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="uil-check-circle fs-4 me-2"></i>
            <div>{{ session('status') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- "Under Attack" Warning Banner if active --}}
    @if ($stats['under_attack'])
        <div class="alert alert-danger d-flex align-items-center mb-4 py-3 shadow-sm border-0" role="alert">
            <i class="uil-exclamation-triangle fs-2 me-3 text-danger"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1 text-danger fw-bold">{{ __('Under Attack Emergency Mode is Active!') }}</h5>
                <p class="mb-0 small text-muted">
                    {{ __('Strict rate limits and aggressive bot filters are actively enforced across all platform routes.') }}
                </p>
            </div>
            <a href="#tab-ddos" data-bs-toggle="tab" class="btn btn-danger btn-sm px-3">
                {{ __('Adjust Policy') }}
            </a>
        </div>
    @endif

    {{-- Top Overview Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3 flex-shrink-0">
                            <span class="avatar-title bg-soft-primary text-primary rounded-circle fs-3 p-3">
                                <i class="uil-shield-check"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-semibold font-size-12 mb-1">{{ __('Total Threats Stopped') }}</p>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['total_threats']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3 flex-shrink-0">
                            <span class="avatar-title bg-soft-danger text-danger rounded-circle fs-3 p-3">
                                <i class="uil-bolt"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-semibold font-size-12 mb-1">{{ __('Threats Today') }}</p>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['threats_today']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3 flex-shrink-0">
                            <span class="avatar-title bg-soft-warning text-warning rounded-circle fs-3 p-3">
                                <i class="uil-database"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-semibold font-size-12 mb-1">{{ __('SQL Injections Blocked') }}</p>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['sqli_blocked']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3 flex-shrink-0">
                            <span class="avatar-title bg-soft-dark text-dark rounded-circle fs-3 p-3">
                                <i class="uil-user-times"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-semibold font-size-12 mb-1">{{ __('Active IP Blacklists') }}</p>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['active_bans']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Security Card with Minimalist Tabs --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-transparent border-bottom p-0">
            <ul class="nav nav-tabs nav-tabs-custom card-header-tabs border-bottom-0 mx-2 mx-md-3 pt-2 security-tabs-nav" id="securityTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'waf' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-waf" role="tab">
                        <i class="uil-shield-check me-1.5 font-size-16"></i>{{ __('WAF & Attacks') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'ddos' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-ddos" role="tab">
                        <i class="uil-thunderstorm me-1.5 font-size-16"></i>{{ __('DDoS & Rate Limits') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'firewall' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-firewall" role="tab">
                        <i class="uil-fire me-1.5 font-size-16"></i>{{ __('Firewall & IP Rules') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'hardening' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-hardening" role="tab">
                        <i class="uil-lock-alt me-1.5 font-size-16"></i>{{ __('Uploads & Anti-Inspect') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'cors' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-cors" role="tab">
                        <i class="uil-globe me-1.5 font-size-16"></i>{{ __('CORS & Origin Access') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $activeTab === 'monitor' ? 'active' : '' }}" 
                       data-bs-toggle="tab" href="#tab-monitor" role="tab">
                        <i class="uil-history me-1.5 font-size-16"></i>{{ __('Live Security Monitor') }}
                        @if ($stats['threats_today'] > 0)
                            <span class="badge bg-danger rounded-pill ms-2 font-size-11 px-2 py-0.5">{{ $stats['threats_today'] }}</span>
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-2 font-size-10 px-1.5 py-0.5">
                                <span class="spinner-grow spinner-grow-sm me-0.5" style="width: 5px; height: 5px;" role="status"></span>{{ __('Live') }}
                            </span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="securityTabsContent">
                {{-- TAB 1: WAF & Attack Defense --}}
                <div class="tab-pane fade {{ $activeTab === 'waf' ? 'show active' : '' }}" id="tab-waf" role="tabpanel">
                    <form action="{{ route('admin.security.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="tab" value="waf">

                        <div class="row g-4">
                            <div class="col-lg-8">
                                <h5 class="fw-bold mb-1">{{ __('Web Application Firewall (WAF)') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Deep-inspect incoming GET, POST, and JSON parameters to detect and intercept code injection attacks before execution.') }}
                                </p>

                                {{-- SQL Injection Defense --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-database text-primary me-1"></i> {{ __('SQL Injection (SQLi) Inspection') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Scans for signatures like UNION SELECT, OR 1=1, SLEEP(), and database drop commands.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="waf_sqli_enabled" value="1" id="wafSqli"
                                                {{ ($settings['waf_sqli_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- XSS Defense --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-brackets-curly text-warning me-1"></i> {{ __('Cross-Site Scripting (XSS) Shield') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Intercepts malicious script injections, inline JavaScript event triggers, and iframe injections.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="waf_xss_enabled" value="1" id="wafXss"
                                                {{ ($settings['waf_xss_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Path Traversal Defense --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-folder-lock text-info me-1"></i> {{ __('Path Traversal & LFI Defense') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Blocks directory traversal attempts (e.g. ../../etc/passwd) and PHP wrapper exploits.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="waf_path_traversal_enabled" value="1" id="wafTraversal"
                                                {{ ($settings['waf_path_traversal_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Honeypot Scanner Traps --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-bug text-danger me-1"></i> {{ __('Honeypot Scanner Probing Traps') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Sets traps on common scanner target paths (/.env, /wp-admin, /phpmyadmin) to automatically trap and auto-ban malicious bots.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="honeypot_traps_enabled" value="1" id="wafHoneypot"
                                                {{ ($settings['honeypot_traps_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card border rounded-3 p-4 bg-white shadow-none">
                                    <h6 class="fw-bold mb-3">{{ __('Action on Threat Detection') }}</h6>
                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="waf_action" id="actionAutoban" value="block_and_autoban"
                                                {{ ($settings['waf_action'] ?? 'block_and_autoban') === 'block_and_autoban' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold" for="actionAutoban">
                                                {{ __('Block & Auto-Ban IP (Recommended)') }}
                                            </label>
                                            <p class="text-muted font-size-12 mb-0 ms-1">
                                                {{ __('Terminates the request with 403 and automatically adds the offender IP to the blacklist.') }}
                                            </p>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="waf_action" id="actionBlockOnly" value="block_only"
                                                {{ ($settings['waf_action'] ?? '') === 'block_only' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold" for="actionBlockOnly">
                                                {{ __('Block Only (403 Forbidden)') }}
                                            </label>
                                            <p class="text-muted font-size-12 mb-0 ms-1">
                                                {{ __('Rejects the malicious request without automatically adding a persistent IP ban.') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="border-top pt-3 mt-3">
                                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                            <i class="uil-save me-1"></i> {{ __('Save WAF Settings') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- TAB 2: DDoS & Rate Limits --}}
                <div class="tab-pane fade {{ $activeTab === 'ddos' ? 'show active' : '' }}" id="tab-ddos" role="tabpanel">
                    <form action="{{ route('admin.security.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="tab" value="ddos">

                        <div class="row g-4">
                            <div class="col-lg-7">
                                <h5 class="fw-bold mb-1">{{ __('Traffic Flow & DDoS Defense') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Throttle high-frequency automated request floods and manage under-attack lockdown mode.') }}
                                </p>

                                {{-- Under Attack Mode --}}
                                <div class="card border border-danger border-2 rounded-3 mb-3 p-3 bg-soft-danger">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-danger">
                                                <i class="uil-shield-exclamation me-1"></i> {{ __('"Under Attack" Emergency Mode') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Instantly lock down public endpoints to aggressive rate limits and drop non-essential automated crawlers.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="under_attack_mode" value="1" id="underAttackMode"
                                                {{ ($settings['under_attack_mode'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Bad Bots Filter --}}
                                <div class="card border rounded-3 mb-4 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-robot text-primary me-1"></i> {{ __('Automated Exploit & Scanner Blocker') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Inspects User-Agent headers to block tools like sqlmap, nikto, dirbuster, masscan, and empty agents.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="block_bad_bots" value="1" id="blockBadBots"
                                                {{ ($settings['block_bad_bots'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Login Brute Force Defense --}}
                                <h5 class="fw-bold mb-1">{{ __('Login & Authentication Protection') }}</h5>
                                <p class="text-muted small mb-3">
                                    {{ __('Safeguard against password guessing, credential stuffing, and brute force login attempts.') }}
                                </p>

                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-lock-access text-danger me-1"></i> {{ __('Auto-Ban on Repeated Failed Logins') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Automatically blacklist the client IP when consecutive bad passwords exceed the threshold within 15 minutes.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="autoban_failed_logins_enabled" value="1" id="autobanLogins"
                                                {{ ($settings['autoban_failed_logins_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="card border rounded-3 p-4 bg-white shadow-none">
                                    <h6 class="fw-bold mb-3">{{ __('Rate Limit & Lockout Thresholds') }}</h6>
                                    
                                    {{-- Global Request Limit --}}
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">{{ __('Global Max Requests / Minute per IP') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="uil-tachometer-fast"></i></span>
                                            <input type="number" class="form-control" name="global_rate_limit_per_minute" min="10" max="1000"
                                                value="{{ $settings['global_rate_limit_per_minute'] ?? 120 }}">
                                            <span class="input-group-text font-size-12">{{ __('req/min') }}</span>
                                        </div>
                                        <span class="text-muted font-size-11">
                                            {{ __('Traffic exceeding this threshold triggers HTTP 429 Too Many Requests.') }}
                                        </span>
                                    </div>

                                    <hr class="my-3">

                                    {{-- Max Failed Logins --}}
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-danger">
                                            <i class="uil-shield-slash me-1"></i>{{ __('Max Failed Login Attempts before Ban') }}
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="uil-user-times"></i></span>
                                            <input type="number" class="form-control" name="autoban_login_threshold" min="2" max="20"
                                                value="{{ $settings['autoban_login_threshold'] ?? 5 }}">
                                            <span class="input-group-text font-size-12">{{ __('attempts') }}</span>
                                        </div>
                                        <span class="text-muted font-size-11">
                                            {{ __('Recommended: 3 to 5 attempts.') }}
                                        </span>
                                    </div>

                                    {{-- Auto-Ban Duration --}}
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">{{ __('Auto-Ban Blacklist Duration') }}</label>
                                        <select class="form-select" name="autoban_duration_hours">
                                            <option value="1" {{ ($settings['autoban_duration_hours'] ?? '') === '1' ? 'selected' : '' }}>{{ __('1 Hour') }}</option>
                                            <option value="6" {{ ($settings['autoban_duration_hours'] ?? '') === '6' ? 'selected' : '' }}>{{ __('6 Hours') }}</option>
                                            <option value="24" {{ ($settings['autoban_duration_hours'] ?? '24') === '24' ? 'selected' : '' }}>{{ __('24 Hours (Recommended)') }}</option>
                                            <option value="48" {{ ($settings['autoban_duration_hours'] ?? '') === '48' ? 'selected' : '' }}>{{ __('48 Hours') }}</option>
                                            <option value="168" {{ ($settings['autoban_duration_hours'] ?? '') === '168' ? 'selected' : '' }}>{{ __('7 Days') }}</option>
                                        </select>
                                    </div>

                                    {{-- Temporary Lockout Minutes --}}
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">{{ __('Temporary Lockout Window') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="uil-clock"></i></span>
                                            <input type="number" class="form-control" name="login_lockout_minutes" min="1" max="60"
                                                value="{{ $settings['login_lockout_minutes'] ?? 1 }}">
                                            <span class="input-group-text font-size-12">{{ __('minutes') }}</span>
                                        </div>
                                        <span class="text-muted font-size-11">
                                            {{ __('Cooldown period applied to standard login throttling.') }}
                                        </span>
                                    </div>

                                    <div class="border-top pt-3 mt-3">
                                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                            <i class="uil-save me-1"></i> {{ __('Save DDoS & Login Settings') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- TAB 3: Firewall & IP Rules --}}
                <div class="tab-pane fade {{ $activeTab === 'firewall' ? 'show active' : '' }}" id="tab-firewall" role="tabpanel">
                    <div class="row g-4 mb-4">
                        {{-- Add Blocked IP Form --}}
                        <div class="col-lg-6">
                            <div class="card border rounded-3 p-4 h-100 bg-light">
                                <h6 class="fw-bold mb-1 text-danger">
                                    <i class="uil-ban me-1"></i> {{ __('Add IP to Blacklist') }}
                                </h6>
                                <p class="text-muted font-size-12 mb-3">
                                    {{ __('Directly block any single IP or CIDR subnet. Blocked visitors receive a 403 Access Denied screen.') }}
                                </p>
                                
                                <form action="{{ route('admin.security.block-ip') }}" method="POST">
                                    @csrf
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-7">
                                            <label class="form-label small fw-semibold">{{ __('IP Address / Subnet') }}</label>
                                            <input type="text" class="form-control form-control-sm" name="ip_address" required 
                                                placeholder="e.g. 192.168.1.100">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold">{{ __('Ban Duration') }}</label>
                                            <select class="form-select form-select-sm" name="duration_hours">
                                                <option value="0">{{ __('Permanent') }}</option>
                                                <option value="1">{{ __('1 Hour') }}</option>
                                                <option value="24" selected>{{ __('24 Hours') }}</option>
                                                <option value="168">{{ __('7 Days') }}</option>
                                                <option value="720">{{ __('30 Days') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">{{ __('Reason for Ban') }}</label>
                                        <input type="text" class="form-control form-control-sm" name="reason" 
                                            placeholder="e.g. Repeated malicious attack probe">
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                                        <i class="uil-shield-slash me-1"></i> {{ __('Block This IP') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Admin Whitelist Card --}}
                        <div class="col-lg-6">
                            <div class="card border rounded-3 p-4 h-100 bg-light">
                                <form action="{{ route('admin.security.settings.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="tab" value="firewall">

                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-primary">
                                                <i class="uil-check-circle me-1"></i> {{ __('Admin IP Whitelist') }}
                                            </h6>
                                            <p class="text-muted font-size-12 mb-0">
                                                {{ __('Restrict access to /admin strictly to approved office/VPN IPs.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="admin_ip_whitelist_enabled" value="1" id="whitelistToggle"
                                                {{ ($settings['admin_ip_whitelist_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">{{ __('Allowed IP Addresses (one per line)') }}</label>
                                        <textarea class="form-control font-monospace font-size-12" name="admin_whitelisted_ips" rows="3"
                                            placeholder="127.0.0.1&#10;203.0.113.15">{{ $whitelistedText }}</textarea>
                                        <span class="text-muted font-size-11">
                                            {{ __('Your current IP:') }} <code>{{ request()->ip() }}</code>
                                        </span>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                        <i class="uil-save me-1"></i> {{ __('Update Whitelist') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Commercial VPN, Datacenter Proxy & Tor Defense --}}
                    <div class="card border rounded-3 p-3 mb-4 bg-light">
                        <form action="{{ route('admin.security.settings.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="firewall">

                            <div class="row align-items-center">
                                <div class="col-lg-8">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0 text-dark">
                                            <i class="uil-globe me-1 text-primary"></i> {{ __('Commercial VPN, Anonymous Proxy & Tor Defense') }}
                                        </h6>
                                        <span class="badge bg-soft-purple text-purple font-size-11">{{ __('GeoIP & ASN Intelligence') }}</span>
                                    </div>
                                    <p class="text-muted font-size-12 mb-0">
                                        {{ __('Detects and drops incoming connections originating from cloud datacenters (AWS, DigitalOcean, Hetzner), commercial VPN tunnels (NordVPN, ExpressVPN), and Tor exit nodes commonly utilized by vulnerability scanners and malicious actors.') }}
                                    </p>
                                </div>
                                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-flex justify-content-lg-end align-items-center gap-4">
                                    <div class="form-check form-switch form-switch-md">
                                        <input class="form-check-input" type="checkbox" name="block_vpn_proxies" value="1" id="blockVpnToggle"
                                            {{ ($settings['block_vpn_proxies'] ?? '0') === '1' ? 'checked' : '' }} onchange="this.form.submit()">
                                        <label class="form-check-label fw-semibold font-size-12" for="blockVpnToggle">
                                            {{ __('Block VPN / Datacenter Proxies') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Active Blocked IPs Table --}}
                    <div class="card border rounded-3 security-table-container">
                        <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="mb-0 fw-bold">
                                    <i class="uil-list-ul me-1"></i> {{ __('Active Blocked IPs') }}
                                    <span class="badge bg-secondary rounded-pill ms-1 font-size-11">{{ $blockedIps->count() }}</span>
                                </h6>
                                <span class="resize-hint-badge d-none d-md-inline-flex">
                                    <i class="uil-arrows-h"></i> {{ __('Adjustable columns') }}
                                </span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-blocked-cols" title="{{ __('Reset column widths') }}">
                                <i class="uil-history me-1"></i> {{ __('Reset Columns') }}
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <div class="security-table-responsive">
                                <table id="datatable-blocked-ips" class="table table-bordered table-resizable align-middle">
                                    <thead class="table-light font-size-12 text-uppercase text-muted">
                                        <tr>
                                            <th>{{ __('IP Address') }}</th>
                                            <th>{{ __('Threat Type') }}</th>
                                            <th>{{ __('Reason') }}</th>
                                            <th>{{ __('Blocked By') }}</th>
                                            <th>{{ __('Strikes') }}</th>
                                            <th>{{ __('Expires At') }}</th>
                                            <th class="text-end">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                     <tbody>
                                        @foreach ($blockedIps as $ban)
                                            <tr>
                                                <td class="fw-bold font-monospace text-danger">
                                                    <i class="uil-ban text-danger me-1"></i> {{ $ban->ip_address }}
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-danger text-danger text-uppercase font-size-11">
                                                        {{ $ban->threat_type }}
                                                    </span>
                                                </td>
                                                <td class="small text-muted">{{ $ban->reason ?? 'N/A' }}</td>
                                                <td class="small">{{ $ban->blocked_by ?? 'System' }}</td>
                                                <td>
                                                    <span class="badge bg-soft-dark text-dark font-size-11">{{ $ban->strike_count }}</span>
                                                </td>
                                                <td class="small">
                                                    @if ($ban->expires_at)
                                                        {{ $ban->expires_at->format('M d, Y H:i') }}
                                                        @if ($ban->expires_at->isPast())
                                                            <span class="badge bg-soft-secondary text-secondary ms-1">{{ __('Expired') }}</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-soft-danger text-danger">{{ __('Permanent') }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <form action="{{ route('admin.security.unblock-ip', $ban->id) }}" method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to unblock IP {{ $ban->ip_address }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2 font-size-12">
                                                            <i class="uil-check me-1"></i> {{ __('Unblock') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: Uploads & Anti-Inspect --}}
                <div class="tab-pane fade {{ $activeTab === 'hardening' ? 'show active' : '' }}" id="tab-hardening" role="tabpanel">
                    <form action="{{ route('admin.security.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="tab" value="hardening">

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h5 class="fw-bold mb-1">{{ __('File Attachment & Upload Security') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Prevent malicious web shells, executable scripts, and disguised Trojan payloads from entering the storage disk.') }}
                                </p>

                                {{-- Strict MIME Verification --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-file-shield-alt text-primary me-1"></i> {{ __('Strict MIME Magic-Byte Verification') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Inspects the true binary header of uploads rather than trusting client file extensions.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="strict_file_mime_check" value="1" id="mimeCheck"
                                                {{ ($settings['strict_file_mime_check'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Executable Blacklist --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-file-slash text-danger me-1"></i> {{ __('Block Dangerous Executable Extensions') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Permanently rejects .php, .phtml, .exe, .sh, .bat, .js, .phar files.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="block_dangerous_extensions" value="1" id="dangerousExtCheck"
                                                {{ ($settings['block_dangerous_extensions'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- HTTP Security Headers --}}
                                <h5 class="fw-bold mt-4 mb-1">{{ __('HTTP Security Headers') }}</h5>
                                <p class="text-muted small mb-3">
                                    {{ __('Enforce defense-in-depth headers on all web responses.') }}
                                </p>

                                <div class="card border rounded-3 p-3 bg-light">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">{{ __('X-Frame-Options (Clickjacking Defense)') }}</label>
                                        <select class="form-select form-select-sm" name="x_frame_options">
                                            <option value="SAMEORIGIN" {{ ($settings['x_frame_options'] ?? 'SAMEORIGIN') === 'SAMEORIGIN' ? 'selected' : '' }}>
                                                {{ __('SAMEORIGIN (Allow within own domain)') }}
                                            </option>
                                            <option value="DENY" {{ ($settings['x_frame_options'] ?? '') === 'DENY' ? 'selected' : '' }}>
                                                {{ __('DENY (Disallow all iframes)') }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small fw-semibold">{{ __('X-Content-Type-Options: nosniff') }}</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="x_content_type_options" value="1"
                                                {{ ($settings['x_content_type_options'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="small fw-semibold">{{ __('HSTS (Force HTTPS max-age)') }}</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="hsts_enabled" value="1"
                                                {{ ($settings['hsts_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h5 class="fw-bold mb-1">{{ __('Frontend Anti-Inspection Defense ("Inspector Active")') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Prevent unauthorized users from right-clicking, inspecting elements, and debugging the POS / Admin frontend.') }}
                                </p>

                                {{-- Anti-Inspect Master Switch --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-eye-slash text-danger me-1"></i> {{ __('Enable Anti-Inspection Shield') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Activates client-side tamper and inspection prevention guards.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="anti_inspection_enabled" value="1" id="antiInspectMaster"
                                                {{ ($settings['anti_inspection_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Disable Right Click --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-mouse text-primary me-1"></i> {{ __('Disable Right-Click Context Menu') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Disables browser right-click menu preventing "Inspect Element" shortcut.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="disable_right_click" value="1"
                                                {{ ($settings['disable_right_click'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Disable DevTools Shortcuts --}}
                                <div class="card border rounded-3 mb-4 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-keyboard text-warning me-1"></i> {{ __('Disable DevTools Shortcuts (F12, Ctrl+Shift+I, Ctrl+U)') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Intercepts keyboard combinations used to open browser DevTools and view source code.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="disable_devtools_keys" value="1"
                                                {{ ($settings['disable_devtools_keys'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                    <i class="uil-save me-1"></i> {{ __('Save Upload & Anti-Inspect Settings') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- TAB 5: CORS & API Origin Access --}}
                <div class="tab-pane fade {{ $activeTab === 'cors' ? 'show active' : '' }}" id="tab-cors" role="tabpanel">
                    <form action="{{ route('admin.security.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="tab" value="cors">

                        <div class="row g-4">
                            <div class="col-lg-8">
                                <h5 class="fw-bold mb-1">{{ __('Cross-Origin Resource Sharing (CORS)') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Manage browser access controls, authorized frontend web domains, Next.js portal URLs, and mobile/POS API client origins.') }}
                                </p>

                                {{-- CORS Enabled Switch --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-globe text-primary me-1"></i> {{ __('CORS Policy Enforcement') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Enable browser cross-origin preflight handling and header protection for external frontend clients.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="cors_enabled" value="1"
                                                {{ ($settings['cors_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                {{-- Allowed Origins Textarea --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <i class="uil-link text-primary me-1"></i> {{ __('Authorized CORS Origins (Whitelist)') }}
                                    </h6>
                                    <p class="text-muted small mb-3">
                                        {{ __('Specify one allowed origin URL per line (e.g. http://localhost:3000 or https://portal.yourstore.com). Wildcard (*) is supported, but modern browsers require explicit origins when credentials/cookies are enabled.') }}
                                    </p>

                                    {{-- Quick Presets --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-add-cors-preset" data-origin="http://localhost:3000">
                                            + Next.js (localhost:3000)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-add-cors-preset" data-origin="http://localhost:8880">
                                            + Nginx (localhost:8880)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-add-cors-preset" data-origin="http://localhost:8882">
                                            + UI5 App (localhost:8882)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-add-cors-preset" data-origin="http://127.0.0.1:3000">
                                            + 127.0.0.1:3000
                                        </button>
                                    </div>

                                    <textarea name="cors_allowed_origins" id="cors_allowed_origins" rows="6" class="form-control font-monospace font-size-13"
                                        placeholder="http://localhost:3000&#10;http://localhost:8880&#10;https://app.example.com">{{ $corsOriginsText }}</textarea>
                                </div>

                                {{-- Advanced Headers & Methods --}}
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border rounded-3 p-3 bg-light h-100">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-exchange text-primary me-1"></i> {{ __('Allowed HTTP Methods') }}
                                            </h6>
                                            <p class="text-muted small mb-2">{{ __('HTTP methods accepted across origins.') }}</p>
                                            <input type="text" name="cors_allowed_methods" class="form-control font-monospace font-size-12"
                                                value="{{ $settings['cors_allowed_methods'] ?? 'GET, POST, PUT, PATCH, DELETE, OPTIONS' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border rounded-3 p-3 bg-light h-100">
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <i class="uil-clock-eight text-primary me-1"></i> {{ __('Preflight Cache (Max Age)') }}
                                            </h6>
                                            <p class="text-muted small mb-2">{{ __('Seconds browsers may cache preflight checks.') }}</p>
                                            <div class="input-group">
                                                <input type="number" name="cors_max_age" class="form-control"
                                                    value="{{ $settings['cors_max_age'] ?? '7200' }}" min="0" step="60">
                                                <span class="input-group-text">{{ __('sec') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border rounded-3 mt-3 p-3 bg-light">
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <i class="uil-brackets-curly text-primary me-1"></i> {{ __('Allowed Request Headers') }}
                                    </h6>
                                    <p class="text-muted small mb-2">{{ __('Headers allowed in incoming cross-origin requests.') }}</p>
                                    <input type="text" name="cors_allowed_headers" class="form-control font-monospace font-size-12"
                                        value="{{ $settings['cors_allowed_headers'] ?? 'Content-Type, Authorization, X-Requested-With, X-Tenant, X-Tenant-Id, X-Store, X-Store-Name, Accept, Origin' }}">
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <h5 class="fw-bold mb-1">{{ __('Origin Security') }}</h5>
                                <p class="text-muted small mb-4">
                                    {{ __('Credential handling & authentication policies.') }}
                                </p>

                                {{-- Supports Credentials --}}
                                <div class="card border rounded-3 mb-3 p-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="me-3">
                                            <h6 class="mb-1 fw-bold text-dark">{{ __('Allow Credentials') }}</h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Transmits cookies, auth tokens, and session cookies with CORS requests.') }}
                                            </p>
                                        </div>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="cors_supports_credentials" value="1"
                                                {{ ($settings['cors_supports_credentials'] ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info border-0 rounded-3 mb-3 small">
                                    <i class="uil-info-circle me-1"></i>
                                    <strong>{{ __('Note for Next.js & Microservices:') }}</strong>
                                    {{ __('When credentials are enabled, wildcard (*) cannot be used by browsers. You must add the exact origin (e.g. http://localhost:3000).') }}
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                    <i class="uil-save me-1"></i> {{ __('Save CORS & Origin Settings') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- TAB 6: Live Security Monitor --}}
                <div class="tab-pane fade {{ $activeTab === 'monitor' ? 'show active' : '' }}" id="tab-monitor" role="tabpanel">

                    {{-- Telegram Real-Time Incident Alerts Card --}}
                    <div class="card border border-primary-subtle rounded-3 mb-4 bg-light shadow-sm">
                        <div class="card-body p-3">
                            <form action="{{ route('admin.security.settings.update') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="tab" value="monitor">

                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title bg-soft-primary text-primary rounded-circle font-size-18">
                                                <i class="uil-telegram-alt"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark">
                                                {{ __('Live Threat Auto-Send to Telegram Alert') }}
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                {{ __('Instantly dispatch notifications to your Telegram channel or group whenever a security threat or attack is intercepted.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="telegram_security_alerts_enabled" value="1" id="telegram_security_alerts_enabled"
                                                {{ ($settings['telegram_security_alerts_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold text-dark small" for="telegram_security_alerts_enabled">
                                                {{ __('Auto-Alerts Active') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-dark">{{ __('Min Severity to Alert') }}</label>
                                        <select name="telegram_security_min_severity" class="form-select form-select-sm">
                                            <option value="all" {{ ($settings['telegram_security_min_severity'] ?? 'medium') === 'all' ? 'selected' : '' }}>
                                                {{ __('All Incidents (Low, Medium, High, Critical)') }}
                                            </option>
                                            <option value="medium" {{ ($settings['telegram_security_min_severity'] ?? 'medium') === 'medium' ? 'selected' : '' }}>
                                                {{ __('Medium, High & Critical (Recommended)') }}
                                            </option>
                                            <option value="high" {{ ($settings['telegram_security_min_severity'] ?? 'medium') === 'high' ? 'selected' : '' }}>
                                                {{ __('High & Critical Only') }}
                                            </option>
                                            <option value="critical" {{ ($settings['telegram_security_min_severity'] ?? 'medium') === 'critical' ? 'selected' : '' }}>
                                                {{ __('Critical Only') }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-dark">{{ __('Bot Token Override (Optional)') }}</label>
                                        <input type="text" name="telegram_security_bot_token" id="telegram_security_bot_token" class="form-control form-control-sm font-monospace"
                                            placeholder="{{ __('Default: Global Bot Token') }}" value="{{ $settings['telegram_security_bot_token'] ?? '' }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-dark">{{ __('Chat ID Override (Optional)') }}</label>
                                        <input type="text" name="telegram_security_chat_id" id="telegram_security_chat_id" class="form-control form-control-sm font-monospace"
                                            placeholder="{{ __('Default: Global Chat ID') }}" value="{{ $settings['telegram_security_chat_id'] ?? '' }}">
                                    </div>
                                    <div class="col-md-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm fw-semibold flex-grow-1">
                                            <i class="uil-save me-1"></i> {{ __('Save Alert Settings') }}
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" id="btn-test-telegram-alert" title="{{ __('Send a test alert message now') }}">
                                            <i class="uil-telegram me-1"></i> {{ __('Test Alert') }}
                                        </button>
                                    </div>
                                </div>
                                <div id="test-telegram-alert-feedback" class="mt-2 d-none"></div>
                            </form>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="fw-bold mb-0">{{ __('Live Threat & Incident Log') }}</h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill">{{ $logs->count() }} {{ __('incidents') }}</span>
                            <span class="resize-hint-badge d-none d-md-inline-flex">
                                <i class="uil-arrows-h"></i> {{ __('Adjustable columns: drag borders to resize') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            {{-- Threat Type Filter Form --}}
                            <form action="{{ route('admin.security.index') }}" method="GET" class="d-flex gap-2">
                                <input type="hidden" name="tab" value="monitor">
                                <select name="threat_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">{{ __('All Threat Types') }}</option>
                                    @foreach ($threatTypes as $type)
                                        <option value="{{ $type }}" {{ $selectedThreatType === $type ? 'selected' : '' }}>
                                            {{ strtoupper($type) }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>

                            {{-- Reset Columns Button --}}
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset-security-cols" title="{{ __('Reset column widths to default') }}">
                                <i class="uil-history me-1"></i> {{ __('Reset Columns') }}
                            </button>

                            {{-- Load Demo Logs (Local Dev Only) --}}
                            @if (app()->environment('local'))
                                <form action="{{ route('admin.security.seed-samples') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info btn-sm" title="{{ __('Load realistic sample incidents for testing') }}">
                                        <i class="uil-flask me-1"></i> {{ __('Load Demo Logs') }}
                                    </button>
                                </form>
                            @endif

                            {{-- Clear Logs --}}
                            @if ($logs->count() > 0)
                                <form action="{{ route('admin.security.clear-logs') }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure you want to clear all security logs?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="uil-trash-alt me-1"></i> {{ __('Clear Logs') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Logs Table --}}
                    <div class="card border rounded-3 security-table-container">
                        <div class="card-body p-3">
                            <div class="security-table-responsive">
                                <table id="datatable-security-logs" class="table table-bordered table-resizable align-middle font-size-13">
                                    <thead class="table-light font-size-11 text-uppercase text-muted">
                                        <tr>
                                            <th>{{ __('Incident ID') }}</th>
                                            <th>{{ __('Store Name') }}</th>
                                            <th>{{ __('Time') }}</th>
                                            <th>{{ __('Threat Type') }}</th>
                                            <th>{{ __('Severity') }}</th>
                                            <th>{{ __('Client IP') }}</th>
                                            <th>{{ __('Location & Network') }}</th>
                                            <th>{{ __('Target Route') }}</th>
                                            <th>{{ __('Payload / Detail') }}</th>
                                            <th>{{ __('Action') }}</th>
                                            <th class="text-end">{{ __('Quick Control') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($logs as $log)
                                            <tr>
                                                <td class="font-monospace fw-bold text-dark">
                                                    {{ $log->incident_id }}
                                                </td>
                                                <td>
                                                    @if ($log->tenant_id)
                                                        <div class="d-inline-flex flex-column align-items-start">
                                                            <span class="badge bg-soft-info text-info border border-info border-opacity-25 px-2 py-1 d-inline-flex align-items-center gap-1 font-size-12" title="{{ __('Store Name: :store', ['store' => $log->store_name]) }}">
                                                                <i class="uil-store"></i>
                                                                <strong class="text-truncate" style="max-width: 140px;">{{ $log->store_name }}</strong>
                                                            </span>
                                                            @if ($log->tenant_id !== $log->store_name)
                                                                <small class="text-muted font-monospace font-size-10 ps-1 mt-0.5">{{ $log->tenant_id }}</small>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="badge bg-light text-muted border border-secondary border-opacity-25 px-2 py-1 d-inline-flex align-items-center gap-1 font-size-12" title="{{ __('Central Platform / Infrastructure Request') }}">
                                                            <i class="uil-globe"></i>
                                                            <span class="fw-semibold">{{ __('Central (All Stores)') }}</span>
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="small text-muted text-nowrap">
                                                    {{ $log->created_at->diffForHumans() }}
                                                </td>
                                                <td>
                                                    @php
                                                        $badgeClass = match($log->threat_type) {
                                                            'sql_injection' => 'bg-danger text-white',
                                                            'honeypot_trap' => 'bg-dark text-white',
                                                            'cross_site_scripting' => 'bg-warning text-dark',
                                                            'bad_bot_scanner' => 'bg-info text-white',
                                                            'brute_force_login' => 'bg-danger text-white',
                                                            'vpn_proxy_blocked' => 'bg-purple text-white',
                                                            default => 'bg-secondary text-white',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $badgeClass }} font-size-11 text-uppercase">
                                                        {{ $log->threat_type }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($log->severity === 'critical')
                                                        <span class="badge bg-soft-danger text-danger">{{ __('CRITICAL') }}</span>
                                                    @elseif ($log->severity === 'high')
                                                        <span class="badge bg-soft-warning text-warning">{{ __('HIGH') }}</span>
                                                    @else
                                                        <span class="badge bg-soft-info text-info">{{ strtoupper($log->severity) }}</span>
                                                    @endif
                                                </td>
                                                <td class="font-monospace fw-semibold text-danger">
                                                    {{ $log->ip_address }}
                                                </td>
                                                <td class="small">
                                                    <div class="d-flex align-items-center gap-1 flex-nowrap">
                                                        <span class="fs-6">{{ $log->flag_emoji }}</span>
                                                        <span class="fw-bold text-dark font-monospace">{{ $log->country_code ?: 'UN' }}</span>
                                                        @if ($log->is_vpn)
                                                            <span class="badge bg-soft-purple text-purple font-size-10 font-monospace py-0 px-1" title="{{ __('Commercial Datacenter / VPN IP Detected') }}">
                                                                <i class="uil-shield"></i> VPN
                                                            </span>
                                                        @endif
                                                        @if ($log->google_maps_url)
                                                            <a href="{{ $log->google_maps_url }}" target="_blank" rel="noopener noreferrer" 
                                                               class="badge bg-soft-primary text-primary text-decoration-none border border-primary border-opacity-25 px-1.5 py-0 d-inline-flex align-items-center gap-1 flex-shrink-0 map-link-hover ms-auto" 
                                                               title="{{ __('Open :coords (:loc) on Google Maps', ['coords' => $log->coordinates_display ?: 'GPS Coordinates', 'loc' => ($log->city ? $log->city . ', ' : '') . ($log->country_name ?: '')]) }}">
                                                                <i class="uil-map-marker text-danger font-size-11"></i>
                                                                <span class="font-size-10 fw-semibold">{{ __('Map') }}</span>
                                                                <i class="uil-external-link-alt font-size-9 opacity-75"></i>
                                                            </a>
                                                        @else
                                                            <span class="badge bg-light text-muted font-size-10 py-0 px-1 ms-auto" title="{{ __('Private LAN Subnet - Local Address') }}">
                                                                <i class="uil-server-network"></i> LAN
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @php
                                                        $locationLabel = ($log->city && $log->city !== 'Unknown City' ? $log->city . ', ' : '') . ($log->country_name ?: __('Unknown'));
                                                    @endphp
                                                    <div class="text-muted font-size-11 text-truncate mt-1" title="{{ $locationLabel }} @if($log->coordinates_display) • {{ $log->coordinates_display }}@endif">
                                                        @if ($log->google_maps_url)
                                                            <a href="{{ $log->google_maps_url }}" target="_blank" rel="noopener noreferrer" class="text-muted text-decoration-none hover-primary" title="{{ __('Open :coords on Google Maps', ['coords' => $log->coordinates_display ?: $locationLabel]) }}">
                                                                {{ $locationLabel }}
                                                                @if ($log->coordinates_display)
                                                                    <span class="text-muted opacity-75 font-size-10 ms-1 font-monospace">({{ $log->coordinates_display }})</span>
                                                                @endif
                                                            </a>
                                                        @else
                                                            {{ $locationLabel }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="small font-monospace" title="{{ $log->request_url }}">
                                                    <div class="d-flex align-items-center gap-1" style="min-width: 0;">
                                                        <span class="badge bg-light text-dark flex-shrink-0">{{ $log->request_method }}</span>
                                                        <span class="text-truncate flex-grow-1" title="{{ $log->request_url }}">{{ $log->request_url }}</span>
                                                    </div>
                                                </td>
                                                <td class="small text-muted" title="{{ $log->payload }}">
                                                    <div class="d-flex align-items-center justify-content-between gap-1" style="min-width: 0;">
                                                        <code class="text-truncate flex-grow-1 text-danger font-monospace" style="user-select: all;" title="{{ $log->payload }}">{{ $log->payload ?? 'N/A' }}</code>
                                                        @if ($log->payload)
                                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted flex-shrink-0" onclick="navigator.clipboard.writeText('{{ addslashes($log->payload) }}');" title="Copy payload">
                                                                <i class="uil-copy font-size-12"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-dark text-dark text-capitalize font-size-11">
                                                        {{ $log->action_taken }}
                                                    </span>
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    <form action="{{ route('admin.security.quick-block', $log->id) }}" method="POST" class="d-inline"
                                                        onsubmit="return confirm('Block IP {{ $log->ip_address }} globally across all tenants?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger py-1 px-2 font-size-11 fw-semibold" title="{{ __('Block IP globally across all tenants and central infrastructure') }}">
                                                            <i class="uil-shield-slash me-1"></i> {{ __('Block IP') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        // Initialize Security Incident Logs DataTable
        let securityTable = null;
        let blockedTable = null;

        if ($('#datatable-security-logs').length) {
            securityTable = $('#datatable-security-logs').DataTable({
                responsive: false,
                autoWidth: false,
                order: [], // Keep server order (latest incidents first)
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                columnDefs: [
                    { orderable: false, targets: [10] } // Action quick-block column
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "{{ __('Search incidents, IPs, routes, payloads...') }}",
                    emptyTable: '<div class="text-center py-5 text-muted"><i class="uil-shield-check fs-1 text-success d-block mb-2"></i><h6 class="fw-bold mb-1">{{ __("No Security Threats Recorded") }}</h6><p class="small mb-0 text-muted">{{ __("Your application is actively protected by WAF and firewall layers.") }}</p></div>'
                }
            });
        }

        // Initialize Blocked IPs DataTable
        if ($('#datatable-blocked-ips').length) {
            blockedTable = $('#datatable-blocked-ips').DataTable({
                responsive: false,
                autoWidth: false,
                order: [], // Keep server order
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                columnDefs: [
                    { orderable: false, targets: [6] } // Unblock button column
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "{{ __('Search blocked IPs or reasons...') }}",
                    emptyTable: '<div class="text-center py-4 text-muted"><i class="uil-shield-check fs-2 text-success d-block mb-2"></i><div class="fw-semibold text-muted">{{ __("No IPs currently blocked. Firewall is clear.") }}</div></div>'
                }
            });
        }

        $('.dataTables_length select').addClass('form-select form-select-sm');

        // Interactive Column Resizing Engine
        function makeTableResizable(tableSelector, storageKey, defaultWidths) {
            const $table = $(tableSelector);
            if (!$table.length) return null;

            const table = $table[0];
            const $container = $table.closest('.col-sm-12').length ? $table.closest('.col-sm-12') : $table.closest('.security-table-responsive');
            const container = $container[0] || $table.parent()[0];

            let guide = container.querySelector('.table-resize-guide');
            if (!guide) {
                guide = document.createElement('div');
                guide.className = 'table-resize-guide';
                container.appendChild(guide);
            }

            let savedWidths = {};
            try {
                if (storageKey) {
                    savedWidths = JSON.parse(localStorage.getItem(storageKey)) || {};
                }
            } catch (e) {
                savedWidths = {};
            }

            const headers = table.querySelectorAll('thead th');

            function applyWidths() {
                let totalWidth = 0;
                headers.forEach((th, index) => {
                    let w = savedWidths[index] || (defaultWidths && defaultWidths[index]) || 120;
                    w = Math.max(w, 60);
                    th.style.setProperty('width', w + 'px', 'important');
                    th.style.setProperty('min-width', w + 'px', 'important');
                    th.style.setProperty('max-width', w + 'px', 'important');
                    totalWidth += w;
                });
                table.style.setProperty('width', totalWidth + 'px', 'important');
                table.style.setProperty('min-width', totalWidth + 'px', 'important');
                table.style.setProperty('max-width', 'none', 'important');
            }

            function saveWidths() {
                if (!storageKey) return;
                const widths = {};
                headers.forEach((th, i) => {
                    widths[i] = parseInt(th.style.width) || th.offsetWidth;
                });
                try {
                    localStorage.setItem(storageKey, JSON.stringify(widths));
                } catch (e) {}
            }

            applyWidths();
            $table.on('draw.dt', function () {
                applyWidths();
            });

            headers.forEach((th, index) => {
                const oldResizer = th.querySelector('.col-resizer');
                if (oldResizer) oldResizer.remove();

                const resizer = document.createElement('div');
                resizer.className = 'col-resizer';
                resizer.title = "{{ __('Drag to resize column • Double-click to auto-fit') }}";
                th.appendChild(resizer);

                // Double click auto-fit
                resizer.addEventListener('dblclick', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    th.style.width = 'auto';
                    th.style.minWidth = 'auto';
                    th.style.maxWidth = 'none';
                    const naturalWidth = Math.max(th.scrollWidth + 32, 70);
                    th.style.width = naturalWidth + 'px';
                    th.style.minWidth = naturalWidth + 'px';
                    th.style.maxWidth = naturalWidth + 'px';

                    let total = 0;
                    headers.forEach(h => {
                        total += parseInt(h.style.width) || h.offsetWidth;
                    });
                    table.style.width = total + 'px';
                    table.style.minWidth = total + 'px';
                    saveWidths();
                });

                // Prevent triggering column sort
                resizer.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                });

                // Dragging logic
                resizer.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const startX = e.pageX;
                    const startWidth = parseInt(th.style.width) || th.offsetWidth;
                    resizer.classList.add('resizing');
                    document.body.classList.add('is-col-resizing');

                    guide.style.display = 'block';
                    guide.style.height = table.offsetHeight + 'px';
                    guide.style.left = (th.offsetLeft + startWidth) + 'px';

                    function onMouseMove(moveEvent) {
                        const diffX = moveEvent.pageX - startX;
                        const newWidth = Math.max(60, startWidth + diffX);
                        th.style.setProperty('width', newWidth + 'px', 'important');
                        th.style.setProperty('min-width', newWidth + 'px', 'important');

                        guide.style.left = (th.offsetLeft + newWidth) + 'px';

                        let total = 0;
                        headers.forEach(h => {
                            total += parseInt(h.style.width) || h.offsetWidth;
                        });
                        table.style.setProperty('width', total + 'px', 'important');
                        table.style.setProperty('min-width', total + 'px', 'important');
                    }

                    function onMouseUp() {
                        resizer.classList.remove('resizing');
                        document.body.classList.remove('is-col-resizing');
                        guide.style.display = 'none';

                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);

                        saveWidths();
                    }

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            });

            return {
                reset: function () {
                    if (storageKey) {
                        try { localStorage.removeItem(storageKey); } catch (e) {}
                    }
                    savedWidths = {};
                    applyWidths();
                }
            };
        }

        // Initialize Resizable Columns for Security Logs
        // Default widths: Incident ID: 140, Store Name: 160, Time: 110, Threat: 170, Severity: 90, IP: 140, Geo: 230, Route: 200, Payload: 260, Action: 100, Quick Control: 120 (Total: 1,720px)
        const securityResizer = makeTableResizable(
            '#datatable-security-logs',
            'vpos_security_logs_widths',
            [140, 160, 110, 170, 90, 140, 230, 200, 260, 100, 120]
        );

        $('#btn-reset-security-cols').on('click', function () {
            if (securityResizer) {
                securityResizer.reset();
            }
        });

        // Initialize Resizable Columns for Blocked IPs
        // Default widths: IP: 160, Threat: 180, Reason: 240, Blocked By: 130, Strikes: 90, Expires: 150, Action: 120 (Total: 1,070px)
        const blockedResizer = makeTableResizable(
            '#datatable-blocked-ips',
            'vpos_blocked_ips_widths',
            [160, 180, 240, 130, 90, 150, 120]
        );

        $('#btn-reset-blocked-cols').on('click', function () {
            if (blockedResizer) {
                blockedResizer.reset();
            }
        });

        // Recalculate and re-apply widths when tabs switch & update URL ?tab=...
        $('a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (securityTable) securityTable.columns.adjust();
            if (blockedTable) blockedTable.columns.adjust();

            var href = $(e.target).attr('href');
            if (href && href.startsWith('#tab-')) {
                var tabName = href.replace('#tab-', '');
                if (window.history && window.history.replaceState) {
                    var newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('tab', tabName);
                    window.history.replaceState(null, '', newUrl.toString());
                }
            }
        });

        // CORS Preset Origins Quick Add
        $(document).on('click', '.js-add-cors-preset', function () {
            var origin = $(this).data('origin');
            var $textarea = $('#cors_allowed_origins');
            var current = $textarea.val().trim();
            var lines = current ? current.split('\n').map(function (s) { return s.trim(); }).filter(Boolean) : [];
            if (lines.indexOf(origin) === -1) {
                lines.push(origin);
                $textarea.val(lines.join('\n'));
            }
        });

        // Test Telegram Alert Button
        $('#btn-test-telegram-alert').on('click', function () {
            var $btn = $(this);
            var $fb = $('#test-telegram-alert-feedback');
            var botToken = $('#telegram_security_bot_token').val();
            var chatId = $('#telegram_security_chat_id').val();

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sending...');
            $fb.addClass('d-none').removeClass('alert alert-success alert-danger');

            $.ajax({
                url: "{{ route('admin.security.test-telegram') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    bot_token: botToken,
                    chat_id: chatId
                },
                success: function (res) {
                    $btn.prop('disabled', false).html('<i class="uil-telegram me-1"></i> Test Alert');
                    $fb.removeClass('d-none').addClass('alert alert-success p-2 small mt-2')
                        .html('<i class="uil-check-circle me-1"></i> ' + (res.message || 'Telegram alert successfully sent!'));
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).html('<i class="uil-telegram me-1"></i> Test Alert');
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to send test Telegram alert.';
                    $fb.removeClass('d-none').addClass('alert alert-danger p-2 small mt-2')
                        .html('<i class="uil-exclamation-triangle me-1"></i> ' + msg);
                }
            });
        });
    });
</script>
@endpush
