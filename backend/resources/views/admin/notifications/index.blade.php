@extends('layouts.app')

@section('title', __('Notifications Center'))
@section('page_title', __('Notifications Center'))

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .notifications-table-container {
        position: relative;
        background: #fff;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0 !important;
        margin: 0 2px !important;
    }
    .dataTables_wrapper .dataTables_length select {
        padding-right: 24px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100 hover-shadow transition-all" style="cursor: pointer;" onclick="$('#tab-link-all').tab('show');">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                            <i class="uil uil-bell font-size-22"></i>
                        </div>
                        <div>
                            <p class="text-muted text-truncate mb-1 font-size-13">{{ __('Total Tracked Alerts') }}</p>
                            <h4 class="mb-0 fw-bold text-dark">{{ $totalCount }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100 hover-shadow transition-all" style="cursor: pointer;" onclick="$('#tab-link-unread').tab('show');">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm rounded-circle bg-soft-danger text-danger d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                            <i class="uil uil-bell-slash font-size-22"></i>
                        </div>
                        <div>
                            <p class="text-muted text-truncate mb-1 font-size-13">{{ __('Unread Alerts') }}</p>
                            <h4 class="mb-0 fw-bold text-danger js-stat-unread-count">{{ $unreadCount }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100 hover-shadow transition-all" style="cursor: pointer;" onclick="$('#tab-link-threats').tab('show');">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm rounded-circle bg-soft-warning text-warning d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                            <i class="uil uil-shield-exclamation font-size-22"></i>
                        </div>
                        <div>
                            <p class="text-muted text-truncate mb-1 font-size-13">{{ __('Threat Incidents') }}</p>
                            <h4 class="mb-0 fw-bold text-dark"><span class="js-stat-unread-threats">{{ $unreadThreatsCount }}</span> <span class="font-size-12 fw-normal text-muted">{{ __('unread') }}</span></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 mb-0 h-100 hover-shadow transition-all" style="cursor: pointer;" onclick="$('#tab-link-orders').tab('show');">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm rounded-circle bg-soft-success text-success d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                            <i class="uil uil-receipt font-size-22"></i>
                        </div>
                        <div>
                            <p class="text-muted text-truncate mb-1 font-size-13">{{ __('Sales & Orders') }}</p>
                            <h4 class="mb-0 fw-bold text-dark"><span class="js-stat-unread-orders">{{ $unreadOrdersCount }}</span> <span class="font-size-12 fw-normal text-muted">{{ __('unread') }}</span></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main List Card with Database-Driven Tab Panes -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <!-- Nav Tabs -->
            <ul class="nav nav-pills card-header-pills" id="notificationsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ in_array($currentTab, ['all', ''], true) ? 'active' : '' }}"
                            id="tab-link-all" data-bs-toggle="tab" data-bs-target="#tab-pane-all" type="button" role="tab">
                        <i class="uil uil-apps me-1"></i>{{ __('All Alerts') }}
                        <span class="badge bg-soft-secondary text-secondary rounded-pill ms-1">{{ $totalCount }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $currentTab === 'unread' ? 'active' : '' }}"
                            id="tab-link-unread" data-bs-toggle="tab" data-bs-target="#tab-pane-unread" type="button" role="tab">
                        <i class="uil uil-envelope-alt me-1"></i>{{ __('Unread Only') }}
                        @if ($unreadCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1 js-tab-unread-total">{{ $unreadCount }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $currentTab === 'threats' ? 'active' : '' }}"
                            id="tab-link-threats" data-bs-toggle="tab" data-bs-target="#tab-pane-threats" type="button" role="tab">
                        <i class="uil uil-shield-check me-1"></i>{{ __('Live Threats') }}
                        <span class="badge bg-soft-warning text-warning rounded-pill ms-1">{{ $threatNotifications->count() }}</span>
                        @if ($unreadThreatsCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1 js-tab-unread-threats">{{ $unreadThreatsCount }} new</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $currentTab === 'orders' ? 'active' : '' }}"
                            id="tab-link-orders" data-bs-toggle="tab" data-bs-target="#tab-pane-orders" type="button" role="tab">
                        <i class="uil uil-receipt me-1"></i>{{ __('Sales & Orders') }}
                        <span class="badge bg-soft-success text-success rounded-pill ms-1">{{ $orderNotifications->count() }}</span>
                        @if ($unreadOrdersCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1 js-tab-unread-orders">{{ $unreadOrdersCount }} new</span>
                        @endif
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3">
            <div class="tab-content" id="notificationsTabContent">

                <!-- TAB 1: ALL ALERTS DATABASE TABLE -->
                <div class="tab-pane fade {{ in_array($currentTab, ['all', ''], true) ? 'show active' : '' }}" id="tab-pane-all" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="card-title mb-0">{{ __('All Notification Events') }}</h5>
                            <p class="text-muted font-size-12 mb-0">{{ __('Unified timeline of all security threats, orders, and POS transactions.') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadCount > 0)
                                <button type="button" class="btn btn-sm btn-outline-primary btn-tab-mark-read" data-tab="all">
                                    <i class="uil uil-check-circle me-1"></i>{{ __('Mark All as Read') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="datatable-all-alerts" class="table table-bordered table-hover align-middle dt-responsive w-100 mb-0">
                            <thead class="table-light text-muted font-size-12 text-uppercase">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>{{ __('Event / Source') }}</th>
                                    <th>{{ __('Store / Location') }}</th>
                                    <th>{{ __('Details') }}</th>
                                    <th>{{ __('Severity / Status') }}</th>
                                    <th>{{ __('Timestamp') }}</th>
                                    <th style="width: 140px;" class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($allNotifications as $item)
                                    <tr class="{{ $item['is_read'] ? 'bg-light bg-opacity-25 opacity-75' : 'fw-medium' }}" id="row-all-{{ md5($item['key']) }}" data-item-key="{{ $item['key'] }}" data-item-type="{{ $item['type'] }}">
                                        <td class="text-center ps-3">
                                            <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center {{ $item['icon_class'] }}" style="width: 32px; height: 32px;">
                                                <i class="uil {{ $item['icon'] }} font-size-16"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-dark font-size-13">{{ $item['title'] }}</span>
                                                @if (! $item['is_read'])
                                                    <span class="badge bg-danger rounded-pill js-new-badge" style="font-size: 8px; padding: 2px 5px;">NEW</span>
                                                @endif
                                            </div>
                                            <div class="font-size-11 text-muted">{{ $item['category'] }}</div>
                                        </td>
                                        <td>
                                            <div class="text-dark font-size-13">
                                                <i class="uil uil-building me-1 text-muted"></i>{{ $item['store'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-muted font-size-12 text-truncate" style="max-width: 300px;">{{ $item['body'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $item['badge_class'] }}" style="font-size: 10px; letter-spacing: 0.5px;">{{ $item['badge'] }}</span>
                                        </td>
                                        <td>
                                            <div class="font-size-12 text-muted">
                                                <i class="uil uil-clock me-1"></i>{{ $item['time'] }}
                                            </div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                @if (! $item['is_read'])
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-mark-single-read px-2 py-1 font-size-12"
                                                            data-key="{{ $item['key'] }}"
                                                            data-type="{{ $item['type'] }}"
                                                            title="{{ __('Mark as read') }}">
                                                        <i class="uil uil-check text-success"></i>
                                                    </button>
                                                @endif
                                                <a href="{{ $item['url'] }}" class="btn btn-sm btn-light border font-size-12 px-2 py-1">
                                                    <span>{{ __('Open') }}</span>
                                                    <i class="uil uil-arrow-right ms-1"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: UNREAD ONLY DATABASE TABLE -->
                <div class="tab-pane fade {{ $currentTab === 'unread' ? 'show active' : '' }}" id="tab-pane-unread" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="card-title mb-0">{{ __('Unread Alerts') }}</h5>
                            <p class="text-muted font-size-12 mb-0">{{ __('Alerts and orders requiring attention that have not been marked as read.') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadCount > 0)
                                <button type="button" class="btn btn-sm btn-outline-primary btn-tab-mark-read" data-tab="all">
                                    <i class="uil uil-check-circle me-1"></i>{{ __('Mark All as Read') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($unreadNotifications->isEmpty())
                        <div class="p-5 text-center text-muted">
                            <div class="avatar-md rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 56px; height: 56px;">
                                <i class="uil uil-check-circle font-size-28 text-success"></i>
                            </div>
                            <h5 class="fw-semibold text-dark mb-1">{{ __('All Caught Up!') }}</h5>
                            <p class="text-muted font-size-13 mb-0">{{ __('There are no unread notifications right now.') }}</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table id="datatable-unread-alerts" class="table table-bordered table-hover align-middle dt-responsive w-100 mb-0">
                                <thead class="table-light text-muted font-size-12 text-uppercase">
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>{{ __('Event / Source') }}</th>
                                        <th>{{ __('Store / Location') }}</th>
                                        <th>{{ __('Details') }}</th>
                                        <th>{{ __('Severity / Status') }}</th>
                                        <th>{{ __('Timestamp') }}</th>
                                        <th style="width: 140px;" class="text-end pe-3">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($unreadNotifications as $item)
                                        <tr class="fw-medium" id="row-unread-{{ md5($item['key']) }}" data-item-key="{{ $item['key'] }}" data-item-type="{{ $item['type'] }}">
                                            <td class="text-center ps-3">
                                                <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center {{ $item['icon_class'] }}" style="width: 32px; height: 32px;">
                                                    <i class="uil {{ $item['icon'] }} font-size-16"></i>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-dark font-size-13">{{ $item['title'] }}</span>
                                                    <span class="badge bg-danger rounded-pill js-new-badge" style="font-size: 8px; padding: 2px 5px;">NEW</span>
                                                </div>
                                                <div class="font-size-11 text-muted">{{ $item['category'] }}</div>
                                            </td>
                                            <td>
                                                <div class="text-dark font-size-13">
                                                    <i class="uil uil-building me-1 text-muted"></i>{{ $item['store'] }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-muted font-size-12 text-truncate" style="max-width: 300px;">{{ $item['body'] }}</div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $item['badge_class'] }}" style="font-size: 10px; letter-spacing: 0.5px;">{{ $item['badge'] }}</span>
                                            </td>
                                            <td>
                                                <div class="font-size-12 text-muted">
                                                    <i class="uil uil-clock me-1"></i>{{ $item['time'] }}
                                                </div>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-mark-single-read px-2 py-1 font-size-12"
                                                            data-key="{{ $item['key'] }}"
                                                            data-type="{{ $item['type'] }}"
                                                            title="{{ __('Mark as read') }}">
                                                        <i class="uil uil-check text-success"></i>
                                                    </button>
                                                    <a href="{{ $item['url'] }}" class="btn btn-sm btn-light border font-size-12 px-2 py-1">
                                                        <span>{{ __('Open') }}</span>
                                                        <i class="uil uil-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- TAB 3: LIVE THREATS DATABASE TABLE (Direct from security_logs Table) -->
                <div class="tab-pane fade {{ $currentTab === 'threats' ? 'show active' : '' }}" id="tab-pane-threats" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="card-title mb-0"><i class="uil uil-shield-check text-warning me-1"></i>{{ __('Security Logs Database Table') }} (<code>security_logs</code>)</h5>
                            <p class="text-muted font-size-12 mb-0">{{ __('Forensic logs of intercepted attacks, automated probes, and firewall events.') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadThreatsCount > 0)
                                <button type="button" class="btn btn-sm btn-outline-warning btn-tab-mark-read" data-tab="threats">
                                    <i class="uil uil-check-circle me-1"></i>{{ __('Mark All Threats as Read') }}
                                </button>
                            @endif
                            <a href="{{ route('admin.security.index', ['tab' => 'monitor']) }}" class="btn btn-sm btn-outline-danger">
                                <i class="uil uil-shield-exclamation me-1"></i>{{ __('Full Security Center') }}
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="datatable-threat-alerts" class="table table-bordered table-hover align-middle dt-responsive w-100 mb-0">
                            <thead class="table-light text-muted font-size-12 text-uppercase">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>{{ __('Incident ID') }}</th>
                                    <th>{{ __('Threat Type') }}</th>
                                    <th>{{ __('Store / Source') }}</th>
                                    <th>{{ __('IP & Location') }}</th>
                                    <th>{{ __('Route / Request') }}</th>
                                    <th>{{ __('Severity') }}</th>
                                    <th>{{ __('Timestamp') }}</th>
                                    <th style="width: 130px;" class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($threatNotifications as $threat)
                                    <tr class="{{ $threat['is_read'] ? 'bg-light bg-opacity-25 opacity-75' : 'fw-medium' }}" id="row-threat-{{ md5($threat['key']) }}" data-item-key="{{ $threat['key'] }}" data-item-type="threat">
                                        <td class="text-center ps-3">
                                            <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center {{ $threat['icon_class'] }}" style="width: 32px; height: 32px;">
                                                <i class="uil {{ $threat['icon'] }} font-size-16"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-light text-dark border font-family-monospace font-size-11">{{ $threat['incident_id'] }}</span>
                                                @if (! $threat['is_read'])
                                                    <span class="badge bg-danger rounded-pill js-new-badge" style="font-size: 8px; padding: 2px 5px;">NEW</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-semibold font-size-13">{{ $threat['title'] }}</span>
                                            <div class="font-size-11 text-muted">{{ $threat['threat_type'] }}</div>
                                        </td>
                                        <td>
                                            <div class="text-dark font-size-13">
                                                <i class="uil uil-building me-1 text-muted"></i>{{ $threat['store'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="font-size-12 fw-medium text-dark font-family-monospace">{{ $threat['ip_address'] }}</div>
                                            <div class="font-size-11 text-muted">
                                                {{ $threat['city'] ? $threat['city'] . ', ' : '' }}{{ $threat['country_name'] }}
                                                @if ($threat['is_vpn'])
                                                    <span class="badge bg-soft-danger text-danger ms-1" style="font-size: 9px;">VPN/PROXY</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="font-size-12 text-truncate font-family-monospace" style="max-width: 180px;" title="{{ $threat['request_method'] }} {{ $threat['request_url'] }}">
                                                <span class="text-primary">{{ $threat['request_method'] }}</span> {{ $threat['request_url'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $threat['badge_class'] }}" style="font-size: 10px; letter-spacing: 0.5px;">{{ $threat['severity'] }}</span>
                                        </td>
                                        <td>
                                            <div class="font-size-12 text-muted">
                                                <i class="uil uil-clock me-1"></i>{{ $threat['time'] }}
                                            </div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                @if (! $threat['is_read'])
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-mark-single-read px-2 py-1 font-size-12"
                                                            data-key="{{ $threat['key'] }}"
                                                            data-type="threat"
                                                            title="{{ __('Mark as read') }}">
                                                        <i class="uil uil-check text-success"></i>
                                                    </button>
                                                @endif
                                                <a href="{{ $threat['url'] }}" class="btn btn-sm btn-light border font-size-12 px-2 py-1" title="{{ __('View in Security Monitor') }}">
                                                    <span>{{ __('View') }}</span>
                                                    <i class="uil uil-shield-check ms-1"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: SALES & ORDERS DATABASE TABLE (Direct from pos_sales Table) -->
                <div class="tab-pane fade {{ $currentTab === 'orders' ? 'show active' : '' }}" id="tab-pane-orders" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="card-title mb-0"><i class="uil uil-receipt text-success me-1"></i>{{ __('POS Sales Database Table') }} (<code>pos_sales</code>)</h5>
                            <p class="text-muted font-size-12 mb-0">{{ __('Live transactions and sales completed at physical stores and mobile apps.') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadOrdersCount > 0)
                                <button type="button" class="btn btn-sm btn-outline-success btn-tab-mark-read" data-tab="orders">
                                    <i class="uil uil-check-circle me-1"></i>{{ __('Mark All Orders as Read') }}
                                </button>
                            @endif
                            <a href="{{ route('admin.reports.sales') }}" class="btn btn-sm btn-outline-success">
                                <i class="uil uil-file-alt me-1"></i>{{ __('Sales Report') }}
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="datatable-order-alerts" class="table table-bordered table-hover align-middle dt-responsive w-100 mb-0">
                            <thead class="table-light text-muted font-size-12 text-uppercase">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>{{ __('Invoice / Ref') }}</th>
                                    <th>{{ __('Store / Branch') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Payment Method') }}</th>
                                    <th>{{ __('Total Payable') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Timestamp') }}</th>
                                    <th style="width: 130px;" class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orderNotifications as $order)
                                    <tr class="{{ $order['is_read'] ? 'bg-light bg-opacity-25 opacity-75' : 'fw-medium' }}" id="row-order-{{ md5($order['key']) }}" data-item-key="{{ $order['key'] }}" data-item-type="order">
                                        <td class="text-center ps-3">
                                            <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center {{ $order['icon_class'] }}" style="width: 32px; height: 32px;">
                                                <i class="uil {{ $order['icon'] }} font-size-16"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-dark font-size-13 fw-semibold">{{ $order['invoice_number'] }}</span>
                                                @if (! $order['is_read'])
                                                    <span class="badge bg-danger rounded-pill js-new-badge" style="font-size: 8px; padding: 2px 5px;">NEW</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-dark font-size-13">
                                                <i class="uil uil-building me-1 text-muted"></i>{{ $order['store'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-dark font-size-13">{{ $order['customer_name'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-size-11">{{ $order['payment_method'] }}</span>
                                        </td>
                                        <td>
                                            <span class="text-success fw-bold font-size-13">{{ $order['total_formatted'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-success text-success" style="font-size: 10px;">COMPLETED</span>
                                        </td>
                                        <td>
                                            <div class="font-size-12 text-muted">
                                                <i class="uil uil-clock me-1"></i>{{ $order['time'] }}
                                            </div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                @if (! $order['is_read'])
                                                    <button type="button" class="btn btn-sm btn-outline-secondary js-mark-single-read px-2 py-1 font-size-12"
                                                            data-key="{{ $order['key'] }}"
                                                            data-type="order"
                                                            title="{{ __('Mark as read') }}">
                                                        <i class="uil uil-check text-success"></i>
                                                    </button>
                                                @endif
                                                <a href="{{ $order['url'] }}" class="btn btn-sm btn-light border font-size-12 px-2 py-1" title="{{ __('View in Sales Report') }}">
                                                    <span>{{ __('Report') }}</span>
                                                    <i class="uil uil-arrow-right ms-1"></i>
                                                </a>
                                            </div>
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
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>

<script>
(function ($) {
    'use strict';

    // Shared DataTable configurations
    var dtConfig = {
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "{{ __('All') }}"]],
        order: [], // keep original server ordering
        columnDefs: [
            { orderable: false, targets: 0 },
            { orderable: false, targets: -1 }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "{{ __('Search alerts...') }}",
            emptyTable: "{{ __('No notifications found.') }}"
        }
    };

    // Initialize all 4 database-driven DataTables
    var tableAll = $('#datatable-all-alerts').length ? $('#datatable-all-alerts').DataTable(dtConfig) : null;
    var tableUnread = $('#datatable-unread-alerts').length ? $('#datatable-unread-alerts').DataTable(dtConfig) : null;
    var tableThreats = $('#datatable-threat-alerts').length ? $('#datatable-threat-alerts').DataTable(dtConfig) : null;
    var tableOrders = $('#datatable-order-alerts').length ? $('#datatable-order-alerts').DataTable(dtConfig) : null;

    $('.dataTables_length select').addClass('form-select form-select-sm');

    // Adjust column widths on tab switch
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
        var targetPane = $(e.target).data('bs-target');
        var tabSlug = targetPane.replace('#tab-pane-', '');
        if (history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.set('tab', tabSlug);
            history.replaceState(null, '', url);
        }
    });

    // Mark All Read for Current Tab
    $(document).on('click', '.btn-tab-mark-read', function () {
        var $btn = $(this);
        var tab = $btn.data('tab') || 'all';
        $btn.prop('disabled', true).html('<i class="uil uil-spinner-alt fa-spin me-1"></i> {{ __("Marking...") }}');

        var token = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
        fetch('{{ route("admin.notifications.mark-all-read") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tab: tab })
        }).then(function (res) {
            return res.json();
        }).then(function () {
            window.location.reload();
        }).catch(function () {
            window.location.reload();
        });
    });

    // Mark Single Notification as Read
    $(document).on('click', '.js-mark-single-read', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var key = $btn.data('key');
        var type = $btn.data('type');
        var $rows = $('tr[data-item-key="' + key + '"]');

        $btn.prop('disabled', true).html('<i class="uil uil-spinner-alt fa-spin"></i>');

        var token = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
        fetch('{{ route("admin.notifications.mark-read") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ key: key })
        }).then(function (res) {
            return res.json();
        }).then(function () {
            $rows.addClass('bg-light bg-opacity-25 opacity-75').removeClass('fw-medium');
            $rows.find('.js-new-badge').remove();
            $rows.find('.js-mark-single-read').fadeOut(200, function () { $(this).remove(); });

            // Decrement badge numbers in real time
            var unreadTotal = parseInt($('.js-stat-unread-count').first().text() || '0', 10);
            if (unreadTotal > 0) {
                var nextTotal = unreadTotal - 1;
                $('.js-stat-unread-count').text(nextTotal);
                if (nextTotal <= 0) {
                    $('.js-tab-unread-total').remove();
                    $('#page-header-notifications-badge').addClass('d-none');
                } else {
                    $('.js-tab-unread-total').text(nextTotal);
                    $('#page-header-notifications-badge').text(nextTotal);
                }
            }

            if (type === 'threat') {
                var threatUnread = parseInt($('.js-stat-unread-threats').first().text() || '0', 10);
                if (threatUnread > 0) {
                    var nextThreat = threatUnread - 1;
                    $('.js-stat-unread-threats').text(nextThreat);
                    if (nextThreat <= 0) {
                        $('.js-tab-unread-threats').remove();
                    } else {
                        $('.js-tab-unread-threats').text(nextThreat + ' new');
                    }
                }
            } else if (type === 'order') {
                var orderUnread = parseInt($('.js-stat-unread-orders').first().text() || '0', 10);
                if (orderUnread > 0) {
                    var nextOrder = orderUnread - 1;
                    $('.js-stat-unread-orders').text(nextOrder);
                    if (nextOrder <= 0) {
                        $('.js-tab-unread-orders').remove();
                    } else {
                        $('.js-tab-unread-orders').text(nextOrder + ' new');
                    }
                }
            }
        }).catch(function () {
            $btn.prop('disabled', false).html('<i class="uil uil-check text-success"></i>');
        });
    });
})(jQuery);
</script>
@endpush
