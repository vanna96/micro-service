<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Services\MailNotificationService;
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
        $this->middleware('admin.permission:general_settings.edit')->only(['update', 'testTelegram', 'testMail']);
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
            ->with('status', __('General setting updated successfully.'));
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

    public function testMail(Request $request, MailNotificationService $mail): JsonResponse|RedirectResponse
    {
        $tenant = $this->requiredTenant();
        $recipientEmail = trim((string) $request->input('recipient_email'));

        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'A valid recipient email address is required to send a test message.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            return redirect()->back()->withErrors(['recipient_email' => $error]);
        }

        $credentials = [
            'mail_mailer' => $request->input('mail_mailer'),
            'mail_host' => $request->input('mail_host'),
            'mail_port' => $request->input('mail_port'),
            'mail_username' => $request->input('mail_username'),
            'mail_password' => $request->input('mail_password'),
            'mail_encryption' => $request->input('mail_encryption'),
            'mail_from_address' => $request->input('mail_from_address'),
            'mail_from_name' => $request->input('mail_from_name'),
        ];

        $result = $mail->sendTestEmail($recipientEmail, $credentials, $tenant);

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
            'mail_notifications_enabled' => ['nullable', 'boolean'],
            'mail_mailer' => ['nullable', 'string', 'in:smtp,sendmail,log'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null,none'],
            'mail_from_address' => ['nullable', 'string', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_order_notifications_enabled' => ['nullable', 'boolean'],
            'facebook_login_enabled' => ['nullable', 'boolean'],
            'facebook_app_id' => ['nullable', 'string', 'max:255'],
            'facebook_app_secret' => ['nullable', 'string', 'max:255'],
            'facebook_graph_version' => ['nullable', 'regex:/^v\d+\.\d+$/'],
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
        if ($request->has('mail_notifications_enabled')) {
            $validated['mail_notifications_enabled'] = $request->boolean('mail_notifications_enabled');
        }
        if ($request->has('mail_order_notifications_enabled')) {
            $validated['mail_order_notifications_enabled'] = $request->boolean('mail_order_notifications_enabled');
        }
        if ($request->has('facebook_login_enabled')) {
            $validated['facebook_login_enabled'] = $request->boolean('facebook_login_enabled');
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
            'mail_notifications_enabled' => false,
            'mail_mailer' => 'smtp',
            'mail_host' => '',
            'mail_port' => 1025,
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'null',
            'mail_from_address' => '',
            'mail_from_name' => '',
            'mail_order_notifications_enabled' => false,
            'facebook_login_enabled' => false,
            'facebook_app_id' => '',
            'facebook_app_secret' => '',
            'facebook_graph_version' => 'v24.0',
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
