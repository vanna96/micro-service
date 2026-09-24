<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotificationRead;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display full notifications center
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $currentTab = $request->query('tab', 'all');
        $user = $request->user();
        $readState = AdminNotificationRead::getUserReadState((int) $user->id);

        $notifications = collect();
        $securityBaseUrl = route('admin.security.index', ['tab' => 'monitor']);
        $salesUrl = route('admin.reports.sales');

        // 1. Live Threat & Incident Logs from Central Database
        $recentThreats = \App\Models\SecurityLog::query()
            ->latest('created_at')
            ->take(100)
            ->get();

        foreach ($recentThreats as $threat) {
            $key = 'threat_' . $threat->id;
            $timestamp = $threat->created_at ? $threat->created_at->timestamp : 0;
            $isRead = AdminNotificationRead::isNotificationRead($readState, $key, $timestamp, 'threat');

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

            $store = $threat->store_name ?: ($threat->tenant_id ?: 'Central / Global');
            $locationStr = ($threat->city ? "{$threat->city}, " : '') . ($threat->country_name ?: 'Global');
            $body = "{$threat->ip_address} • {$locationStr}";

            $notifications->push([
                'key' => $key,
                'id' => $threat->id,
                'incident_id' => (string) ($threat->incident_id ?: ('SEC-' . strtoupper(substr(md5((string) $threat->id), 0, 8)))),
                'type' => 'threat',
                'threat_type' => (string) $threat->threat_type,
                'category' => 'Security Threat',
                'title' => $typeLabel,
                'store' => $store,
                'body' => $body,
                'ip_address' => (string) $threat->ip_address,
                'country_name' => (string) ($threat->country_name ?: 'Global'),
                'country_code' => (string) ($threat->country_code ?: ''),
                'city' => (string) ($threat->city ?: ''),
                'is_vpn' => (bool) $threat->is_vpn,
                'request_url' => (string) ($threat->request_url ?: '/'),
                'request_method' => (string) ($threat->request_method ?: 'GET'),
                'severity' => strtoupper((string) ($threat->severity ?: 'HIGH')),
                'icon' => $icon,
                'icon_class' => $iconClass,
                'badge' => strtoupper((string) ($threat->severity ?: 'THREAT')),
                'badge_class' => $badgeClass,
                'time' => $threat->created_at ? $threat->created_at->diffForHumans() : 'Just now',
                'timestamp' => $timestamp,
                'url' => route('admin.notifications.open', ['key' => $key, 'url' => $securityBaseUrl]),
                'is_read' => $isRead,
            ]);
        }

        // 2. Orders & POS Sales across accessible or active tenant databases
        $tenantsToQuery = collect();
        $selectedTenant = admin_current_tenant();
        if ($selectedTenant) {
            $tenantsToQuery->push($selectedTenant);
        } else {
            $accessible = admin_accessible_tenants();
            if ($accessible->isNotEmpty()) {
                $tenantsToQuery = $accessible;
            } else {
                $tenantsToQuery = Tenant::query()->where('status', 'Active')->take(5)->get();
            }
        }

        $wasInitialized = tenancy()->initialized;
        $originalTenant = tenancy()->initialized ? tenant() : null;

        foreach ($tenantsToQuery as $t) {
            try {
                tenancy()->initialize($t);
                $storeName = admin_tenant_display_name($t);

                $recentSales = \App\Models\PosSale::query()
                    ->latest('id')
                    ->take(50)
                    ->get();

                foreach ($recentSales as $sale) {
                    $key = "pos_sale_{$t->id}_{$sale->id}";
                    $timestamp = $sale->completed_at ? $sale->completed_at->timestamp : ($sale->created_at ? $sale->created_at->timestamp : 0);
                    $isRead = AdminNotificationRead::isNotificationRead($readState, $key, $timestamp, 'order');

                    $inv = $sale->invoice_number ?: ($sale->reference ?: ('#' . $sale->id));
                    $curr = strtoupper((string) ($sale->base_currency_code ?: 'USD'));
                    $currSym = $curr === 'KHR' ? '៛' : '$';
                    $totalStr = $currSym . number_format((float) $sale->total_base, 2);
                    $payment = $sale->payment_method ?: 'Cash';
                    $customer = $sale->customer_name ?: 'Walk-in Customer';
                    $body = "{$customer} • {$totalStr} • {$payment}";

                    $notifications->push([
                        'key' => $key,
                        'id' => $sale->id,
                        'invoice_number' => $inv,
                        'tenant_id' => (string) $t->id,
                        'type' => 'order',
                        'category' => 'POS Sale',
                        'title' => "Sale {$inv}",
                        'store' => $storeName,
                        'body' => $body,
                        'customer_name' => $customer,
                        'payment_method' => $payment,
                        'total_formatted' => $totalStr,
                        'total_base' => (float) $sale->total_base,
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
                        'is_read' => $isRead,
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        if ($originalTenant) {
            tenancy()->initialize($originalTenant);
        } elseif (tenancy()->initialized) {
            tenancy()->end();
        }

        $allNotifications = $notifications->sortByDesc('timestamp')->values();
        $unreadNotifications = $allNotifications->where('is_read', false)->values();
        $threatNotifications = $allNotifications->where('type', 'threat')->values();
        $orderNotifications = $allNotifications->where('type', 'order')->values();

        $unreadThreatsCount = $threatNotifications->where('is_read', false)->count();
        $unreadOrdersCount = $orderNotifications->where('is_read', false)->count();
        $unreadCount = $unreadNotifications->count();

        $filteredNotifications = match ($currentTab) {
            'threats' => $threatNotifications,
            'orders' => $orderNotifications,
            'unread' => $unreadNotifications,
            default => $allNotifications,
        };

        $totalThreatsInDb = \App\Models\SecurityLog::query()->count();

        return view('admin.notifications.index', [
            'notifications' => $filteredNotifications,
            'allNotifications' => $allNotifications,
            'unreadNotifications' => $unreadNotifications,
            'threatNotifications' => $threatNotifications,
            'orderNotifications' => $orderNotifications,
            'currentTab' => $currentTab,
            'unreadCount' => $unreadCount,
            'unreadThreatsCount' => $unreadThreatsCount,
            'unreadOrdersCount' => $unreadOrdersCount,
            'totalCount' => $allNotifications->count(),
            'totalThreatsCount' => $totalThreatsInDb,
        ]);
    }

    /**
     * Mark a single notification as read via AJAX
     */
    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
        ]);

        $userId = (int) $request->user()->id;
        AdminNotificationRead::markKeyAsRead($userId, $validated['key']);

        return response()->json([
            'success' => true,
            'key' => $validated['key'],
        ]);
    }

    /**
     * Mark multiple or all visible notifications as read via AJAX
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'string', 'in:all,unread,threats,orders'],
            'type' => ['nullable', 'string', 'in:all,unread,threat,threats,order,orders'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string', 'max:120'],
        ]);

        $userId = (int) $request->user()->id;
        $keys = array_values(array_filter((array) ($validated['keys'] ?? [])));
        $tab = $validated['tab'] ?? ($validated['type'] ?? 'all');

        if (! empty($keys)) {
            $count = AdminNotificationRead::markKeysAsRead($userId, $keys);
        } else {
            AdminNotificationRead::markAllAsRead($userId, $tab);
            $count = 1;
        }

        return response()->json([
            'success' => true,
            'marked' => $count,
            'tab' => $tab,
        ]);
    }

    /**
     * Handle notification click: mark as read, switch tenant context if needed, and redirect to target URL
     */
    public function open(Request $request): RedirectResponse
    {
        $key = trim((string) $request->query('key', ''));
        $tenantId = trim((string) $request->query('tenant_id', ''));
        $url = trim((string) $request->query('url', ''));

        $user = $request->user();

        // 1. Mark notification as read
        if ($key !== '' && $user) {
            AdminNotificationRead::markKeyAsRead((int) $user->id, $key);
        }

        // 2. Set tenant context in session if switching to a tenant's sales/orders
        if ($tenantId !== '' && $user) {
            $hasAccess = false;
            if (admin_is_tenant_user()) {
                $hasAccess = (admin_auth_tenant_id() === $tenantId);
            } else {
                $hasAccess = $user->tenants()->where('tenants.id', $tenantId)->exists()
                    || \App\Models\Tenant::where('id', $tenantId)->exists();
            }

            if ($hasAccess) {
                $request->session()->put('admin_selected_tenant_id', $tenantId);
            }
        }

        // 3. Resolve target redirect URL safely
        if ($url !== '') {
            $parsed = parse_url($url);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $targetHost = $parsed['host'] ?? null;

            // Allow relative paths or URLs matching current app host / localhost
            if (! $targetHost || $targetHost === $appHost || in_array($targetHost, ['localhost', '127.0.0.1'])) {
                return redirect()->to($url);
            }
        }

        return redirect()->route('admin.dashboard');
    }
}
