<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @php
        $minibleIconsCss = 'minible/assets/css/icons.min.css';
        $minibleAppJs = 'minible/assets/js/app.js';
        $minibleIconsVersion = file_exists(public_path($minibleIconsCss)) ? filemtime(public_path($minibleIconsCss)) : time();
        $minibleAppJsVersion = file_exists(public_path($minibleAppJs)) ? filemtime(public_path($minibleAppJs)) : time();
        $layoutTenantOptions = auth()->check() ? admin_accessible_tenants() : collect();
        $layoutSelectedTenant = auth()->check() ? admin_current_tenant() : null;
        $layoutSelectedTenantName = admin_tenant_display_name($layoutSelectedTenant);
        $layoutCanManageAdministrators = auth()->check() ? admin_can_manage_administrators() : false;
        $layoutCanSwitchTenant = auth()->check() ? admin_can_switch_tenant() && $layoutTenantOptions->isNotEmpty() : false;
        $layoutTenantAreaUnlocked = auth()->check() ? (admin_is_tenant_user() || (bool) $layoutSelectedTenant) : false;
        $layoutCanViewTenantUsers = auth()->check() ? admin_has_permission('tenant_users.view') : false;
        $layoutCanManageTenantUsers = auth()->check() ? admin_has_permission('tenant_users.manage') : false;
        $layoutCanViewCustomers = auth()->check() ? admin_has_permission('customers.view') : false;
        $layoutCanViewBranches = auth()->check() ? admin_has_permission('branches.view') : false;
        $layoutCanViewCategories = auth()->check() ? admin_has_permission('categories.view') : false;
        $layoutCanViewItems = auth()->check() ? admin_has_permission('items.view') : false;
        $layoutCanViewPriceLists = auth()->check() ? admin_has_permission('price_lists.view') : false;
        $layoutCanViewPos = auth()->check() ? admin_has_permission('pos.view') : false;
        $layoutCanViewSliders = auth()->check() ? admin_has_permission('sliders.view') : false;
        $layoutCanViewPromotions = auth()->check() ? admin_has_permission('promotions.view') : false;
        $layoutCanViewActivityLogs = auth()->check() ? admin_has_permission('activity_logs.view') : false;
        $layoutCanViewRoles = auth()->check() ? admin_has_permission('roles.view') : false;
        $layoutCanViewAddresses = auth()->check() ? admin_has_permission('addresses.view') : false;
        $layoutCanViewCurrencies = auth()->check() ? admin_has_permission('currencies.view') : false;
        $layoutCanViewRateIndexes = auth()->check() ? admin_has_permission('rate_indexes.view') : false;
        $layoutCanViewFileManager = auth()->check() ? admin_has_permission('file_manager.view') : false;
        $layoutMode = trim((string) $__env->yieldContent('layout_mode', 'default'));
        $layoutIsFullscreen = $layoutMode === 'fullscreen';
        $layoutHasUserManagementMenu = $layoutTenantAreaUnlocked && ($layoutCanViewTenantUsers
            || $layoutCanViewRoles
            || $layoutCanViewAddresses
            || $layoutCanViewActivityLogs);
        $layoutHasMasterDataMenu = $layoutTenantAreaUnlocked && ($layoutCanViewBranches
            || $layoutCanViewCategories
            || $layoutCanViewItems
            || $layoutCanViewPriceLists
            || $layoutCanViewSliders);
        $layoutNotifications = collect([
            session('status')
            ? [
                'title' => __('Latest Update'),
                'body' => session('status'),
                'icon' => 'uil-check-circle',
                'icon_class' => 'text-success',
            ]
            : null,
            auth()->check() && $layoutSelectedTenant
            ? [
                'title' => __('Working Tenant'),
                'body' => __('You are currently managing tenant :tenant.', ['tenant' => $layoutSelectedTenantName]),
                'icon' => 'uil-building',
                'icon_class' => 'text-primary',
            ]
            : null,
            auth()->check() && $layoutTenantOptions->isNotEmpty()
            ? [
                'title' => __('Assigned Tenants'),
                'body' => trans_choice('You have :count active tenant assignment.|You have :count active tenant assignments.', $layoutTenantOptions->count(), ['count' => $layoutTenantOptions->count()]),
                'icon' => 'uil-server-network',
                'icon_class' => 'text-info',
            ]
            : null,
        ])->filter()->values();
    @endphp
    <meta charset="utf-8" />
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ global_asset('minible/assets/images/favicon.ico') }}">
    <link href="{{ global_asset('minible/assets/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet"
        type="text/css" />
    <link href="{{ global_asset($minibleIconsCss) }}?v={{ $minibleIconsVersion }}" rel="stylesheet" type="text/css" />
    <link href="{{ global_asset('minible/assets/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css" />

    @stack('styles')
