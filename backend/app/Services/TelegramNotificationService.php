<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotificationService
{
    /**
     * Check if Telegram notifications are enabled for the current or given tenant.
     */
    /**
     * Check if Telegram notifications are enabled for the current or given tenant.
     */
    public function isEnabled(?Tenant $tenant = null): bool
    {
        $tenant = $this->resolveTenantForOrders($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];

        if (array_key_exists('telegram_notifications_enabled', $settings)) {
            return filter_var($settings['telegram_notifications_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('telegram_enabled', $settings)) {
            return filter_var($settings['telegram_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('telegram.enabled', false);
    }

    /**
     * Resolve the Telegram Bot Token for the tenant or global config.
     */
    public function getBotToken(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenantForOrders($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];
        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        $configToken = trim((string) config('telegram.bot_token', ''));

        return $configToken !== '' ? $configToken : null;
    }

    /**
     * Resolve the Telegram Chat ID for the tenant or global config.
     */
    public function getChatId(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenantForOrders($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];
        $chatId = trim((string) ($settings['telegram_chat_id'] ?? ''));

        if ($chatId !== '') {
            return $chatId;
        }

        $configChatId = trim((string) config('telegram.chat_id', ''));

        return $configChatId !== '' ? $configChatId : null;
    }

    /**
     * Check if Telegram error logging notifications are enabled.
     */
    public function isErrorLogEnabled(?Tenant $tenant = null): bool
    {
        $tenant = $this->resolveTenantForErrorLogging($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];

        if (array_key_exists('telegram_error_log_enabled', $settings)) {
            return filter_var($settings['telegram_error_log_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('telegram.error_log_enabled', false);
    }

    /**
     * Resolve the Telegram Bot Token for Error Logging.
     * Priority:
     * 1. Tenant error log bot token (from database)
     * 2. Tenant main bot token (from database)
     * 3. Global error log config (optional .env fallback)
     * 4. Global main bot token (optional .env fallback)
     */
    public function getErrorLogBotToken(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenantForErrorLogging($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];

        $token = trim((string) ($settings['telegram_error_log_bot_token'] ?? ''));
        if ($token !== '') {
            return $token;
        }

        $mainToken = trim((string) ($settings['telegram_bot_token'] ?? ''));
        if ($mainToken !== '') {
            return $mainToken;
        }

        $configToken = trim((string) config('telegram.error_log_bot_token', ''));
        if ($configToken !== '') {
            return $configToken;
        }

        return $this->getBotToken($tenant);
    }

    /**
     * Resolve the Telegram Chat ID for Error Logging.
     * Priority:
     * 1. Tenant error log chat ID (from database)
     * 2. Tenant main chat ID (from database)
     * 3. Global error log config (optional .env fallback)
     * 4. Global main chat ID (optional .env fallback)
     */
    public function getErrorLogChatId(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenantForErrorLogging($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];

        $chatId = trim((string) ($settings['telegram_error_log_chat_id'] ?? ''));
        if ($chatId !== '') {
            return $chatId;
        }

        $mainChatId = trim((string) ($settings['telegram_chat_id'] ?? ''));
        if ($mainChatId !== '') {
            return $mainChatId;
        }

        $configChatId = trim((string) config('telegram.error_log_chat_id', ''));
        if ($configChatId !== '') {
            return $configChatId;
        }

        return $this->getChatId($tenant);
    }

    /**
     * Resolve the active or target tenant for error logging directly from database / request / context.
     */
    public function resolveTenantForErrorLogging(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        // 1. Current active tenancy (e.g. tenant domain/subdomain)
        if (function_exists('tenant') && ($activeTenant = tenant())) {
            return $activeTenant;
        }

        // 2. Tenant from route parameter if viewing/managing a tenant in admin
        if (function_exists('request') && request()) {
            $routeTenant = request()->route('tenant');
            if ($routeTenant instanceof Tenant) {
                return $routeTenant;
            }
            if (is_string($routeTenant) && filled($routeTenant)) {
                try {
                    $found = Tenant::find($routeTenant);
                    if ($found) {
                        return $found;
                    }
                } catch (Throwable) {
                }
            }
        }

        // 3. Current tenant selected in admin session if it has error logging configured
        if (function_exists('admin_current_tenant') && ($sessionTenant = admin_current_tenant())) {
            if ($this->hasTenantErrorLogConfig($sessionTenant)) {
                return $sessionTenant;
            }
        }

        // 4. Central / unauthenticated context: Query the database for any tenant with error logging enabled
        try {
            $dbTenant = Tenant::all()->first(function (Tenant $t) {
                return $this->hasTenantErrorLogConfig($t);
            });

            if ($dbTenant) {
                return $dbTenant;
            }
        } catch (Throwable) {
            // Ignore if DB is unreachable during crash
        }

        return function_exists('admin_current_tenant') ? admin_current_tenant() : null;
    }

    /**
     * Check if a tenant has Telegram error logging configured in its database settings.
     */
    public function hasTenantErrorLogConfig(?Tenant $tenant): bool
    {
        if (! $tenant) {
            return false;
        }

        $settings = is_array($tenant->general_settings) ? $tenant->general_settings : [];
        $enabled = filter_var($settings['telegram_error_log_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $token = trim((string) ($settings['telegram_error_log_bot_token'] ?? $settings['telegram_bot_token'] ?? ''));
        $chat = trim((string) ($settings['telegram_error_log_chat_id'] ?? $settings['telegram_chat_id'] ?? ''));

        return $enabled && $token !== '' && $chat !== '';
    }

    /**
     * Resolve the active or target tenant for order notifications directly from database.
     */
    public function resolveTenantForOrders(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        if (function_exists('tenant') && ($activeTenant = tenant())) {
            return $activeTenant;
        }

        if (function_exists('admin_current_tenant') && ($sessionTenant = admin_current_tenant())) {
            $settings = is_array($sessionTenant->general_settings) ? $sessionTenant->general_settings : [];
            $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
            $chat = trim((string) ($settings['telegram_chat_id'] ?? ''));
            if ($token !== '' && $chat !== '') {
                return $sessionTenant;
            }
        }

        if (function_exists('request') && request()) {
            $routeTenant = request()->route('tenant');
            if ($routeTenant instanceof Tenant) {
                return $routeTenant;
            }
            if (is_string($routeTenant) && filled($routeTenant)) {
                try {
                    $found = Tenant::find($routeTenant);
                    if ($found) {
                        return $found;
                    }
                } catch (Throwable) {
                }
            }
        }

        try {
            return Tenant::all()->first(function (Tenant $t) {
                $settings = is_array($t->general_settings) ? $t->general_settings : [];
                $enabled = filter_var($settings['telegram_notifications_enabled'] ?? $settings['telegram_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
                $chat = trim((string) ($settings['telegram_chat_id'] ?? ''));

                return $enabled && $token !== '' && $chat !== '';
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Send an HTML-formatted message to Telegram directly via HTTP.
     */
    public function sendDirectMessage(string $htmlMessage, ?string $botToken = null, ?string $chatId = null, array|string|null $replyMarkup = null): bool
    {
        $token = $botToken ?: $this->getBotToken();
        $chat = $chatId ?: $this->getChatId();

        if (empty($token) || empty($chat)) {
            Log::info('[TelegramNotificationService] Skipped: Bot token or Chat ID is missing.');
            return false;
        }

        try {
            $timeout = (int) config('telegram.timeout', 10);
            $url = "https://api.telegram.org/bot{$token}/sendMessage";

            $payload = [
                'chat_id' => $chat,
                'text' => $htmlMessage,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            $cleanMarkup = $this->filterReplyMarkup($replyMarkup);
            if ($cleanMarkup !== null) {
                $payload['reply_markup'] = json_encode($cleanMarkup);
            }

            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[TelegramNotificationService] Telegram API error: ' . $response->body());
            return false;
        } catch (Throwable $e) {
            Log::error('[TelegramNotificationService] Request failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Send an HTML-formatted message to Telegram (supports queueing or direct send).
     */
    public function sendMessage(string $htmlMessage, ?string $botToken = null, ?string $chatId = null, array|string|null $replyMarkup = null, bool $useQueue = false): bool
    {
        if ($useQueue && ! app()->runningUnitTests()) {
            try {
                \App\Jobs\SendTelegramMessageJob::dispatch($htmlMessage, $botToken, $chatId, $replyMarkup);
                return true;
            } catch (Throwable) {
                // If queue dispatch fails, fall back to direct HTTP transmission
            }
        }

        return $this->sendDirectMessage($htmlMessage, $botToken, $chatId, $replyMarkup);
    }

    /**
     * Send a test ping to verify credentials from the admin panel.
     */
    public function sendTestMessage(string $botToken, string $chatId, ?string $storeName = null): array
    {
        $store = $storeName ?: (tenant() ? admin_tenant_display_name(tenant()) : config('app.name', 'Store'));
        $now = Carbon::now()->toDateTimeString();
        $env = app()->environment();

        $lines = [
            "🤖 <b>Telegram Notification Test</b>",
            " ├ Store : <code>" . htmlspecialchars($store, ENT_QUOTES, 'UTF-8') . "</code>",
            " ├ Env : <code>{$env}</code>",
            " ├ Status : <code>Connected</code>",
            " ├ Channel : Real-time Telegram Notifications",
            " └ Time : <i>{$now}</i>",
        ];
        $message = implode("\n", $lines);

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Notification Works!', 'url' => url('/admin')],
                    ['text' => '🏪 Store Admin', 'url' => url('/admin')],
                ],
            ],
        ];

        try {
            $timeout = (int) config('telegram.timeout', 10);
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            $cleanMarkup = $this->filterReplyMarkup($replyMarkup);
            if ($cleanMarkup !== null) {
                $payload['reply_markup'] = json_encode($cleanMarkup);
            }

            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Test message sent successfully to Telegram with interactive button!',
                ];
            }

            $errorData = $response->json();
            $desc = $errorData['description'] ?? $response->body();

            return [
                'success' => false,
                'message' => "Telegram returned an error: {$desc}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to connect to Telegram: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Send a realistic sample order receipt to Telegram to verify formatting and buttons.
     */
    public function sendSampleOrderNotification(string $botToken, string $chatId, ?Tenant $tenant = null): array
    {
        $store = $tenant ? admin_tenant_display_name($tenant) : (tenant() ? admin_tenant_display_name(tenant()) : config('app.name', 'Store'));
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];
        $currency = strtoupper((string) ($settings['currency'] ?? 'USD'));
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $price1 = $this->formatAmount(3.50, $currency);
        $price2 = $this->formatAmount(4.00, $currency);
        $subtotal = $this->formatAmount(7.50, $currency);
        $total = $this->formatAmount(7.50, $currency);

        $lines = [];
        $lines[] = "🧪 <b>[SAMPLE TEST] New POS Sale Completed!</b>";
        $lines[] = " ├ Store : {$store}";
        $lines[] = " ├ Invoice : <code>INV-TEST-0042</code>";
        $lines[] = " ├ Customer : Sophea Meas";
        $lines[] = " ├ Order Type : Takeaway";
        $lines[] = " └ Payment Method : Cash";
        $lines[] = "";
        $lines[] = "Items (2):";
        $lines[] = "• 1x Iced Americano (M) — <code>{$price1}</code>";
        $lines[] = "• 2x Croissant — <code>{$price2}</code>";
        $lines[] = "";
        $lines[] = "Subtotal: {$subtotal}";
        $lines[] = "💵 Total Paid: <b>{$total}</b>";
        $lines[] = "Cash Tendered: " . $this->formatAmount(10.00, $currency);
        $lines[] = "Change Due: " . $this->formatAmount(2.50, $currency);
        $lines[] = "";
        $lines[] = "⏱️ <i>{$now}</i>";

        $message = implode("\n", $lines);

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Order Test Verified', 'url' => url('/admin')],
                    ['text' => '🏪 Open Store Admin', 'url' => url('/admin')],
                ],
            ],
        ];

        try {
            $timeout = (int) config('telegram.timeout', 10);
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            $cleanMarkup = $this->filterReplyMarkup($replyMarkup);
            if ($cleanMarkup !== null) {
                $payload['reply_markup'] = json_encode($cleanMarkup);
            }

            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Sample order receipt sent to Telegram with interactive button!',
                ];
            }

            $errorData = $response->json();
            $desc = $errorData['description'] ?? $response->body();

            return [
                'success' => false,
                'message' => "Telegram returned an error: {$desc}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to connect to Telegram: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Recursion safety flag for error log dispatching.
     */
    public static bool $isSendingErrorLog = false;

    /**
     * Dispatch an application error or unhandled exception record to Telegram.
     */
    public function notifyErrorRecord(array $record, ?Tenant $tenant = null, bool $useQueue = true): bool
    {
        if (static::$isSendingErrorLog) {
            return false;
        }

        // Never alert on Debugbar internal telemetry exceptions
        $rawMessage = (string) ($record['message'] ?? '');
        if (str_contains($rawMessage, 'Debugbar exception:')) {
            return false;
        }

        $tenant = $this->resolveTenantForErrorLogging($tenant);

        $token = $this->getErrorLogBotToken($tenant);
        $chat = $this->getErrorLogChatId($tenant);

        if (empty($token) || empty($chat)) {
            return false;
        }

        // Deduplicate identical errors within 5 seconds to prevent spam
        $exception = $record['context']['exception'] ?? null;
        $hashSource = $exception instanceof Throwable
            ? get_class($exception) . ':' . $exception->getFile() . ':' . $exception->getLine() . ':' . $exception->getMessage()
            : $rawMessage;

        $dedupKey = 'telegram_err_' . md5($hashSource);

        try {
            if (\Illuminate\Support\Facades\Cache::has($dedupKey)) {
                return false;
            }
            \Illuminate\Support\Facades\Cache::put($dedupKey, 1, 5);
        } catch (Throwable) {
            // Proceed if cache is unavailable
        }

        static::$isSendingErrorLog = true;

        try {
            $formattedMessage = $this->formatErrorLogMessage($record, $tenant);

            if ($useQueue && ! app()->runningUnitTests()) {
                try {
                    if ($tenant && (! function_exists('tenant') || ! tenant())) {
                        $tenant->run(function () use ($formattedMessage, $token, $chat, $tenant) {
                            \App\Jobs\SendTelegramMessageJob::dispatch($formattedMessage, $token, $chat, null, (string) $tenant->id);
                        });
                    } else {
                        \App\Jobs\SendTelegramMessageJob::dispatch($formattedMessage, $token, $chat, null, $tenant ? (string) $tenant->id : null);
                    }
                    return true;
                } catch (Throwable) {
                    // Fall back to direct transmission if queue is unreachable
                }
            }

            return $this->sendDirectMessage($formattedMessage, $token, $chat);
        } finally {
            static::$isSendingErrorLog = false;
        }
    }

    /**
     * Format a Monolog error record into a clean, modern, executive-grade Telegram HTML alert.
     */
    public function formatErrorLogMessage(array $record, ?Tenant $tenant = null): string
    {
        $store = $tenant ? admin_tenant_display_name($tenant) : (tenant() ? admin_tenant_display_name(tenant()) : config('app.name', 'Laravel'));
        $tenantId = $tenant?->id ?? (tenant()?->id);
        $storeDisplay = ($tenantId && $tenantId !== $store) ? "{$store} ({$tenantId})" : $store;

        $level = strtoupper((string) ($record['level_name'] ?? 'ERROR'));
        $env = app()->environment();
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $levelEmoji = match ($level) {
            'EMERGENCY' => '💀',
            'ALERT', 'CRITICAL' => '🔥',
            'WARNING' => '⚠️',
            default => '🚨',
        };

        // Title line
        $title = "{$levelEmoji} <b>[{$level}] Laravel Error Alert</b>";

        // Structured key-value fields for the tree branch layout
        $fields = [];
        $fields['Store'] = '<code>' . htmlspecialchars($storeDisplay, ENT_QUOTES, 'UTF-8') . '</code>';
        $fields['Env'] = "<code>{$env}</code>";

        // Exception details if available in context
        $rawMessage = trim((string) ($record['message'] ?? ''));
        $exception = $record['context']['exception'] ?? null;
        $displayMsg = '';
        $sourcePath = '';

        if ($exception instanceof Throwable) {
            $exceptionClass = class_basename($exception);
            $fields['Exception'] = "<code>{$exceptionClass}</code>";
            $fields['Time'] = "<i>{$now}</i>";

            $excMessage = trim($exception->getMessage());
            if (filled($rawMessage) && filled($excMessage) && ! str_contains($rawMessage, $excMessage)) {
                $displayMsg = $rawMessage . ' — ' . $excMessage;
            } else {
                $displayMsg = filled($rawMessage) ? $rawMessage : ($excMessage ?: 'Unknown Exception');
            }

            $cleanFile = $this->cleanPath($exception->getFile());
            $line = $exception->getLine();
            $sourcePath = "{$cleanFile}:{$line}";
        } else {
            $fields['Time'] = "<i>{$now}</i>";
            $displayMsg = $rawMessage ?: 'Unknown Error';
        }

        // Request context if in HTTP request
        if (! app()->runningInConsole() && function_exists('request') && request()) {
            try {
                $method = request()->method();
                $path = request()->getRequestUri();
                $host = request()->getHost();
                $isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true) || str_starts_with($host, '172.') || str_starts_with($host, '10.');
                $routeDisplay = $isLocalHost ? $path : "{$host}{$path}";
                $ip = request()->ip();

                $fields['Route'] = '<code>' . $method . ' ' . htmlspecialchars(substr($routeDisplay, 0, 80), ENT_QUOTES, 'UTF-8') . '</code>';

                $userDisplay = 'Guest';
                if (auth()->check() && ($user = auth()->user())) {
                    $userDisplay = htmlspecialchars($user->name ?: $user->email ?: ('User #' . $user->id), ENT_QUOTES, 'UTF-8');
                }
                $fields['Client IP'] = "<code>{$ip}</code>";
                $fields['User'] = $userDisplay;
            } catch (Throwable) {
                // Ignore request parsing issues
            }
        }

        // Stack trace or context preview
        $tracePreview = null;
        if ($exception instanceof Throwable) {
            $tracePreview = $this->buildStackTracePreview($exception);
        } elseif (! empty($record['context'])) {
            $cleanContext = array_filter($record['context'], fn ($v) => ! is_resource($v) && ! is_object($v));
            $jsonContext = json_encode($cleanContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($jsonContext && strlen($jsonContext) > 2) {
                $tracePreview = mb_substr($jsonContext, 0, 400);
            }
        }

        // Combine Message, Source, and Trace into ONE single preformatted code block
        $detailsSections = [];
        if (filled($displayMsg)) {
            $detailsSections[] = "Message:\n" . mb_substr($displayMsg, 0, 500);
        }
        if (filled($sourcePath)) {
            $detailsSections[] = "Source:\n" . $sourcePath;
        }
        if (! empty($tracePreview)) {
            $traceLabel = ($exception instanceof Throwable) ? 'Trace:' : 'Context:';
            $detailsSections[] = "{$traceLabel}\n" . mb_substr($tracePreview, 0, 650);
        }

        $combinedDetails = implode("\n\n", $detailsSections);

        // Build elegant tree branches:
        //  ├ Key : Value
        //  └ Error Details :
        // <pre>...</pre>
        $lines = [$title];
        foreach ($fields as $key => $val) {
            $lines[] = " ├ {$key} : {$val}";
        }

        if (filled($combinedDetails)) {
            $lines[] = " └ Error Details :\n<pre>" . htmlspecialchars($combinedDetails, ENT_QUOTES, 'UTF-8') . '</pre>';
        } elseif (! empty($lines)) {
            $lastIdx = count($lines) - 1;
            $lines[$lastIdx] = preg_replace('/^ ├ /', ' └ ', $lines[$lastIdx]);
        }

        return implode("\n", $lines);
    }

    /**
     * Format a SecurityLog threat incident into the executive tree-branch Telegram HTML alert.
     */
    public function formatSecurityAlertMessage(\App\Models\SecurityLog $log, ?Tenant $tenant = null): string
    {
        $store = $tenant ? admin_tenant_display_name($tenant) : ($log->store_name ?: (tenant() ? admin_tenant_display_name(tenant()) : config('app.name', 'Central Admin')));
        $tenantId = $tenant?->id ?? ($log->tenant_id ?: (tenant()?->id));
        $storeDisplay = ($tenantId && $tenantId !== $store) ? "{$store} ({$tenantId})" : $store;

        $severity = strtoupper((string) ($log->severity ?: 'HIGH'));
        $env = app()->environment();
        $now = $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');

        $severityEmoji = match ($severity) {
            'CRITICAL' => '🚨',
            'HIGH' => '🔥',
            'MEDIUM' => '⚠️',
            'LOW' => '🛡️',
            default => '🚨',
        };

        // Title line matching user's template
        $title = "{$severityEmoji} <b>[{$severity}] Live Threat & Incident Alert</b>";

        // Structured key-value fields for the tree branch layout
        $fields = [];
        $fields['Store'] = '<code>' . htmlspecialchars($storeDisplay, ENT_QUOTES, 'UTF-8') . '</code>';
        $fields['Env'] = "<code>{$env}</code>";

        $threatName = ucwords(str_replace(['_', '-'], ' ', (string) ($log->threat_type ?: 'Security Violation')));
        $fields['Threat'] = "<code>{$threatName}</code>";

        $actionName = strtoupper(str_replace(['_', '-'], ' ', (string) ($log->action_taken ?: 'BLOCKED')));
        $fields['Action'] = "<code>{$actionName}</code>";

        $fields['Time'] = "<i>{$now}</i>";

        // Route formatting
        $method = strtoupper((string) ($log->request_method ?: 'GET'));
        $rawUrl = (string) ($log->request_url ?: '/');
        if (filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($rawUrl);
            $host = $parsed['host'] ?? '';
            $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            $isLocal = in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true)
                || str_starts_with($host, '172.')
                || str_starts_with($host, '10.')
                || str_starts_with($host, '192.168.');
            $routeDisplay = $isLocal ? $path : "{$host}{$path}";
        } else {
            $routeDisplay = $rawUrl;
        }
        $fields['Route'] = '<code>' . $method . ' ' . htmlspecialchars(substr($routeDisplay, 0, 80), ENT_QUOTES, 'UTF-8') . '</code>';

        // Client IP
        $flag = $log->flag_emoji ?: '';
        $ipDisplay = $log->ip_address ?: '0.0.0.0';
        $fields['Client IP'] = "<code>{$ipDisplay}</code>" . ($flag ? " {$flag}" : '');

        // User
        $userDisplay = 'Guest';
        if (auth()->check() && ($user = auth()->user())) {
            $userDisplay = htmlspecialchars($user->name ?: $user->email ?: ('User #' . $user->id), ENT_QUOTES, 'UTF-8');
        }
        $fields['User'] = $userDisplay;

        // Message, Source, and Payload sections inside <pre>...</pre>
        $incidentId = $log->incident_id ?: 'SEC-' . strtoupper(\Illuminate\Support\Str::random(8));
        $displayMsg = "Incident [{$incidentId}]: {$threatName} attempt intercepted and {$actionName} by firewall.";

        $sourceParts = [];
        $loc = trim(($log->flag_emoji ? $log->flag_emoji . ' ' : '') . ($log->country_name ?: '') . ($log->city ? " ({$log->city})" : ''));
        if (filled($loc) && ! str_contains($loc, 'Unknown')) {
            $sourceParts[] = $loc;
        }
        if (filled($log->isp) && $log->isp !== 'Unknown ISP') {
            $sourceParts[] = "ISP: {$log->isp}";
        }
        if ($log->is_vpn) {
            $sourceParts[] = "VPN/Proxy: Yes";
        }
        if ($log->coordinates_display) {
            $sourceParts[] = "Coords: {$log->coordinates_display}";
        }
        if (filled($log->user_agent)) {
            $sourceParts[] = "Agent: " . \Illuminate\Support\Str::limit($log->user_agent, 160);
        }

        $sourceStr = ! empty($sourceParts) ? implode("\n", $sourceParts) : 'Local Network / Internal Request';

        $detailsSections = [];
        $detailsSections[] = "Message:\n" . mb_substr($displayMsg, 0, 500);
        $detailsSections[] = "Source:\n" . $sourceStr;

        if (filled($log->payload)) {
            $detailsSections[] = "Payload:\n" . mb_substr($log->payload, 0, 800);
        }

        $combinedDetails = implode("\n\n", $detailsSections);

        // Build elegant tree branches:
        //  ├ Key : Value
        //  └ Threat Details :
        // <pre>...</pre>
        $lines = [$title];
        foreach ($fields as $key => $val) {
            $lines[] = " ├ {$key} : {$val}";
        }

        if (filled($combinedDetails)) {
            $lines[] = " └ Threat Details :\n<pre>" . htmlspecialchars($combinedDetails, ENT_QUOTES, 'UTF-8') . '</pre>';
        } elseif (! empty($lines)) {
            $lastIdx = count($lines) - 1;
            $lines[$lastIdx] = preg_replace('/^ ├ /', ' └ ', $lines[$lastIdx]);
        }

        return implode("\n", $lines);
    }

    /**
     * Clean absolute and Docker container paths to short relative project paths.
     */
    public function cleanPath(?string $path): string
    {
        if (! $path) {
            return 'unknown';
        }

        $base = base_path();
        return str_replace([$base . '/', '/var/www/html/', $base], '', $path);
    }

    /**
     * Build an application-first, clean stack trace preview for Telegram alerts.
     */
    public function buildStackTracePreview(Throwable $exception, int $maxFrames = 4): ?string
    {
        $trace = $exception->getTrace();
        if (empty($trace)) {
            $raw = $exception->getTraceAsString();
            if (empty($raw)) {
                return null;
            }
            $rawLines = explode("\n", $raw);
            $cleaned = array_map(fn ($l) => $this->cleanPath($l), array_slice($rawLines, 0, 3));
            return implode("\n", $cleaned);
        }

        $appFrames = [];
        $vendorFrames = [];

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            if (! $file) {
                continue;
            }

            $cleanFile = $this->cleanPath($file);
            $cleanClass = $class ? class_basename($class) : '';
            $call = ($cleanClass ? "{$cleanClass}{$type}" : '') . "{$function}()";

            $isApp = ! str_starts_with($cleanFile, 'vendor/');
            $formatted = "{$cleanFile}:{$line}\n   ↳ {$call}";

            if ($isApp) {
                $appFrames[] = $formatted;
            } else {
                $vendorFrames[] = $formatted;
            }
        }

        $selected = ! empty($appFrames) ? array_slice($appFrames, 0, $maxFrames) : array_slice($vendorFrames, 0, 3);

        if (empty($selected)) {
            return null;
        }

        $output = [];
        foreach ($selected as $idx => $frameText) {
            $output[] = "#{$idx} {$frameText}";
        }

        return implode("\n", $output);
    }

    /**
     * Send a test error alert from the admin panel to verify error logging credentials.
     */
    public function sendTestErrorLog(string $botToken, string $chatId, ?string $storeName = null, string $type = 'sample'): array
    {
        $store = $storeName ?: (tenant() ? admin_tenant_display_name(tenant()) : config('app.name', 'Store'));
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $env = app()->environment();

        if ($type === 'ping') {
            $lines = [
                "🔔 <b>Telegram Error Logging Ping</b>",
                " ├ Store : <code>" . htmlspecialchars($store, ENT_QUOTES, 'UTF-8') . "</code>",
                " ├ Env : <code>{$env}</code>",
                " ├ Status : <code>Connected &amp; Active</code>",
                " ├ Channel : Real-time Error Alerts",
                " └ Time : <i>{$now}</i>",
            ];
            $message = implode("\n", $lines);
        } else {
            $lines = [
                "🚨 <b>[SAMPLE TEST] Laravel Error Alert</b>",
                " ├ Store : <code>" . htmlspecialchars($store, ENT_QUOTES, 'UTF-8') . "</code>",
                " ├ Env : <code>{$env}</code>",
                " ├ Exception : <code>RuntimeException</code>",
                " ├ Time : <i>{$now}</i>",
                " ├ Route : <code>POST /admin/checkout/process</code>",
                " ├ Client IP : <code>127.0.0.1</code>",
                " ├ User : Administrator (ID: 1)",
                " └ Error Details :\n<pre>Message:\n[SAMPLE] Simulated test exception to verify Telegram error notification channel.\n\nSource:\napp/Http/Controllers/CheckoutController.php:124\n\nTrace:\n#0 app/Http/Controllers/CheckoutController.php:124\n   ↳ CheckoutController->processPayment()\n#1 app/Http/Controllers/OrderController.php:45\n   ↳ OrderController->handleOrder()</pre>",
            ];

            $message = implode("\n", $lines);
        }

        try {
            $timeout = (int) config('telegram.timeout', 10);
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Test error alert sent successfully to Telegram!',
                ];
            }

            $errorData = $response->json();
            $desc = $errorData['description'] ?? $response->body();

            return [
                'success' => false,
                'message' => "Telegram returned an error: {$desc}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to connect to Telegram: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Format and send a notification for a completed POS Sale.
     */
    public function notifyPosSale(PosSale $sale, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?: tenant() ?: admin_current_tenant();

        if (! $this->isEnabled($tenant)) {
            return false;
        }

        $botToken = $this->getBotToken($tenant);
        $chatId = $this->getChatId($tenant);

        if (! $botToken || ! $chatId) {
            return false;
        }

        $message = $this->formatPosSaleMessage($sale, $tenant);
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '🧾 View In Admin', 'url' => url('/admin')],
                ],
            ],
        ];

        return $this->sendMessage($message, $botToken, $chatId, $replyMarkup);
    }

    /**
     * Format and send a notification for an online / mobile Order.
     */
    public function notifyOrder(mixed $order, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?: tenant() ?: admin_current_tenant();

        if (! $this->isEnabled($tenant)) {
            return false;
        }

        $botToken = $this->getBotToken($tenant);
        $chatId = $this->getChatId($tenant);

        if (! $botToken || ! $chatId) {
            return false;
        }

        $message = $this->formatOrderMessage($order, $tenant);
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '📦 View Order In Admin', 'url' => url('/admin')],
                ],
            ],
        ];

        return $this->sendMessage($message, $botToken, $chatId, $replyMarkup);
    }

    /**
     * Build the HTML message for a POS Sale.
     */
    public function formatPosSaleMessage(PosSale $sale, ?Tenant $tenant = null): string
    {
        $storeName = htmlspecialchars($tenant ? admin_tenant_display_name($tenant) : config('app.name', 'POS Store'));
        $invoice = htmlspecialchars($sale->invoice_number ?: $sale->reference ?: ('#' . $sale->id));
        $paymentMethod = htmlspecialchars($sale->payment_method ?: 'Cash');
        $orderType = htmlspecialchars($sale->order_type ?: 'Takeaway');
        $customerName = htmlspecialchars($sale->customer_name ?: 'Walk-in Customer');
        $baseCurrency = strtoupper((string) ($sale->base_currency_code ?: 'USD'));
        $totalFormatted = $this->formatAmount((float) $sale->total_base, $baseCurrency);

        $date = $sale->completed_at
            ? $sale->completed_at->format('Y-m-d H:i:s')
            : Carbon::now()->format('Y-m-d H:i:s');

        $lines = [];
        $lines[] = "🛒 <b>New POS Sale Completed!</b>";
        $lines[] = " ├ Store : {$storeName}";
        $lines[] = " ├ Invoice : <code>{$invoice}</code>";
        $lines[] = " ├ Customer : {$customerName}";
        $lines[] = " ├ Order Type : {$orderType}";
        $lines[] = " └ Payment Method : {$paymentMethod}";
        $lines[] = "";

        // Items list
        $items = $sale->items()->get();
        if ($items->isNotEmpty()) {
            $lines[] = "Items (" . $items->count() . "):";
            $maxItems = 10;
            foreach ($items->take($maxItems) as $item) {
                $qty = (float) $item->quantity;
                $formattedQty = $qty == (int) $qty ? (int) $qty : $qty;
                $name = htmlspecialchars($item->name ?: 'Item');
                $itemCurr = $item->currency_code ?: $baseCurrency;
                $itemTotal = (float) ($item->line_total_base ?: ($item->unit_price * $qty));
                $priceStr = $this->formatAmount($itemTotal, $itemCurr);

                $uom = $item->uom_name ?: $item->uom_code;
                $uomStr = $uom ? " (" . htmlspecialchars($uom) . ")" : "";

                $lines[] = "• {$formattedQty}x {$name}{$uomStr} — <code>{$priceStr}</code>";
            }

            if ($items->count() > $maxItems) {
                $remaining = $items->count() - $maxItems;
                $lines[] = "<i>... and {$remaining} more item(s)</i>";
            }
            $lines[] = "";
        }

        // Calculations & Financials
        $subtotal = (float) $sale->subtotal_base;
        $discount = (float) $sale->discount_base;
        $tax = (float) $sale->tax_base;

        if ($discount > 0 || $tax > 0) {
            $lines[] = "Subtotal: " . $this->formatAmount($subtotal, $baseCurrency);
            if ($discount > 0) {
                $promoLabel = $sale->promotion_name ? " (" . htmlspecialchars($sale->promotion_name) . ")" : "";
                $lines[] = "Discount{$promoLabel}: -" . $this->formatAmount($discount, $baseCurrency);
            }
            if ($tax > 0) {
                $lines[] = "Tax: " . $this->formatAmount($tax, $baseCurrency);
            }
        }

        $lines[] = "💵 Total Paid: <b>{$totalFormatted}</b>";

        // Cash tender details
        if (strcasecmp($paymentMethod, 'Cash') === 0 && (float) $sale->cash_received_base > 0) {
            $received = (float) $sale->cash_received_base;
            $change = (float) $sale->change_base;
            $lines[] = "Cash Tendered: " . $this->formatAmount($received, $baseCurrency);
            if ($change > 0) {
                $lines[] = "Change Due: " . $this->formatAmount($change, $baseCurrency);
            }
        }

        $lines[] = "";
        $lines[] = "⏱️ <i>{$date}</i>";

        return implode("\n", $lines);
    }

    /**
     * Build the HTML message for an online / mobile Order.
     */
    public function formatOrderMessage(mixed $order, ?Tenant $tenant = null): string
    {
        $storeName = htmlspecialchars($tenant ? admin_tenant_display_name($tenant) : config('app.name', 'Store'));
        $orderNumber = htmlspecialchars($order->order_number ?: ('#' . $order->id));
        $customer = $order->user ? htmlspecialchars($order->user->name ?: $order->user->email) : 'Online Customer';
        $paymentMethod = htmlspecialchars($order->payment_method ?: 'Online Payment');
        $deliveryMethod = htmlspecialchars($order->delivery_method ?: 'Delivery');
        $currency = strtoupper((string) ($order->currency_code ?: 'USD'));
        $totalFormatted = $this->formatAmount((float) $order->total, $currency);

        $date = $order->placed_at
            ? $order->placed_at->format('Y-m-d H:i:s')
            : Carbon::now()->format('Y-m-d H:i:s');

        $lines = [];
        $lines[] = "🛍️ <b>New Online Order Placed!</b>";
        $lines[] = " ├ Store : {$storeName}";
        $lines[] = " ├ Order : <code>{$orderNumber}</code>";
        $lines[] = " ├ Customer : {$customer}";
        $lines[] = " ├ Delivery : {$deliveryMethod}";
        $lines[] = " └ Payment : {$paymentMethod}";

        if ($order->address) {
            $addr = htmlspecialchars(implode(', ', array_filter([
                $order->address->address_line ?? $order->address->address_line1 ?? null,
                $order->address->city ?? null,
                $order->address->state ?? null,
            ])));
            if ($addr !== '') {
                $lines[] = " ├ Address : {$addr}";
            }
        }

        $lines[] = "";

        // Items list
        $items = $order->items()->get();
        if ($items->isNotEmpty()) {
            $lines[] = "Items (" . $items->count() . "):";
            $maxItems = 10;
            foreach ($items->take($maxItems) as $item) {
                $qty = (int) $item->quantity;
                $name = htmlspecialchars($item->name ?: 'Item');
                $itemTotal = (float) $item->line_total;
                $priceStr = $this->formatAmount($itemTotal, $currency);

                $lines[] = "• {$qty}x {$name} — <code>{$priceStr}</code>";
            }

            if ($items->count() > $maxItems) {
                $remaining = $items->count() - $maxItems;
                $lines[] = "<i>... and {$remaining} more item(s)</i>";
            }
            $lines[] = "";
        }

        // Subtotal and discount
        $subtotal = (float) $order->subtotal;
        $discount = (float) $order->discount_total;
        if ($discount > 0) {
            $lines[] = "Subtotal: " . $this->formatAmount($subtotal, $currency);
            $lines[] = "Discount: -" . $this->formatAmount($discount, $currency);
        }

        $lines[] = "💵 Total: <b>{$totalFormatted}</b>";

        if (filled($order->note)) {
            $lines[] = "📝 Note: " . htmlspecialchars($order->note);
        }

        $lines[] = "";
        $lines[] = "⏱️ <i>{$date}</i>";

        return implode("\n", $lines);
    }

    /**
     * Format numbers into readable currency string with symbol.
     */
    public function formatAmount(float $amount, string $currencyCode = 'USD', ?string $symbol = null, ?int $decimals = null): string
    {
        $code = strtoupper($currencyCode);
        $isKhr = $code === 'KHR';
        $dec = $decimals !== null ? $decimals : ($isKhr ? 0 : 2);
        $sym = $symbol ?: ($isKhr ? '៛' : ($code === 'USD' ? '$' : $code . ' '));
        $formatted = number_format(abs($amount), $dec);

        return ($amount < 0 ? '-' : '') . $sym . $formatted;
    }

    /**
     * Filter inline keyboard markup to remove any buttons with invalid/localhost URLs that Telegram rejects.
     */
    public function filterReplyMarkup(array|string|null $replyMarkup): ?array
    {
        if (! $replyMarkup) {
            return null;
        }

        $markup = is_string($replyMarkup) ? json_decode($replyMarkup, true) : $replyMarkup;
        if (! is_array($markup) || empty($markup['inline_keyboard']) || ! is_array($markup['inline_keyboard'])) {
            return null;
        }

        $validKeyboard = [];
        foreach ($markup['inline_keyboard'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $validRow = [];
            foreach ($row as $button) {
                if (! is_array($button)) {
                    continue;
                }
                // If it's a URL button, ensure URL is acceptable to Telegram API
                if (isset($button['url'])) {
                    if ($this->isValidTelegramUrl($button['url'])) {
                        $validRow[] = $button;
                    }
                } else {
                    $validRow[] = $button;
                }
            }
            if (! empty($validRow)) {
                $validKeyboard[] = $validRow;
            }
        }

        if (empty($validKeyboard)) {
            return null;
        }

        return ['inline_keyboard' => $validKeyboard];
    }

    /**
     * Verify if a URL is acceptable to Telegram's inline keyboard button URL validator.
     * Telegram strictly rejects localhost, 127.0.0.1, internal IP addresses, and non-FQDN domains.
     */
    public function isValidTelegramUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        if (str_starts_with($url, 'tg://')) {
            return true;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        $host = strtolower($host);

        // Reject localhost and local development addresses
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost') || str_ends_with($host, '.test') || str_ends_with($host, '.invalid')) {
            return false;
        }

        // Host must have a dot with a recognized public-like TLD (e.g., domain.tld)
        if (! str_contains($host, '.')) {
            return false;
        }

        return true;
    }
}
