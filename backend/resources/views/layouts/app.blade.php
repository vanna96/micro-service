<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @php
        $themeIconsCss = 'minible/assets/css/icons.min.css';
        $themeAppJs = 'minible/assets/js/app.js';
        $themeIconsVersion = file_exists(public_path($themeIconsCss)) ? filemtime(public_path($themeIconsCss)) : time();
        $themeAppJsVersion = file_exists(public_path($themeAppJs)) ? filemtime(public_path($themeAppJs)) : time();
        $layoutTenantOptions = auth()->check() ? admin_accessible_tenants() : collect();
        $layoutSelectedTenant = auth()->check() ? admin_current_tenant() : null;
        $layoutSelectedTenantName = admin_tenant_display_name($layoutSelectedTenant);
        $layoutCanManageAdministrators = auth()->check() ? admin_can_manage_administrators() : false;
        $layoutCanSwitchTenant = auth()->check() ? admin_can_switch_tenant() && $layoutTenantOptions->isNotEmpty() : false;
        $layoutTenantAreaUnlocked = auth()->check() ? (admin_is_tenant_user() || (bool) $layoutSelectedTenant) : false;
        $layoutCanViewTenantUsers = auth()->check() ? admin_has_permission('tenant_users.view') : false;
        $layoutCanViewCustomers = auth()->check() ? admin_has_permission('customers.view') : false;
        $layoutCanViewPurchaseOrders = auth()->check() ? admin_has_permission('purchase_orders.view') : false;
        $layoutCanViewBranches = auth()->check() ? admin_has_permission('branches.view') : false;
        $layoutCanViewCategories = auth()->check() ? admin_has_permission('categories.view') : false;
        $layoutCanViewUnitsOfMeasure = auth()->check() ? admin_has_permission('units_of_measure.view') : false;
        $layoutCanViewUomGroups = auth()->check() ? admin_has_permission('uom_groups.view') : false;
        $layoutCanViewItemVariations = auth()->check() ? admin_has_permission('item_variations.view') : false;
        $layoutCanViewItemOptions = auth()->check() ? admin_has_permission('item_options.view') : false;
        $layoutCanViewItems = auth()->check() ? admin_has_permission('items.view') : false;
        $layoutCanViewPriceLists = auth()->check() ? admin_has_permission('price_lists.view') : false;
        $layoutCanViewSliders = auth()->check() ? admin_has_permission('sliders.view') : false;
        $layoutCanViewPromotions = auth()->check() ? admin_has_permission('promotions.view') : false;
        $layoutCanViewActivityLogs = auth()->check() ? admin_has_permission('activity_logs.view') : false;
        $layoutCanViewRoles = auth()->check() ? admin_has_permission('roles.view') : false;
        $layoutCanViewAddresses = auth()->check() ? admin_has_permission('addresses.view') : false;
        $layoutCanViewCurrencies = auth()->check() ? admin_has_permission('currencies.view') : false;
        $layoutCanViewRateIndexes = auth()->check() ? admin_has_permission('rate_indexes.view') : false;
        $layoutCanViewFileManager = auth()->check() ? admin_has_permission('file_manager.view') : false;
        $layoutCanViewGeneralSettings = auth()->check() ? admin_has_permission('general_settings.view') : false;
        $layoutCanViewReports = auth()->check() ? admin_has_permission('reports.view') : false;
        $layoutCanViewTelescope = auth()->check()
            && (bool) config('telescope.enabled', true)
            && ($layoutCanManageAdministrators || \Illuminate\Support\Facades\Gate::check('viewTelescope'));
        $layoutMode = trim((string) $__env->yieldContent('layout_mode', 'default'));
        $layoutIsFullscreen = $layoutMode === 'fullscreen';
        $layoutHasUserManagementMenu = $layoutTenantAreaUnlocked && ($layoutCanViewTenantUsers
            || $layoutCanViewRoles
            || $layoutCanViewAddresses);
        $layoutHasMasterDataMenu = $layoutTenantAreaUnlocked && ($layoutCanViewBranches
            || $layoutCanViewCategories
            || $layoutCanViewUnitsOfMeasure
            || $layoutCanViewUomGroups
            || $layoutCanViewItemVariations
            || $layoutCanViewItemOptions
            || $layoutCanViewItems
            || $layoutCanViewPriceLists
            || $layoutCanViewSliders);
        $layoutHasSettingsMenu = $layoutTenantAreaUnlocked && ($layoutCanViewGeneralSettings
            || $layoutCanViewCurrencies
            || $layoutCanViewRateIndexes
            || $layoutCanViewFileManager);
        // Sidebar groups stay expanded (metisMenu mm-active/mm-show) while one of their children is the current route.
        $layoutUnitManagementActive = request()->routeIs('admin.uom-groups.*')
            || request()->routeIs('admin.units-of-measure.*');
        $layoutVariationManagementActive = request()->routeIs('admin.item-variations.*')
            || request()->routeIs('admin.item-options.*');
        $layoutMasterDataActive = request()->routeIs('admin.branches.*')
            || request()->routeIs('admin.categories.*')
            || $layoutUnitManagementActive
            || $layoutVariationManagementActive
            || request()->routeIs('admin.items.*')
            || request()->routeIs('admin.price-lists.*')
            || request()->routeIs('admin.sliders.*');
        $layoutUserManagementActive = request()->routeIs('admin.tenant-users.*')
            || request()->routeIs('admin.roles.*')
            || request()->routeIs('admin.addresses.*');
        $layoutSettingsActive = request()->routeIs('admin.currencies.*')
            || request()->routeIs('admin.rate-index.*')
            || request()->routeIs('admin.file-manager.*')
            || request()->routeIs('admin.general-settings.*');
        $layoutReportsActive = request()->routeIs('admin.reports.*');
        // Real-time notifications: Live Threat & Incident Log and Recent POS Sales / Orders
        $layoutNotifications = collect();

        // 0. Fetch structured read state for current admin user
        $layoutReadState = auth()->check()
            ? \App\Models\AdminNotificationRead::getUserReadState((int) auth()->id())
            : ['all_read_at' => null, 'threats_read_at' => null, 'orders_read_at' => null, 'keys_map' => []];

        // 1. Live Threat & Incident Log (SecurityLog)
        try {
            $recentThreats = \App\Models\SecurityLog::query()
                ->latest('created_at')
                ->take(6)
                ->get();

            $securityUrl = \Illuminate\Support\Facades\Route::has('admin.security.index')
                ? route('admin.security.index', ['tab' => 'monitor'])
                : '#';

            foreach ($recentThreats as $threat) {
                $key = 'threat_' . $threat->id;
                $timestamp = $threat->created_at ? $threat->created_at->timestamp : 0;
                if (\App\Models\AdminNotificationRead::isNotificationRead($layoutReadState, $key, $timestamp, 'threat')) {
                    continue; // Hidden because read already
                }

                $typeLabel = match ($threat->threat_type) {
                    'sql_injection' => 'SQL Injection Blocked',
                    'honeypot_trap' => 'Honeypot Scanner Blocked',
                    'vpn_proxy_blocked' => 'VPN / Proxy Intercepted',
                    'cross_site_scripting' => 'XSS Attack Blocked',
                    'brute_force_login' => 'Brute Force Login Blocked',
                    'malicious_file_upload' => 'Malicious File Blocked',
                    'rate_limit_exceeded' => 'Rate Limit Flood Blocked',
                    default => 'Threat Intercepted',
                };

                $icon = match (strtolower((string) $threat->severity)) {
                    'critical' => 'uil-shield-slash',
                    'high' => 'uil-shield-exclamation',
                    'medium' => 'uil-shield-check',
                    default => 'uil-info-circle',
                };

                $iconClass = match (strtolower((string) $threat->severity)) {
                    'critical', 'high' => 'text-danger bg-soft-danger',
                    'medium' => 'text-warning bg-soft-warning',
                    default => 'text-info bg-soft-info',
                };

                $badgeClass = match (strtolower((string) $threat->severity)) {
                    'critical', 'high' => 'bg-soft-danger text-danger',
                    'medium' => 'bg-soft-warning text-warning',
                    default => 'bg-soft-info text-info',
                };

                $store = $threat->store_name ?: ($threat->tenant_id ?: 'Central');
                $body = "{$store} • {$threat->ip_address}";

                $layoutNotifications->push([
                    'key' => $key,
                    'type' => 'security',
                    'title' => $typeLabel,
                    'body' => $body,
                    'icon' => $icon,
                    'icon_class' => $iconClass,
                    'badge' => strtoupper((string) ($threat->severity ?: 'THREAT')),
                    'badge_class' => $badgeClass,
                    'time' => $threat->created_at ? $threat->created_at->diffForHumans() : 'Just now',
                    'timestamp' => $threat->created_at ? $threat->created_at->timestamp : 0,
                    'url' => route('admin.notifications.open', ['key' => $key, 'url' => $securityUrl]),
                ]);
            }
        } catch (\Throwable $e) {}

        // 2. Real Orders & POS Sales across active or accessible tenants
        try {
            $tenantsToQuery = collect();
            if ($layoutSelectedTenant) {
                $tenantsToQuery->push($layoutSelectedTenant);
            } else {
                $accessible = admin_accessible_tenants();
                if ($accessible->isNotEmpty()) {
                    $tenantsToQuery = $accessible;
                } else {
                    $tenantsToQuery = \App\Models\Tenant::query()->where('status', 'Active')->take(4)->get();
                }
            }

            $wasInitialized = tenancy()->initialized;
            $originalTenant = tenancy()->initialized ? tenant() : null;

            foreach ($tenantsToQuery as $t) {
                try {
                    tenancy()->initialize($t);
                    $storeName = admin_tenant_display_name($t);
                    $salesUrl = \Illuminate\Support\Facades\Route::has('admin.reports.sales')
                        ? route('admin.reports.sales')
                        : '#';

                    // A. Query recent POS Sales
                    $recentSales = \App\Models\PosSale::query()
                        ->latest('id')
                        ->take(4)
                        ->get();

                    foreach ($recentSales as $sale) {
                        $key = "pos_sale_{$t->id}_{$sale->id}";
                        $timestamp = $sale->completed_at ? $sale->completed_at->timestamp : ($sale->created_at ? $sale->created_at->timestamp : 0);
                        if (\App\Models\AdminNotificationRead::isNotificationRead($layoutReadState, $key, $timestamp, 'order')) {
                            continue; // Hidden because read already
                        }

                        $inv = $sale->invoice_number ?: ($sale->reference ?: ('#' . $sale->id));
                        $curr = strtoupper((string) ($sale->base_currency_code ?: 'USD'));
                        $currSym = $curr === 'KHR' ? '៛' : '$';
                        $totalStr = $currSym . number_format((float) $sale->total_base, 2);
                        $payment = $sale->payment_method ?: 'Cash';
                        $body = "{$storeName} • {$totalStr} • {$payment}";

                        $layoutNotifications->push([
                            'key' => $key,
                            'tenant_id' => (string) $t->id,
                            'type' => 'order',
                            'title' => "Sale {$inv}",
                            'body' => $body,
                            'icon' => 'uil-receipt',
                            'icon_class' => 'text-success bg-soft-success',
                            'badge' => 'ORDER',
                            'badge_class' => 'bg-soft-success text-success',
                            'time' => $sale->completed_at ? $sale->completed_at->diffForHumans() : ($sale->created_at ? $sale->created_at->diffForHumans() : 'Just now'),
                            'timestamp' => $timestamp,
                            'url' => route('admin.notifications.open', [
                                'key' => $key,
                                'tenant_id' => (string) $t->id,
                                'url' => $salesUrl,
                            ]),
                        ]);
                    }

                    // B. Query mobile app Orders (if any exist)
                    if (class_exists(\App\Models\Order::class)) {
                        try {
                            $recentOrders = \App\Models\Order::query()
                                ->latest('id')
                                ->take(3)
                                ->get();

                            foreach ($recentOrders as $order) {
                                $key = "order_{$t->id}_{$order->id}";
                                $ordTimestamp = $order->placed_at ? $order->placed_at->timestamp : ($order->created_at ? $order->created_at->timestamp : 0);
                                if (\App\Models\AdminNotificationRead::isNotificationRead($layoutReadState, $key, $ordTimestamp, 'order')) {
                                    continue; // Hidden because read already
                                }

                                $ordNum = $order->order_number ?: ('#' . $order->id);
                                $curr = strtoupper((string) ($order->currency_code ?: 'USD'));
                                $currSym = $curr === 'KHR' ? '៛' : '$';
                                $totalStr = $currSym . number_format((float) $order->total, 2);
                                $status = ucfirst((string) ($order->status ?: 'Placed'));
                                $body = "{$storeName} • {$totalStr} • {$status}";

                                $layoutNotifications->push([
                                    'key' => $key,
                                    'tenant_id' => (string) $t->id,
                                    'type' => 'order',
                                    'title' => "Order {$ordNum}",
                                    'body' => $body,
                                    'icon' => 'uil-shopping-bag',
                                    'icon_class' => 'text-primary bg-soft-primary',
                                    'badge' => 'ORDER',
                                    'badge_class' => 'bg-soft-primary text-primary',
                                    'time' => $order->placed_at ? $order->placed_at->diffForHumans() : ($order->created_at ? $order->created_at->diffForHumans() : 'Just now'),
                                    'timestamp' => $order->placed_at ? $order->placed_at->timestamp : ($order->created_at ? $order->created_at->timestamp : 0),
                                    'url' => route('admin.notifications.open', [
                                        'key' => $key,
                                        'tenant_id' => (string) $t->id,
                                        'url' => $salesUrl,
                                    ]),
                                ]);
                            }
                        } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {}
            }

            if ($originalTenant) {
                tenancy()->initialize($originalTenant);
            } elseif (tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable $e) {}

        $layoutNotifications = $layoutNotifications->sortByDesc('timestamp')->values();
        $layoutPageTitle = trim($__env->yieldContent('page_title', $__env->yieldContent('title', '')));
        $layoutAppName = config('app.name', 'V-POS');
    @endphp
    <meta charset="utf-8" />
    <title>{{ $layoutPageTitle ? $layoutPageTitle . ' | ' . $layoutAppName : $layoutAppName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (config('services.mapbox.access_token'))
        <script>
            window.mapboxAccessToken = @json(config('services.mapbox.access_token'));
        </script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="{{ global_asset('branding/v-pos-mark.svg') }}" type="image/svg+xml">
    <link href="{{ global_asset('minible/assets/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet"
        type="text/css" />
    <link href="{{ global_asset($themeIconsCss) }}?v={{ $themeIconsVersion }}" rel="stylesheet" type="text/css" />
    <link href="{{ global_asset('minible/assets/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css" />
    <link href="{{ global_asset('branding/vpos-admin.css') }}?v={{ time() }}" rel="stylesheet" type="text/css" />

    @stack('styles')
</head>

<body @auth data-sidebar="light" @else class="@yield('body_class', 'authentication-bg')"
@endauth @hasSection('body_style') style="@yield('body_style')" @endif>
    @guest
        @yield('content')
    @else
        @if ($layoutIsFullscreen)
            <div class="layout-fullscreen-shell">
                @yield('content')
            </div>
        @else
            <div id="layout-wrapper">
                <header id="page-topbar">
                    <div class="navbar-header">
                        <div class="d-flex">
                            <div class="navbar-brand-box">
                                <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
                                    <span class="logo-sm">
                                        <img src="{{ global_asset('branding/v-pos-mark.svg') }}" alt="V-POS" height="26">
                                    </span>
                                    <span class="logo-lg">
                                        <img src="{{ global_asset('branding/v-pos-logo.svg') }}" alt="V-POS" height="26">
                                    </span>
                                </a>

                                <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
                                    <span class="logo-sm">
                                        <img src="{{ global_asset('branding/v-pos-mark.svg') }}" alt="V-POS" height="26">
                                    </span>
                                    <span class="logo-lg">
                                        <img src="{{ global_asset('branding/v-pos-logo-light.svg') }}" alt="V-POS"
                                            height="26">
                                    </span>
                                </a>
                            </div>

                            <button type="button"
                                class="btn btn-sm px-3 font-size-16 header-item waves-effect waves-light vertical-menu-btn">
                                <i class="fa fa-fw fa-bars"></i>
                            </button>

                            <form class="app-search d-none d-lg-block" action="{{ url()->current() }}" method="GET">
                                <div class="position-relative">
                                    <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                                        placeholder="Search...">
                                    <span class="uil-search"></span>
                                </div>
                            </form>
                        </div>

                        <div class="d-flex">
                            @if ($layoutCanSwitchTenant)
                                <div class="d-none d-lg-flex align-items-center me-3">
                                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#tenantSelectionModal">
                                        <i class="uil uil-building me-1"></i>
                                        {{ $layoutSelectedTenantName ?: __('Select Tenant') }}
                                    </button>
                                </div>
                            @elseif (auth()->check() && admin_is_tenant_user())
                                <div class="d-none d-lg-flex align-items-center me-3">
                                    <span class="badge bg-light text-secondary border">
                                        <i class="uil uil-lock me-1"></i>
                                        {{ __('Tenant Locked') }}
                                    </span>
                                </div>
                            @endif
                            <div class="dropdown d-inline-block me-1">
                                <button type="button" class="btn header-item noti-icon position-relative waves-effect"
                                    id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class="uil-bell font-size-22"></i>
                                    <span id="page-header-notifications-badge"
                                        class="badge bg-danger text-white rounded-pill position-absolute {{ $layoutNotifications->isEmpty() ? 'd-none' : '' }}"
                                        style="top: 10px; right: 6px; min-width: 18px; height: 18px; line-height: 18px; padding: 0 5px; font-size: 11px;">
                                        {{ $layoutNotifications->count() }}
                                    </span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                                    aria-labelledby="page-header-notifications-dropdown">
                                    <div class="p-3 border-bottom">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="m-0 fw-semibold">{{ __('Notifications') }}</h6>
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button"
                                                    class="btn btn-link btn-sm p-0 text-muted font-size-11 {{ $layoutNotifications->isEmpty() ? 'd-none' : '' }}"
                                                    id="mark-all-notifications-read"
                                                    style="text-decoration:none;"
                                                    title="{{ __('Mark all as read') }}">
                                                    <i class="uil uil-check-circle me-1"></i>{{ __('Mark all read') }}
                                                </button>
                                                <span id="notifications-header-badge"
                                                    class="badge bg-soft-danger text-danger {{ $layoutNotifications->isEmpty() ? 'd-none' : '' }}">
                                                    {{ $layoutNotifications->count() }} new
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="notifications-scroll-container" style="max-height: 350px; overflow-y: auto;">
                                        <div id="notifications-items-wrapper">
                                            @foreach ($layoutNotifications as $layoutNotification)
                                                <a href="{{ $layoutNotification['url'] }}"
                                                   data-noti-key="{{ $layoutNotification['key'] }}"
                                                   class="text-reset notification-item d-flex align-items-start gap-3 px-3 py-2 border-bottom border-light text-decoration-none js-notification-item position-relative">
                                                    <div class="flex-shrink-0 mt-1">
                                                        <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center {{ $layoutNotification['icon_class'] }}" style="width: 34px; height: 34px;">
                                                            <i class="uil {{ $layoutNotification['icon'] }} font-size-18"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                                            <span class="fw-semibold text-truncate font-size-13 text-dark">{{ $layoutNotification['title'] }}</span>
                                                            <span class="badge {{ $layoutNotification['badge_class'] }}" style="font-size: 9px; letter-spacing: 0.5px;">{{ $layoutNotification['badge'] }}</span>
                                                        </div>
                                                        <div class="text-muted font-size-12 text-truncate mb-1">{{ $layoutNotification['body'] }}</div>
                                                        <div class="font-size-11 text-muted opacity-75">
                                                            <i class="uil uil-clock me-1"></i>{{ $layoutNotification['time'] }}
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                        <div id="notifications-empty-state" class="px-3 py-4 text-center text-muted {{ $layoutNotifications->isNotEmpty() ? 'd-none' : '' }}">
                                            <i class="uil uil-bell-slash font-size-24 text-muted mb-2 d-block"></i>
                                            {{ __('No unread threats or orders right now.') }}
                                        </div>
                                    </div>
                                    <div class="p-2 border-top d-grid">
                                        <a class="btn btn-sm btn-link font-size-13 text-center text-primary text-decoration-none fw-medium" href="{{ route('admin.notifications.index') }}">
                                            <i class="uil uil-arrow-circle-right me-1"></i>{{ __('See All') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown d-inline-block">
                                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <img class="rounded-circle header-profile-user" src="{{ Auth::user()->profile_image_url }}"
                                        alt="Header Avatar">
                                    <span
                                        class="d-none d-xl-inline-block ms-1 fw-medium font-size-15">{{ Auth::user()->name }}</span>
                                    <i class="uil-angle-down d-none d-xl-inline-block font-size-15"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <span class="dropdown-item-text text-muted">{{ __('Signed in as') }}</span>
                                    <span
                                        class="dropdown-item-text fw-semibold">{{ Auth::user()->email ?: Auth::user()->username }}</span>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i
                                            class="uil uil-home-alt font-size-18 align-middle me-1 text-muted"></i>
                                        <span class="align-middle">{{ __('Dashboard') }}</span></a>
                                    @if ($layoutCanSwitchTenant)
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#tenantSelectionModal">
                                            <i class="uil uil-building font-size-18 align-middle text-muted me-1"></i>
                                            <span
                                                class="align-middle">{{ $layoutSelectedTenant ? __('Switch Tenant') : __('Select Tenant') }}</span>
                                        </a>
                                    @endif
                                    @if ($layoutCanViewTelescope)
                                        <a class="dropdown-item" href="{{ url(config('telescope.path', 'telescope')) }}" target="_blank">
                                            <i class="uil uil-telescope font-size-18 align-middle text-muted me-1"></i>
                                            <span class="align-middle">{{ __('Telescope') }}</span>
                                        </a>
                                    @endif
                                    <a class="dropdown-item" href="#"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="uil uil-sign-out-alt font-size-18 align-middle me-1 text-muted"></i>
                                        <span class="align-middle">{{ __('Sign Out') }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- ========== Left Sidebar Start ========== -->
                <div class="vertical-menu">
                    {{-- The copy in .navbar-header is hidden above 991.98px by ".navbar-header
                         .navbar-brand-box{display:none}", so the vertical layout needs its own
                         here. .sidebar-menu-scroll already reserves the top 70px for it. --}}
                    <div class="navbar-brand-box">
                        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
                            <span class="logo-sm">
                                <img src="{{ global_asset('branding/v-pos-mark.svg') }}" alt="V-POS" height="26">
                            </span>
                            <span class="logo-lg">
                                <img src="{{ global_asset('branding/v-pos-logo.svg') }}" alt="V-POS" height="26">
                            </span>
                        </a>

                        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
                            <span class="logo-sm">
                                <img src="{{ global_asset('branding/v-pos-mark.svg') }}" alt="V-POS" height="26">
                            </span>
                            <span class="logo-lg">
                                <img src="{{ global_asset('branding/v-pos-logo-light.svg') }}" alt="V-POS" height="26">
                            </span>
                        </a>
                    </div>

                    <button type="button" class="btn btn-sm px-3 font-size-16 header-item waves-effect vertical-menu-btn">
                        <i class="fa fa-fw fa-bars"></i>
                    </button>

                    <div data-simplebar class="sidebar-menu-scroll">
                        <div class="sidebar-search-wrapper">
                            <div class="sidebar-search-box">
                                <i class="uil uil-search sidebar-search-icon"></i>
                                <input type="text" id="sidebar-menu-search" class="form-control sidebar-search-input"
                                    placeholder="{{ __('Search menu...') }}" autocomplete="off" spellcheck="false"
                                    aria-label="{{ __('Search menu') }}">
                                <button type="button" class="btn btn-link sidebar-search-clear d-none" id="sidebar-search-clear"
                                    title="{{ __('Clear search') }}" aria-label="{{ __('Clear search') }}">
                                    <i class="uil uil-times"></i>
                                </button>
                            </div>
                        </div>

                        <div id="sidebar-menu">
                            <ul class="metismenu list-unstyled" id="side-menu">
                                <li class="menu-title">{{ __('Menu') }}</li>

                                <li>
                                    <a href="{{ route('admin.dashboard') }}"
                                        class="waves-effect {{ request()->routeIs('admin.dashboard', 'home') ? 'active' : '' }}">
                                        <i class="uil-home-alt"></i>
                                        <span>{{ __('Dashboard') }}</span>
                                    </a>
                                </li>

                                @if ($layoutCanManageAdministrators)
                                    <li>
                                        <a href="{{ route('admin.users.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                            <i class="uil-users-alt"></i>
                                            <span>{{ __('Administrator') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.tenants.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">
                                            <i class="uil-server-network"></i>
                                            <span>{{ __('Company') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.security.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.security.*') ? 'active' : '' }}">
                                            <i class="uil-shield-check"></i>
                                            <span>{{ __('Security') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutCanViewTelescope)
                                    <li>
                                        <a href="{{ url(config('telescope.path', 'telescope')) }}" target="_blank"
                                            class="waves-effect {{ request()->is(config('telescope.path', 'telescope') . '*') ? 'active' : '' }}">
                                            <i class="uil-telescope"></i>
                                            <span class="badge rounded-pill bg-soft-info text-info float-end font-size-11">{{ __('Monitor') }}</span>
                                            <span>{{ __('Telescope') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutTenantAreaUnlocked && $layoutCanViewCustomers)
                                    <li>
                                        <a href="{{ route('admin.customers.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                                            <i class="uil-user-square"></i>
                                            <span>{{ __('Customer') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutTenantAreaUnlocked && $layoutCanViewPurchaseOrders)
                                    <li>
                                        <a href="{{ route('admin.purchase-orders.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}">
                                            <i class="uil-shopping-cart-alt"></i>
                                            <span>{{ __('Purchase Order') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutHasMasterDataMenu)
                                    <li class="{{ $layoutMasterDataActive ? 'mm-active' : '' }}">
                                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                                            <i class="uil-database"></i>
                                            <span>{{ __('Master Data') }}</span>
                                        </a>
                                        <ul class="sub-menu {{ $layoutMasterDataActive ? 'mm-show' : '' }}"
                                            aria-expanded="{{ $layoutMasterDataActive ? 'true' : 'false' }}">
                                            @if ($layoutCanViewBranches)
                                                <li>
                                                    <a href="{{ route('admin.branches.index') }}"
                                                        class="{{ request()->routeIs('admin.branches.*') ? 'active' : '' }}">
                                                        {{ __('Branch') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewCategories)
                                                <li>
                                                    <a href="{{ route('admin.categories.index') }}"
                                                        class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                                        {{ __('Category') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewUnitsOfMeasure || $layoutCanViewUomGroups)
                                                <li class="{{ $layoutUnitManagementActive ? 'mm-active' : '' }}">
                                                    <a href="javascript: void(0);" class="has-arrow">
                                                        {{ __('Unit Management') }}
                                                    </a>
                                                    <ul class="sub-menu {{ $layoutUnitManagementActive ? 'mm-show' : '' }}"
                                                        aria-expanded="{{ $layoutUnitManagementActive ? 'true' : 'false' }}">
                                                        @if ($layoutCanViewUnitsOfMeasure)
                                                            <li>
                                                                <a href="{{ route('admin.units-of-measure.index') }}"
                                                                    class="{{ request()->routeIs('admin.units-of-measure.*') ? 'active' : '' }}">
                                                                    {{ __('Unit of Measure') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                        @if ($layoutCanViewUomGroups)
                                                            <li>
                                                                <a href="{{ route('admin.uom-groups.index') }}"
                                                                    class="{{ request()->routeIs('admin.uom-groups.*') ? 'active' : '' }}">
                                                                    {{ __('UOM Group') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewItemVariations || $layoutCanViewItemOptions)
                                                <li class="{{ $layoutVariationManagementActive ? 'mm-active' : '' }}">
                                                    <a href="javascript: void(0);" class="has-arrow">
                                                        {{ __('Variation Management') }}
                                                    </a>
                                                    <ul class="sub-menu {{ $layoutVariationManagementActive ? 'mm-show' : '' }}"
                                                        aria-expanded="{{ $layoutVariationManagementActive ? 'true' : 'false' }}">
                                                        @if ($layoutCanViewItemVariations)
                                                            <li>
                                                                <a href="{{ route('admin.item-variations.index') }}"
                                                                    class="{{ request()->routeIs('admin.item-variations.*') ? 'active' : '' }}">
                                                                    {{ __('Variation') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                        @if ($layoutCanViewItemOptions)
                                                            <li>
                                                                <a href="{{ route('admin.item-options.index') }}"
                                                                    class="{{ request()->routeIs('admin.item-options.*') ? 'active' : '' }}">
                                                                    {{ __('Option') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewItems)
                                                <li>
                                                    <a href="{{ route('admin.items.index') }}"
                                                        class="{{ request()->routeIs('admin.items.*') ? 'active' : '' }}">
                                                        {{ __('Item') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewPriceLists)
                                                <li>
                                                    <a href="{{ route('admin.price-lists.index') }}"
                                                        class="{{ request()->routeIs('admin.price-lists.*') ? 'active' : '' }}">
                                                        {{ __('Price List') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewSliders)
                                                <li>
                                                    <a href="{{ route('admin.sliders.index') }}"
                                                        class="{{ request()->routeIs('admin.sliders.*') ? 'active' : '' }}">
                                                        {{ __('Slider') }}
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                @if ($layoutHasUserManagementMenu)
                                    <li class="{{ $layoutUserManagementActive ? 'mm-active' : '' }}">
                                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                                            <i class="uil-users-alt"></i>
                                            <span>{{ __('User Setting') }}</span>
                                        </a>
                                        <ul class="sub-menu {{ $layoutUserManagementActive ? 'mm-show' : '' }}"
                                            aria-expanded="{{ $layoutUserManagementActive ? 'true' : 'false' }}">
                                            @if ($layoutCanViewAddresses)
                                                <li>
                                                    <a href="{{ route('admin.addresses.index') }}"
                                                        class="{{ request()->routeIs('admin.addresses.*') ? 'active' : '' }}">
                                                        {{ __('Address') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewTenantUsers)
                                                <li>
                                                    <a href="{{ route('admin.tenant-users.index') }}"
                                                        class="{{ request()->routeIs('admin.tenant-users.*') ? 'active' : '' }}">
                                                        {{ __('User') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewRoles)
                                                <li>
                                                    <a href="{{ route('admin.roles.index') }}"
                                                        class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                                        {{ __('Roles') }}
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                @if ($layoutTenantAreaUnlocked && $layoutCanViewActivityLogs)
                                    <li>
                                        <a href="{{ route('admin.activity-logs.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}">
                                            <i class="uil-history"></i>
                                            <span>{{ __('Activity Logs') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutTenantAreaUnlocked && $layoutCanViewPromotions)
                                    <li>
                                        <a href="{{ route('admin.promotions.index') }}"
                                            class="waves-effect {{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}">
                                            <i class="uil-tag-alt"></i>
                                            <span>{{ __('Promotion') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($layoutTenantAreaUnlocked && $layoutCanViewReports)
                                    <li class="{{ $layoutReportsActive ? 'mm-active' : '' }}">
                                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                                            <i class="uil-chart-line"></i>
                                            <span>{{ __('Reports') }}</span>
                                        </a>
                                        <ul class="sub-menu {{ $layoutReportsActive ? 'mm-show' : '' }}"
                                            aria-expanded="{{ $layoutReportsActive ? 'true' : 'false' }}">
                                            <li>
                                                <a href="{{ route('admin.reports.sales') }}"
                                                    class="{{ request()->routeIs('admin.reports.sales') || request()->routeIs('admin.reports.index') ? 'active' : '' }}">
                                                    {{ __('Sales Report') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ route('admin.reports.products') }}"
                                                    class="{{ request()->routeIs('admin.reports.products') ? 'active' : '' }}">
                                                    {{ __('Product Performance') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ route('admin.reports.inventory') }}"
                                                    class="{{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}">
                                                    {{ __('Inventory & Stock') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ route('admin.reports.payments') }}"
                                                    class="{{ request()->routeIs('admin.reports.payments') ? 'active' : '' }}">
                                                    {{ __('Payment Summary') }}
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                @endif

                                @if ($layoutHasSettingsMenu)
                                    <li class="{{ $layoutSettingsActive ? 'mm-active' : '' }}">
                                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                                            <i class="uil-cog"></i>
                                            <span>{{ __('Setting') }}</span>
                                        </a>
                                        <ul class="sub-menu {{ $layoutSettingsActive ? 'mm-show' : '' }}"
                                            aria-expanded="{{ $layoutSettingsActive ? 'true' : 'false' }}">
                                            @if ($layoutCanViewGeneralSettings)
                                                <li>
                                                    <a href="{{ route('admin.general-settings.index') }}"
                                                        class="{{ request()->routeIs('admin.general-settings.*') ? 'active' : '' }}">
                                                        {{ __('General') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewCurrencies)
                                                <li>
                                                    <a href="{{ route('admin.currencies.index') }}"
                                                        class="{{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}">
                                                        {{ __('Currency') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewRateIndexes)
                                                <li>
                                                    <a href="{{ route('admin.rate-index.index') }}"
                                                        class="{{ request()->routeIs('admin.rate-index.*') ? 'active' : '' }}">
                                                        {{ __('Exchange Rates') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($layoutCanViewFileManager)
                                                <li>
                                                    <a href="{{ route('admin.file-manager.index') }}"
                                                        class="{{ request()->routeIs('admin.file-manager.*') ? 'active' : '' }}">
                                                        {{ __('File Manager') }}
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- ========== Left Sidebar End ========== -->

                <div class="main-content">
                    <div class="page-content">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-12">
                                    <div class="page-title-box">
                                        <h4 class="mb-0">@yield('page_title', 'Dashboard')</h4>
                                    </div>
                                </div>
                            </div>

                            @yield('content')
                        </div>
                    </div>

                    <footer class="footer">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-sm-6">
                                    <script>document.write(new Date().getFullYear())</script> ©
                                    {{ config('app.name', 'V-POS') }}.
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end d-none d-sm-block">
                                        {{ __('V-POS') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>

            <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                @csrf
            </form>

            @if ($layoutCanSwitchTenant)
                <div class="modal fade" id="tenantSelectionModal" tabindex="-1" aria-labelledby="tenantSelectionModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.tenant-context.store') }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="tenantSelectionModalLabel">{{ __('Select Tenant') }}</h5>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted">
                                        {{ __('Choose one of your assigned tenants to continue into the admin area.') }}</p>
                                    <div class="mb-0">
                                        <label class="form-label required">{{ __('Tenant') }}</label>
                                        <select name="tenant_id" class="form-select" required>
                                            <option value="">{{ __('Select tenant') }}</option>
                                            @foreach ($layoutTenantOptions as $layoutTenantOption)
                                            <option value="{{ $layoutTenantOption->id }}"
                                                    @selected(optional($layoutSelectedTenant)->id === $layoutTenantOption->id)>
                                                    {{ admin_tenant_display_name($layoutTenantOption) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    @if ($layoutSelectedTenant)
                                        <button type="submit" formaction="{{ route('admin.tenant-context.destroy') }}" formmethod="POST"
                                            class="btn btn-light"
                                            onclick="event.preventDefault(); document.getElementById('clear-tenant-form').submit();">
                                            {{ __('Clear Selection') }}
                                        </button>
                                    @endif
                                    <button type="submit" class="btn btn-primary">{{ __('Use Tenant') }}</button>
                                </div>
                            </form>
                            @if ($layoutSelectedTenant)
                                <form id="clear-tenant-form" method="POST" action="{{ route('admin.tenant-context.destroy') }}"
                                    class="d-none">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endif
    @endguest

    <script src="{{ global_asset('minible/assets/libs/jquery/jquery.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/node-waves/waves.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/waypoints/lib/jquery.waypoints.min.js') }}"></script>
    <script src="{{ global_asset('minible/assets/libs/jquery.counterup/jquery.counterup.min.js') }}"></script>
    <script src="{{ global_asset($themeAppJs) }}?v={{ $themeAppJsVersion }}"></script>
    @auth
    <script>
    (function ($) {
        'use strict';
        $(function () {
            var $searchInput = $('#sidebar-menu-search');
            var $clearBtn = $('#sidebar-search-clear');
            var $sideMenu = $('#side-menu');
            if (!$searchInput.length || !$sideMenu.length) return;

            var noResultsText = @json(__('No menu found'));
            var $emptyLi = $('<li id="sidebar-search-empty" class="d-none"></li>')
                .append('<i class="uil uil-search-alt"></i>')
                .append($('<span></span>').text(noResultsText));
            $sideMenu.append($emptyLi);

            var $menuTitle = $sideMenu.find('> li.menu-title');

            function filterMenu(query) {
                var q = $.trim(query).toLowerCase();

                if (!q) {
                    $clearBtn.addClass('d-none');
                    $emptyLi.addClass('d-none');
                    $menuTitle.show();

                    $sideMenu.find('li').each(function () {
                        $(this).css('display', '');
                    });
                    $sideMenu.find('ul.sub-menu').each(function () {
                        $(this).css('display', '');
                    });
                    return;
                }

                $clearBtn.removeClass('d-none');
                var totalLeaves = 0;

                function processSubMenu($ul, parentMatched) {
                    var anyMatched = false;
                    $ul.children('li').each(function () {
                        var $li = $(this);
                        if ($li.is($emptyLi) || $li.is($menuTitle)) return;

                        var $subUl = $li.children('ul.sub-menu');
                        var $link = $li.children('a');
                        var selfText = $link.length ? $link.text().toLowerCase() : '';
                        var selfMatched = selfText.indexOf(q) !== -1;

                        if ($subUl.length) {
                            var childrenMatched = processSubMenu($subUl, parentMatched || selfMatched);
                            var shouldShow = selfMatched || childrenMatched || parentMatched;
                            if (shouldShow) {
                                $li.show();
                                $subUl.show();
                                anyMatched = true;
                            } else {
                                $li.hide();
                                $subUl.hide();
                            }
                        } else {
                            var shouldShowLeaf = selfMatched || parentMatched;
                            if (shouldShowLeaf) {
                                $li.show();
                                anyMatched = true;
                                totalLeaves++;
                            } else {
                                $li.hide();
                            }
                        }
                    });
                    return anyMatched;
                }

                $sideMenu.children('li').each(function () {
                    var $li = $(this);
                    if ($li.is($emptyLi) || $li.is($menuTitle)) return;

                    var $subUl = $li.children('ul.sub-menu');
                    var $link = $li.children('a');
                    var selfText = $link.length ? $link.text().toLowerCase() : '';
                    var selfMatched = selfText.indexOf(q) !== -1;

                    if ($subUl.length) {
                        var childrenMatched = processSubMenu($subUl, selfMatched);
                        if (selfMatched || childrenMatched) {
                            $li.show();
                            $subUl.show();
                        } else {
                            $li.hide();
                            $subUl.hide();
                        }
                    } else {
                        if (selfMatched) {
                            $li.show();
                            totalLeaves++;
                        } else {
                            $li.hide();
                        }
                    }
                });

                if (totalLeaves === 0) {
                    $menuTitle.hide();
                    $emptyLi.removeClass('d-none');
                } else {
                    $menuTitle.show();
                    $emptyLi.addClass('d-none');
                }
                recalculateSidebarScroll();
            }

            $searchInput.on('input', function () {
                filterMenu(this.value);
            });

            $searchInput.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $(this).val('');
                    filterMenu('');
                    $(this).blur();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var $firstVisible = $sideMenu.find('li:visible a:visible').first();
                    if ($firstVisible.length) $firstVisible.focus();
                }
            });

            $clearBtn.on('click', function () {
                $searchInput.val('');
                filterMenu('');
                $searchInput.focus();
            });

            function recalculateSidebarScroll() {
                var scrollEl = document.querySelector('.sidebar-menu-scroll');
                if (scrollEl && window.SimpleBar) {
                    var sb = SimpleBar.instances.get(scrollEl);
                    if (sb) {
                        sb.recalculate();
                    }
                }
            }

            // Recalculate SimpleBar scroll bounds when menus expand/collapse
            $sideMenu.on('shown.metisMenu hidden.metisMenu', function () {
                setTimeout(recalculateSidebarScroll, 50);
            });

            // Quick focus shortcut '/' or 'Cmd+K' / 'Ctrl+K'
            $(document).on('keydown', function (e) {
                if ((e.key === '/' || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) &&
                    !$(e.target).is('input, textarea, select, [contenteditable]')) {
                    e.preventDefault();
                    $searchInput.focus().select();
                }
            });

            // --- Notifications Read & Dismiss Handler ---
            function updateNotificationBadges(count) {
                var $bellBadge = $('#page-header-notifications-badge');
                var $hdrBadge = $('#notifications-header-badge');
                var $markAllBtn = $('#mark-all-notifications-read');
                var $emptyState = $('#notifications-empty-state');
                var $wrapper = $('#notifications-items-wrapper');

                if (count <= 0) {
                    $bellBadge.addClass('d-none').text('0');
                    $hdrBadge.addClass('d-none').text('0 new');
                    $markAllBtn.addClass('d-none');
                    $wrapper.empty();
                    $emptyState.removeClass('d-none');
                } else {
                    $bellBadge.removeClass('d-none').text(count);
                    $hdrBadge.removeClass('d-none').text(count + ' new');
                    $markAllBtn.removeClass('d-none');
                    $emptyState.addClass('d-none');
                }
            }

            $(document).on('click', '.js-notification-item', function (e) {
                var $item = $(this);
                var key = $item.data('noti-key');
                if (!key) return;

                // Animate and remove item immediately
                $item.css({
                    transition: 'opacity 0.2s ease, transform 0.2s ease',
                    opacity: 0,
                    transform: 'translateX(8px)'
                });

                setTimeout(function () {
                    $item.remove();
                    var remaining = $('.js-notification-item').length;
                    updateNotificationBadges(remaining);
                }, 220);

                // Asynchronously record read_at in background
                try {
                    var token = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
                    fetch('{{ route("admin.notifications.mark-read") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ key: key }),
                        keepalive: true
                    }).catch(function () {});
                } catch (err) {}
            });

            $(document).on('click', '#mark-all-notifications-read', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var keys = [];
                $('.js-notification-item').each(function () {
                    var k = $(this).data('noti-key');
                    if (k) keys.push(k);
                });

                updateNotificationBadges(0);

                try {
                    var token = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
                    fetch('{{ route("admin.notifications.mark-all-read") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ tab: 'all', keys: keys }),
                        keepalive: true
                    }).catch(function () {});
                } catch (err) {}
            });
        });
    })(jQuery);
    </script>
    @endauth
    @include('partials.anti_inspection')
    @stack('scripts')
</body>

</html>