</head>

<body @auth data-layout="horizontal" data-topbar="colored" @else class="@yield('body_class', 'authentication-bg')"
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
                                <a href="{{ route('home') }}" class="logo logo-dark">
                                    <span class="logo-sm">
                                        <img src="{{ global_asset('minible/assets/images/logo-sm.png') }}" alt="" height="22">
                                    </span>
                                    <span class="logo-lg">
                                        <img src="{{ global_asset('minible/assets/images/logo-dark.png') }}" alt="" height="20">
                                    </span>
                                </a>

                                <a href="{{ route('home') }}" class="logo logo-light">
                                    <span class="logo-sm">
                                        <img src="{{ global_asset('minible/assets/images/logo-sm.png') }}" alt="" height="22">
                                    </span>
                                    <span class="logo-lg">
                                        <img src="{{ global_asset('minible/assets/images/logo-light.png') }}" alt=""
                                            height="20">
                                    </span>
                                </a>
                            </div>

                            <button type="button"
                                class="btn btn-sm px-3 font-size-16 d-lg-none header-item waves-effect waves-light"
                                data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
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
                            @endif
                            <div class="dropdown d-inline-block me-1">
                                <button type="button" class="btn header-item noti-icon position-relative waves-effect"
                                    id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class="uil-bell font-size-22"></i>
                                    @if ($layoutNotifications->isNotEmpty())
                                        <span class="badge bg-danger text-white rounded-pill position-absolute"
                                            style="top: 10px; right: 6px; min-width: 18px; height: 18px; line-height: 18px; padding: 0 5px; font-size: 11px;">
                                            {{ $layoutNotifications->count() }}
                                        </span>
                                    @endif
                                </button>
                                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                                    aria-labelledby="page-header-notifications-dropdown">
                                    <div class="p-3 border-bottom">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="m-0">{{ __('Notifications') }}</h6>
                                            <span
                                                class="badge bg-soft-primary text-primary">{{ $layoutNotifications->count() }}</span>
                                        </div>
                                    </div>
                                    <div class="p-2">
                                        @forelse ($layoutNotifications as $layoutNotification)
                                            <div class="d-flex align-items-start gap-3 px-2 py-2">
                                                <div class="flex-shrink-0">
                                                    <i
                                                        class="uil {{ $layoutNotification['icon'] }} font-size-20 {{ $layoutNotification['icon_class'] }}"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">{{ $layoutNotification['title'] }}</div>
                                                    <div class="text-muted font-size-13">{{ $layoutNotification['body'] }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="px-3 py-4 text-center text-muted">
                                                {{ __('No notifications right now.') }}
                                            </div>
                                        @endforelse
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
                                    <a class="dropdown-item" href="{{ route('home') }}">
                                        <i class="uil uil-estate font-size-18 align-middle text-muted me-1"></i>
                                        <span class="align-middle">{{ __('Dashboard') }}</span>
                                    </a>
                                    @if ($layoutCanSwitchTenant)
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#tenantSelectionModal">
                                            <i class="uil uil-building font-size-18 align-middle text-muted me-1"></i>
                                            <span
                                                class="align-middle">{{ $layoutSelectedTenant ? __('Switch Tenant') : __('Select Tenant') }}</span>
                                        </a>
                                    @endif
                                    <a class="dropdown-item" href="#"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="uil uil-sign-out-alt font-size-18 align-middle me-1 text-muted"></i>
                                        <span class="align-middle">{{ __('Sign out') }}</span>
                                    </a>
                                    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="container-fluid">
                        <div class="topnav">
                            <nav class="navbar navbar-light navbar-expand-lg topnav-menu">
                                <div class="collapse navbar-collapse" id="topnav-menu-content">
                                    <ul class="navbar-nav">
                                        <li class="nav-item">
                                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                                                href="{{ route('home') }}">
                                                <i class="uil-home-alt me-2"></i>{{ __('Dashboard') }}
                                            </a>
                                        </li>
                                        @if ($layoutCanManageAdministrators)
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.users.index') }}">
                                                    <i class="uil-users-alt me-2"></i>{{ __('Administrator') }}
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.tenants.index') }}">
                                                    <i class="uil-server-network me-2"></i>{{ __('Tenants') }}
                                                </a>
                                            </li>
                                        @endif
                                        @if ($layoutTenantAreaUnlocked && $layoutCanViewPos)
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.pos.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.pos.index') }}">
                                                    <i class="uil-calculator-alt me-2"></i>{{ __('POS') }}
                                                </a>
                                            </li>
                                        @endif
                                        @if ($layoutTenantAreaUnlocked && $layoutCanViewCustomers)
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.customers.index') }}">
                                                    <i class="uil-user-square me-2"></i>{{ __('Customer') }}
                                                </a>
                                            </li>
                                        @endif
                                        @if ($layoutHasMasterDataMenu)
                                            <li class="nav-item dropdown">
                                                <a class="nav-link dropdown-toggle arrow-none {{ request()->routeIs('admin.branches.*') || request()->routeIs('admin.categories.*') || request()->routeIs('admin.items.*') || request()->routeIs('admin.price-lists.*') || request()->routeIs('admin.sliders.*') ? 'active' : '' }}"
                                                    href="#" id="topnav-master-data" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="uil-database me-2"></i>{{ __('Master Data') }}
                                                    <div class="arrow-down"></div>
                                                </a>
                                                <div class="dropdown-menu" aria-labelledby="topnav-master-data">
                                                    @if ($layoutCanViewBranches)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.branches.index') }}">
                                                            {{ __('Branch') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewCategories)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.categories.index') }}">
                                                            {{ __('Category') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewItems)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.items.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.items.index') }}">
                                                            {{ __('Item') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewPriceLists)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.price-lists.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.price-lists.index') }}">
                                                            {{ __('Price List') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewSliders)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.sliders.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.sliders.index') }}">
                                                            {{ __('Slider') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                        @if ($layoutHasUserManagementMenu)
                                            <li class="nav-item dropdown">
                                                <a class="nav-link dropdown-toggle arrow-none {{ request()->routeIs('admin.tenant-users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.activity-logs.*') || request()->routeIs('admin.addresses.*') ? 'active' : '' }}"
                                                    href="#" id="topnav-user-management" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="uil-users-alt me-2"></i>{{ __('User Setting') }}
                                                    <div class="arrow-down"></div>
                                                </a>
                                                <div class="dropdown-menu" aria-labelledby="topnav-user-management">
                                                    @if ($layoutCanViewAddresses)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.addresses.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.addresses.index') }}">
                                                            {{ __('Address') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewTenantUsers)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.tenant-users.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.tenant-users.index') }}">
                                                            {{ __('User') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewRoles)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.roles.index') }}">
                                                            {{ __('Roles') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewActivityLogs)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.activity-logs.index') }}">
                                                            {{ __('Activity Logs') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                        @if ($layoutTenantAreaUnlocked && $layoutCanViewPromotions)
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.promotions.index') }}">
                                                    <i class="uil-tag-alt me-2"></i>{{ __('Promotion') }}
                                                </a>
                                            </li>
                                        @endif
                                        @if ($layoutTenantAreaUnlocked)
                                            <li class="nav-item dropdown">
                                                <a class="nav-link dropdown-toggle arrow-none {{ request()->routeIs('admin.general-settings.*') || request()->routeIs('admin.currencies.*') || request()->routeIs('admin.rate-index.*') ? 'active' : '' }}"
                                                    href="#" id="topnav-settings" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="uil-cog me-2"></i>{{ __('Setting') }}
                                                    <div class="arrow-down"></div>
                                                </a>
                                                <div class="dropdown-menu" aria-labelledby="topnav-settings">
                                                    <a class="dropdown-item {{ request()->routeIs('admin.general-settings.*') ? 'active' : '' }}"
                                                        href="{{ route('admin.general-settings.index') }}">
                                                        {{ __('General') }}
                                                    </a>
                                                    @if ($layoutCanViewCurrencies)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.currencies.index') }}">
                                                            {{ __('Currency') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewRateIndexes)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.rate-index.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.rate-index.index') }}">
                                                            {{ __('Rate Index') }}
                                                        </a>
                                                    @endif
                                                    @if ($layoutCanViewFileManager)
                                                        <a class="dropdown-item {{ request()->routeIs('admin.file-manager.*') ? 'active' : '' }}"
                                                            href="{{ route('admin.file-manager.index') }}">
                                                            {{ __('File Manager') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </nav>
                        </div>
                    </div>
                </header>

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
                                    {{ config('app.name', 'Laravel') }}.
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end d-none d-sm-block">
                                        {{ __('Minible horizontal admin template') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>

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
    <script src="{{ global_asset($minibleAppJs) }}?v={{ $minibleAppJsVersion }}"></script>
    @stack('scripts')
</body>

</html>
