<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Models\SecuritySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function __construct()
    {
        // Require administrator access for management endpoints
        $this->middleware(['auth', 'admin.administrator'])->except([
            'triggerHoneypot',
            'verifyIpApi',
            'clientConfigApi',
        ]);
    }

    public function index(Request $request): View
    {
        $activeTab = $request->get('tab', 'waf');
        $logFilter = $request->get('threat_type', '');

        $stats = [
            'total_threats' => SecurityLog::count(),
            'threats_today' => SecurityLog::where('created_at', '>=', now()->startOfDay())->count(),
            'active_bans' => BlockedIp::where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->count(),
            'sqli_blocked' => SecurityLog::where('threat_type', 'sql_injection')->count(),
            'under_attack' => SecuritySetting::getBool('under_attack_mode', false),
        ];

        $settings = SecuritySetting::allSettings();

        $blockedIps = BlockedIp::query()
            ->orderByDesc('id')
            ->get();

        $logsQuery = SecurityLog::query();
        if ($logFilter !== '') {
            $logsQuery->where('threat_type', $logFilter);
        }
        $logs = $logsQuery->orderByDesc('id')
            ->take(500)
            ->get();

        $threatTypes = SecurityLog::query()
            ->distinct()
            ->pluck('threat_type');

        // Parse whitelisted IPs for textarea display
        $whitelistedRaw = json_decode($settings['admin_whitelisted_ips'] ?? '[]', true) ?: [];
        $whitelistedText = implode("\n", $whitelistedRaw);

        // CORS origins display text
        $corsOriginsText = $settings['cors_allowed_origins'] ?? "http://localhost:3000\nhttp://localhost:8880\nhttp://localhost:8882\nhttp://127.0.0.1:3000\nhttp://127.0.0.1:8880";

        return view('admin.security.index', [
            'stats' => $stats,
            'settings' => $settings,
            'blockedIps' => $blockedIps,
            'logs' => $logs,
            'threatTypes' => $threatTypes,
            'selectedThreatType' => $logFilter,
            'activeTab' => $activeTab,
            'whitelistedText' => $whitelistedText,
            'corsOriginsText' => $corsOriginsText,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $tab = $request->get('tab', 'waf');

        $updates = [];

        // Tab: WAF & Attack Protection
        if ($tab === 'waf' || $request->has('waf_sqli_enabled') || $request->has('waf_action')) {
            $updates['waf_sqli_enabled'] = $request->has('waf_sqli_enabled') ? '1' : '0';
            $updates['waf_xss_enabled'] = $request->has('waf_xss_enabled') ? '1' : '0';
            $updates['waf_path_traversal_enabled'] = $request->has('waf_path_traversal_enabled') ? '1' : '0';
            $updates['waf_action'] = $request->input('waf_action', 'block_and_autoban');
            $updates['honeypot_traps_enabled'] = $request->has('honeypot_traps_enabled') ? '1' : '0';
        }

        // Tab: DDoS & Rate Limiting
        if ($tab === 'ddos' || $request->has('under_attack_mode') || $request->has('global_rate_limit_per_minute') || $request->has('block_bad_bots')) {
            $updates['under_attack_mode'] = $request->has('under_attack_mode') ? '1' : '0';
            $updates['global_rate_limit_per_minute'] = (string) max(10, (int) $request->input('global_rate_limit_per_minute', 120));
            $updates['block_bad_bots'] = $request->has('block_bad_bots') ? '1' : '0';
            $updates['autoban_failed_logins_enabled'] = $request->has('autoban_failed_logins_enabled') ? '1' : '0';
            $updates['autoban_login_threshold'] = (string) max(2, (int) $request->input('autoban_login_threshold', 5));
            $updates['autoban_duration_hours'] = (string) max(1, (int) $request->input('autoban_duration_hours', 24));
            $updates['login_lockout_minutes'] = (string) max(1, (int) $request->input('login_lockout_minutes', 1));
        }

        // Tab: Firewall, IP Rules & VPN Defense
        if ($tab === 'firewall' || $request->has('admin_ip_whitelist_enabled') || $request->has('block_vpn_proxies')) {
            $whitelistedRaw = $request->input('admin_whitelisted_ips', '');
            $whitelistedArray = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $whitelistedRaw))));

            $updates['admin_ip_whitelist_enabled'] = $request->has('admin_ip_whitelist_enabled') ? '1' : '0';
            if ($request->has('admin_whitelisted_ips')) {
                $updates['admin_whitelisted_ips'] = json_encode($whitelistedArray);
            }
            $updates['block_vpn_proxies'] = $request->has('block_vpn_proxies') ? '1' : '0';
            $updates['block_tor_nodes'] = $request->has('block_tor_nodes') ? '1' : '0';
        }

        // Tab: File Uploads & Anti-Inspect Hardening
        if ($tab === 'hardening' || $request->has('anti_inspection_enabled') || $request->has('strict_file_mime_check')) {
            $updates['strict_file_mime_check'] = $request->has('strict_file_mime_check') ? '1' : '0';
            $updates['block_dangerous_extensions'] = $request->has('block_dangerous_extensions') ? '1' : '0';
            $updates['anti_inspection_enabled'] = $request->has('anti_inspection_enabled') ? '1' : '0';
            $updates['disable_right_click'] = $request->has('disable_right_click') ? '1' : '0';
            $updates['disable_devtools_keys'] = $request->has('disable_devtools_keys') ? '1' : '0';
            $updates['hsts_enabled'] = $request->has('hsts_enabled') ? '1' : '0';
            $updates['x_frame_options'] = $request->input('x_frame_options', 'SAMEORIGIN');
            $updates['x_content_type_options'] = $request->has('x_content_type_options') ? '1' : '0';
            $updates['referrer_policy'] = $request->input('referrer_policy', 'strict-origin-when-cross-origin');
        }

        // Tab: CORS & API Origin Access
        if ($tab === 'cors' || $request->has('cors_allowed_origins') || $request->has('cors_enabled')) {
            $corsOriginsRaw = $request->input('cors_allowed_origins', '');
            $originsArray = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $corsOriginsRaw))));

            $updates['cors_enabled'] = $request->has('cors_enabled') ? '1' : '0';
            $updates['cors_allowed_origins'] = implode("\n", $originsArray);
            $updates['cors_supports_credentials'] = $request->has('cors_supports_credentials') ? '1' : '0';
            $updates['cors_allowed_methods'] = (string) $request->input('cors_allowed_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $updates['cors_allowed_headers'] = (string) $request->input('cors_allowed_headers', 'Content-Type, Authorization, X-Requested-With, X-Tenant, X-Tenant-Id, X-Store, X-Store-Name, Accept, Origin');
            $updates['cors_max_age'] = (string) max(0, (int) $request->input('cors_max_age', 7200));
        }

        // Real-Time Telegram Security Threat Alerts
        if ($tab === 'monitor' || $request->has('telegram_security_alerts_enabled') || $request->has('telegram_security_min_severity')) {
            $updates['telegram_security_alerts_enabled'] = $request->has('telegram_security_alerts_enabled') ? '1' : '0';
            if ($request->has('telegram_security_min_severity')) {
                $updates['telegram_security_min_severity'] = (string) $request->input('telegram_security_min_severity', 'medium');
            }
            if ($request->has('telegram_security_bot_token')) {
                $updates['telegram_security_bot_token'] = trim((string) $request->input('telegram_security_bot_token', ''));
            }
            if ($request->has('telegram_security_chat_id')) {
                $updates['telegram_security_chat_id'] = trim((string) $request->input('telegram_security_chat_id', ''));
            }
        }

        if (! empty($updates)) {
            SecuritySetting::setMany($updates);
        }

        return redirect()
            ->route('admin.security.index', ['tab' => $tab])
            ->with('status', 'Security settings updated successfully.');
    }

    public function blockIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'reason' => ['nullable', 'string', 'max:255'],
            'duration_hours' => ['nullable', 'integer', 'min:0', 'max:87600'],
        ]);

        $duration = ! empty($validated['duration_hours']) ? (int) $validated['duration_hours'] : null;
        $reason = ! empty($validated['reason']) ? $validated['reason'] : 'Manually blocked by administrator';

        BlockedIp::block(
            trim($validated['ip_address']),
            $reason,
            'manual',
            $duration,
            auth()->user() ? auth()->user()->name : 'Administrator'
        );

        return redirect()
            ->route('admin.security.index', ['tab' => 'firewall'])
            ->with('status', "IP {$validated['ip_address']} has been added to the blacklist.");
    }

    public function unblockIp(Request $request, int $id): RedirectResponse
    {
        $record = BlockedIp::findOrFail($id);
        $ip = $record->ip_address;

        BlockedIp::unblock($ip);

        return redirect()
            ->route('admin.security.index', ['tab' => 'firewall'])
            ->with('status', "IP {$ip} has been unblocked successfully.");
    }

    public function quickBlockFromLog(Request $request, int $logId): RedirectResponse
    {
        $log = SecurityLog::findOrFail($logId);

        BlockedIp::block(
            $log->ip_address,
            'Quick blocked from security incident #' . $log->incident_id,
            $log->threat_type,
            24,
            auth()->user() ? auth()->user()->name : 'Administrator'
        );

        return redirect()
            ->route('admin.security.index', ['tab' => 'monitor'])
            ->with('status', "IP {$log->ip_address} has been blacklisted.");
    }

    public function clearLogs(): RedirectResponse
    {
        SecurityLog::truncate();

        return redirect()
            ->route('admin.security.index', ['tab' => 'monitor'])
            ->with('status', 'Security incident logs have been cleared.');
    }

    public function seedSampleLogs(): RedirectResponse
    {
        try {
            (new \Database\Seeders\SecurityIncidentSampleSeeder())->run();

            return redirect()
                ->route('admin.security.index', ['tab' => 'monitor'])
                ->with('status', 'Sample security incidents loaded successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.security.index', ['tab' => 'monitor'])
                ->with('status', 'Failed to load sample incidents: ' . $e->getMessage());
        }
    }

    /**
     * Honeypot scanner trap endpoint.
     */
    public function triggerHoneypot(Request $request)
    {
        $ip = $request->ip();

        if (SecuritySetting::getBool('honeypot_traps_enabled', true)) {
            $incident = SecurityLog::logIncident(
                $ip,
                'honeypot_trap',
                'critical',
                'auto_banned',
                'Probe on vulnerable trap path: ' . $request->path(),
                $request
            );

            BlockedIp::block(
                $ip,
                'Automated probe against honeypot trap (' . $request->path() . ')',
                'honeypot',
                SecuritySetting::getInt('autoban_duration_hours', 24),
                'Firewall Honeypot'
            );

            if (view()->exists('errors.security-blocked')) {
                return response()->view('errors.security-blocked', [
                    'ip' => $ip,
                    'reason' => 'Probing unauthorized trap paths is forbidden. Your IP has been permanently flagged.',
                    'incidentId' => $incident->incident_id,
                ], 403);
            }
        }

        abort(404);
    }

    /**
     * Public API endpoint for frontend/Next.js to verify client IP against firewall.
     */
    public function verifyIpApi(Request $request): JsonResponse
    {
        $ip = (string) ($request->query('ip') ?: $request->ip());
        $isBlocked = BlockedIp::isBlocked($ip);
        $underAttack = SecuritySetting::getBool('under_attack_mode', false);

        return response()->json([
            'ip' => $ip,
            'blocked' => $isBlocked,
            'under_attack' => $underAttack,
            'firewall_active' => true,
        ]);
    }

    /**
     * Public API endpoint providing client-side protection policies to the frontend.
     */
    public function clientConfigApi(): JsonResponse
    {
        return response()->json([
            'anti_inspection_enabled' => SecuritySetting::getBool('anti_inspection_enabled', false),
            'disable_right_click' => SecuritySetting::getBool('disable_right_click', false),
            'disable_devtools_keys' => SecuritySetting::getBool('disable_devtools_keys', false),
            'under_attack_mode' => SecuritySetting::getBool('under_attack_mode', false),
        ]);
    }

    /**
     * Dispatch a test security alert to Telegram.
     */
    public function testTelegramAlert(Request $request): JsonResponse
    {
        $botToken = trim((string) $request->input('bot_token', ''));
        $chatId = trim((string) $request->input('chat_id', ''));

        /** @var \App\Services\TelegramNotificationService $service */
        $service = app(\App\Services\TelegramNotificationService::class);

        $resolvedToken = $botToken ?: SecuritySetting::get('telegram_security_bot_token');
        $resolvedChat = $chatId ?: SecuritySetting::get('telegram_security_chat_id');

        if (empty($resolvedToken) || empty($resolvedChat)) {
            $resolvedToken = $resolvedToken ?: $service->getBotToken();
            $resolvedChat = $resolvedChat ?: $service->getChatId();
        }

        if (empty($resolvedToken) || empty($resolvedChat)) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram Bot Token and Chat ID are missing. Please enter them or configure global Telegram settings.',
            ], 422);
        }

        // Send simulated test threat alert
        $dummyIncident = new SecurityLog([
            'incident_id' => 'SEC-TEST-' . strtoupper(Str::random(4)),
            'ip_address' => $request->ip(),
            'threat_type' => 'sql_injection_simulated',
            'severity' => 'high',
            'action_taken' => 'blocked_and_reported',
            'request_method' => 'POST',
            'request_url' => $request->fullUrl(),
            'payload' => "' UNION SELECT 1, @@version, user() --",
            'store_name' => 'Security Center Test',
            'country_code' => 'KH',
            'country_name' => 'Cambodia',
            'city' => 'Phnom Penh',
            'isp' => 'Telecom Cambodia',
            'is_vpn' => false,
        ]);

        $sent = $dummyIncident->dispatchTelegramAlert($resolvedToken, $resolvedChat);

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => 'Test Telegram Security Alert successfully sent to Chat ID: ' . $resolvedChat,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send Telegram message. Please verify your Bot Token, Chat ID, and ensure you have started a chat with the bot.',
        ], 400);
    }
}
