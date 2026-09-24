<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneralSettingController extends Controller
{
    protected CurrencyRepository $currencies;

    public function __construct(CurrencyRepository $currencies)
    {
        $this->currencies = $currencies;
        $this->middleware('admin.permission:general_settings.view')->only(['index']);
        $this->middleware('admin.permission:general_settings.edit')->only(['update', 'testTelegram']);
    }

    public function index(): View
    {
        $tenant = $this->requiredTenant();
        $currencyOptions = $this->currencies->getActiveOptions();

        return view('admin.general-settings.index', [
            'selectedTenant' => $tenant,
            'generalSettings' => $this->generalSettings($tenant),
            'timezones' => timezone_identifiers_list(),
            'currencyOptions' => $currencyOptions,
            'hasCurrencies' => $currencyOptions->isNotEmpty(),
            'canEditGeneralSettings' => admin_has_permission('general_settings.edit'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->requiredTenant();
        $validated = $this->validateSettings($request);
        $generalSettings = $tenant->general_settings;

        if (! is_array($generalSettings)) {
            $generalSettings = [];
        }

        $tenant->forceFill([
            'general_settings' => array_merge($generalSettings, $validated),
        ])->save();

        return redirect()
            ->route('admin.general-settings.index')
            ->with('status', 'General setting updated successfully.');
    }

    public function testTelegram(Request $request, TelegramNotificationService $telegram): JsonResponse|RedirectResponse
    {
        $tenant = $this->requiredTenant();
        $generalSettings = is_array($tenant->general_settings) ? $tenant->general_settings : [];

        $botToken = trim((string) ($request->input('telegram_bot_token') ?: ($generalSettings['telegram_bot_token'] ?? config('telegram.bot_token'))));
        $chatId = trim((string) ($request->input('telegram_chat_id') ?: ($generalSettings['telegram_chat_id'] ?? config('telegram.chat_id'))));

        if ($botToken === '' || $chatId === '') {
            $error = 'Both Telegram Bot Token and Chat ID are required to send a test message.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            return redirect()->back()->withErrors(['telegram_bot_token' => $error]);
        }

        $type = $request->input('type', 'sample_order');
        if ($type === 'ping') {
            $result = $telegram->sendTestMessage($botToken, $chatId, admin_tenant_display_name($tenant));
        } else {
            $result = $telegram->sendSampleOrderNotification($botToken, $chatId, $tenant);
        }

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 400);
        }

        return redirect()
            ->back()
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    private function validateSettings(Request $request): array
    {
        $rules = [
            'store_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'telegram_notifications_enabled' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'telegram_error_log_enabled' => ['nullable', 'boolean'],
            'telegram_error_log_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_error_log_chat_id' => ['nullable', 'string', 'max:255'],
            'terms_conditions' => ['nullable', 'string'],
            'privacy_policy' => ['nullable', 'string'],
            'minimum_mobile_version' => ['nullable', 'string', 'max:20'],
            'latest_mobile_version' => ['nullable', 'string', 'max:20'],
            'store_url_ios' => ['nullable', 'url', 'max:500'],
            'store_url_android' => ['nullable', 'url', 'max:500'],
        ];

        $fieldsToValidate = array_intersect_key($rules, $request->all());
        $validated = $request->validate($fieldsToValidate);

        if ($request->has('telegram_notifications_enabled')) {
            $validated['telegram_notifications_enabled'] = $request->boolean('telegram_notifications_enabled');
        }
        if ($request->has('telegram_error_log_enabled')) {
            $validated['telegram_error_log_enabled'] = $request->boolean('telegram_error_log_enabled');
        }

        return $validated;
    }

    private function generalSettings(Tenant $tenant): array
    {
        return array_merge([
            'store_name' => admin_tenant_display_name($tenant),
            'contact_email' => '',
            'contact_phone' => '',
            'address' => '',
            'currency' => '',
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
            'receipt_footer' => '',
            'telegram_notifications_enabled' => false,
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'telegram_error_log_enabled' => false,
            'telegram_error_log_bot_token' => '',
            'telegram_error_log_chat_id' => '',
            'terms_conditions' => '',
            'privacy_policy' => '',
            'minimum_mobile_version' => '1.0.0',
            'latest_mobile_version' => '1.0.0',
            'store_url_ios' => '',
            'store_url_android' => '',
        ], is_array($tenant->general_settings) ? $tenant->general_settings : []);
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
