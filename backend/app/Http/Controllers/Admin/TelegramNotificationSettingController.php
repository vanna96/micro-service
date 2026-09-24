<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramNotificationSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin.permission:general_settings.view')->only(['index']);
        $this->middleware('admin.permission:general_settings.edit')->only(['update', 'test']);
    }

    public function index(TelegramNotificationService $telegram): View
    {
        $tenant = $this->requiredTenant();
        $generalSettings = is_array($tenant->general_settings) ? $tenant->general_settings : [];

        // Order Notifications
        $botToken = trim((string) ($generalSettings['telegram_bot_token'] ?? ''));
        $chatId = trim((string) ($generalSettings['telegram_chat_id'] ?? ''));
        $isEnabled = (bool) ($generalSettings['telegram_notifications_enabled'] ?? false);
        $isConfigured = ! empty($botToken) && ! empty($chatId);
        $globalBotToken = (string) config('telegram.bot_token', '');
        $globalChatId = (string) config('telegram.chat_id', '');

        // Error Log Notifications
        $errorLogBotToken = trim((string) ($generalSettings['telegram_error_log_bot_token'] ?? ''));
        $errorLogChatId = trim((string) ($generalSettings['telegram_error_log_chat_id'] ?? ''));
        $isErrorLogEnabled = (bool) ($generalSettings['telegram_error_log_enabled'] ?? false);
        $resolvedErrorToken = $errorLogBotToken ?: $botToken ?: $globalBotToken;
        $resolvedErrorChat = $errorLogChatId ?: $chatId ?: $globalChatId;
        $isErrorLogConfigured = ! empty($resolvedErrorToken) && ! empty($resolvedErrorChat);

        return view('admin.telegram-notifications.index', [
            'selectedTenant' => $tenant,
            'storeName' => admin_tenant_display_name($tenant),
            'currency' => strtoupper((string) ($generalSettings['currency'] ?? 'USD')),

            // Order Notifications
            'isEnabled' => $isEnabled,
            'botToken' => $botToken,
            'chatId' => $chatId,
            'isConfigured' => $isConfigured,
            'hasGlobalFallback' => ! empty($globalBotToken) && ! empty($globalChatId),

            // Error Log Notifications
            'isErrorLogEnabled' => $isErrorLogEnabled,
            'errorLogBotToken' => $errorLogBotToken,
            'errorLogChatId' => $errorLogChatId,
            'resolvedErrorToken' => $resolvedErrorToken,
            'resolvedErrorChat' => $resolvedErrorChat,
            'isErrorLogConfigured' => $isErrorLogConfigured,
            'globalErrorLogBotToken' => (string) config('telegram.error_log_bot_token', ''),
            'globalErrorLogChatId' => (string) config('telegram.error_log_chat_id', ''),
            'errorLogLevel' => strtoupper((string) config('telegram.error_log_level', 'error')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->requiredTenant();

        $validated = $request->validate([
            // Order Notifications
            'telegram_notifications_enabled' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],

            // Error Log Notifications
            'telegram_error_log_enabled' => ['nullable', 'boolean'],
            'telegram_error_log_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_error_log_chat_id' => ['nullable', 'string', 'max:255'],
        ]);

        $generalSettings = is_array($tenant->general_settings) ? $tenant->general_settings : [];

        // Order Notifications
        $generalSettings['telegram_notifications_enabled'] = $request->boolean('telegram_notifications_enabled');
        $generalSettings['telegram_bot_token'] = trim((string) ($validated['telegram_bot_token'] ?? ''));
        $generalSettings['telegram_chat_id'] = trim((string) ($validated['telegram_chat_id'] ?? ''));

        // Error Log Notifications
        $generalSettings['telegram_error_log_enabled'] = $request->boolean('telegram_error_log_enabled');
        $generalSettings['telegram_error_log_bot_token'] = trim((string) ($validated['telegram_error_log_bot_token'] ?? ''));
        $generalSettings['telegram_error_log_chat_id'] = trim((string) ($validated['telegram_error_log_chat_id'] ?? ''));

        $tenant->forceFill([
            'general_settings' => $generalSettings,
        ])->save();

        $tab = $request->input('active_tab', 'orders');

        return redirect()
            ->route('admin.telegram-notifications.index', ['tab' => $tab])
            ->with('status', 'Telegram notification settings saved successfully.');
    }

    public function test(Request $request, TelegramNotificationService $telegram): JsonResponse|RedirectResponse
    {
        $tenant = $this->requiredTenant();
        $generalSettings = is_array($tenant->general_settings) ? $tenant->general_settings : [];
        $type = $request->input('type', 'sample_order');
        $isErrorLogTest = str_starts_with($type, 'error_');

        if ($isErrorLogTest) {
            $botToken = trim((string) ($request->input('telegram_error_log_bot_token')
                ?: ($request->input('telegram_bot_token')
                ?: ($generalSettings['telegram_error_log_bot_token']
                ?? ($generalSettings['telegram_bot_token']
                ?? config('telegram.error_log_bot_token', config('telegram.bot_token')))))));

            $chatId = trim((string) ($request->input('telegram_error_log_chat_id')
                ?: ($request->input('telegram_chat_id')
                ?: ($generalSettings['telegram_error_log_chat_id']
                ?? ($generalSettings['telegram_chat_id']
                ?? config('telegram.error_log_chat_id', config('telegram.chat_id')))))));

            if ($botToken === '' || $chatId === '') {
                $error = 'Both Bot Token and Chat ID are required to send an error alert test.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
                return redirect()->back()->withErrors(['telegram_error_log_bot_token' => $error]);
            }

            $mode = $type === 'error_ping' ? 'ping' : 'sample';
            $result = $telegram->sendTestErrorLog($botToken, $chatId, admin_tenant_display_name($tenant), $mode);
        } else {
            $botToken = trim((string) ($request->input('telegram_bot_token')
                ?: ($generalSettings['telegram_bot_token']
                ?? config('telegram.bot_token'))));

            $chatId = trim((string) ($request->input('telegram_chat_id')
                ?: ($generalSettings['telegram_chat_id']
                ?? config('telegram.chat_id'))));

            if ($botToken === '' || $chatId === '') {
                $error = 'Both Telegram Bot Token and Chat ID are required to send a test message.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
                return redirect()->back()->withErrors(['telegram_bot_token' => $error]);
            }

            if ($type === 'ping') {
                $result = $telegram->sendTestMessage($botToken, $chatId, admin_tenant_display_name($tenant));
            } else {
                $result = $telegram->sendSampleOrderNotification($botToken, $chatId, $tenant);
            }
        }

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 400);
        }

        return redirect()
            ->back()
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();
        abort_if(! $tenant, 404);

        return $tenant;
    }
}
