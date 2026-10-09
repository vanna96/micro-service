<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Repositories\TenantRepository;
use App\Services\MailNotificationService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TenantController extends Controller
{
    protected TenantRepository $tenants;

    protected CurrencyRepository $currencies;

    public function __construct(TenantRepository $tenants, CurrencyRepository $currencies)
    {
        $this->tenants = $tenants;
        $this->currencies = $currencies;
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        return view('admin.tenants.index', [
            'tenants' => $this->tenants->getAdminListing($search),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $tenant = new Tenant();

        return view('admin.tenants.create', [
            'tenant' => $tenant,
            'generalSettings' => $this->generalSettings($tenant),
            'timezones' => timezone_identifiers_list(),
            'currencyOptions' => collect(),
            'hasCurrencies' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTenant($request);

        $generalSettings = $validated['general_settings'] ?? null;
        unset($validated['general_settings']);

        $tenant = $this->tenants->createForAdmin($validated);
        $this->saveGeneralSettings($tenant, $generalSettings);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', __('Tenant created successfully.'));
    }

    public function edit(Tenant $tenant): View
    {
        $tenant = $this->tenants->loadForAdminEdit($tenant);
        $currencyOptions = $this->currencyOptionsForTenant($tenant);

        return view('admin.tenants.edit', [
            'tenant' => $tenant,
            'generalSettings' => $this->generalSettings($tenant),
            'timezones' => timezone_identifiers_list(),
            'currencyOptions' => $currencyOptions,
            'hasCurrencies' => $currencyOptions->isNotEmpty(),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateTenant($request, false, $tenant);
        $generalSettings = $validated['general_settings'] ?? null;
        unset($validated['general_settings']);

        $this->tenants->updateForAdmin($tenant, $validated);
        $this->saveGeneralSettings($tenant, $generalSettings);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', __('Tenant updated successfully.'));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->tenants->deleteForAdmin($tenant);

        return redirect()
            ->route('admin.tenants.index')
            ->with('status', __('Tenant deleted successfully.'));
    }

    public function testTelegram(Request $request, TelegramNotificationService $telegram): JsonResponse|RedirectResponse
    {
        $botToken = trim((string) $request->input('telegram_bot_token'));
        $chatId = trim((string) $request->input('telegram_chat_id'));
        $type = $request->input('type', 'sample_order');

        if ($botToken === '' || $chatId === '') {
            $error = 'Both Telegram Bot Token and Chat ID are required to send a test message.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            return redirect()->back()->withErrors(['telegram_bot_token' => $error]);
        }

        $tenant = null;
        if ($request->filled('tenant_id')) {
            $tenant = Tenant::find($request->input('tenant_id'));
        }

        if (str_starts_with($type, 'error_')) {
            $mode = $type === 'error_ping' ? 'ping' : 'sample';
            $result = $telegram->sendTestErrorLog($botToken, $chatId, $tenant ? admin_tenant_display_name($tenant) : null, $mode);
        } elseif ($type === 'ping') {
            $result = $telegram->sendTestMessage($botToken, $chatId, $tenant ? admin_tenant_display_name($tenant) : null);
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
        $recipientEmail = trim((string) $request->input('recipient_email'));

        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'A valid recipient email address is required to send a test message.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            return redirect()->back()->withErrors(['recipient_email' => $error]);
        }

        $tenant = null;
        if ($request->filled('tenant_id')) {
            $tenant = Tenant::find($request->input('tenant_id'));
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

    private function validateTenant(Request $request, bool $includeId = true, ?Tenant $tenant = null): array
    {
        $tenantTable = 'central.'.(new Tenant())->getTable();
        $domainId = $tenant?->domains?->first()?->id;

        $rules = [
            'alias' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/',
                Rule::unique('central.domains', 'domain')->ignore($domainId),
            ],
            'db_connection' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'string', 'max:20'],
            'db_name' => ['required', 'string', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];

        if ($includeId) {
            $rules['id'] = ['required', 'string', 'max:255', Rule::unique($tenantTable, 'id')];
        }

        if ($request->has('general_settings')) {
            $currencyCodes = $tenant
                ? $this->currencyOptionsForTenant($tenant)->pluck('code')->all()
                : [];
            $currencyRules = empty($currencyCodes)
                ? ['nullable', 'string', 'max:10']
                : ['required', 'string', Rule::in($currencyCodes)];

            $rules = array_merge($rules, [
                'general_settings.store_name' => ['required', 'string', 'max:255'],
                'general_settings.contact_email' => ['nullable', 'email:rfc', 'max:255'],
                'general_settings.contact_phone' => ['nullable', 'string', 'max:30'],
                'general_settings.address' => ['nullable', 'string', 'max:500'],
                'general_settings.currency' => $currencyRules,
                'general_settings.timezone' => ['required', 'string', 'timezone'],
                'general_settings.locale' => ['nullable', 'string', 'max:10'],
                'general_settings.receipt_footer' => ['nullable', 'string', 'max:500'],
                'general_settings.telegram_notifications_enabled' => ['nullable', 'boolean'],
                'general_settings.telegram_bot_token' => ['nullable', 'string', 'max:255'],
                'general_settings.telegram_chat_id' => ['nullable', 'string', 'max:255'],
                'general_settings.telegram_error_log_enabled' => ['nullable', 'boolean'],
                'general_settings.telegram_error_log_bot_token' => ['nullable', 'string', 'max:255'],
                'general_settings.telegram_error_log_chat_id' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_notifications_enabled' => ['nullable', 'boolean'],
                'general_settings.mail_mailer' => ['nullable', 'string', 'in:smtp,sendmail,log'],
                'general_settings.mail_host' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_port' => ['nullable', 'integer', 'between:1,65535'],
                'general_settings.mail_username' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_password' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null,none'],
                'general_settings.mail_from_address' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_from_name' => ['nullable', 'string', 'max:255'],
                'general_settings.mail_order_notifications_enabled' => ['nullable', 'boolean'],
                'general_settings.facebook_login_enabled' => ['nullable', 'boolean'],
                'general_settings.facebook_app_id' => ['nullable', 'string', 'max:255'],
                'general_settings.facebook_app_secret' => ['nullable', 'string', 'max:255'],
                'general_settings.facebook_graph_version' => ['nullable', 'regex:/^v\d+\.\d+$/'],
                'general_settings.google_login_enabled' => ['nullable', 'boolean'],
                'general_settings.google_client_id' => ['nullable', 'string', 'max:255'],
                'general_settings.google_client_secret' => ['nullable', 'string', 'max:255'],
            ]);
        }

        $rules['alias'][] = Rule::unique($tenantTable, 'alias')->ignore($request->route('tenant'));

        return $request->validate($rules);
    }

    private function generalSettings(Tenant $tenant): array
    {
        return array_merge([
            'store_name' => $tenant->alias ?: '',
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
            'google_login_enabled' => false,
            'google_client_id' => '',
            'google_client_secret' => '',
        ], is_array($tenant->general_settings) ? $tenant->general_settings : []);
    }

    private function saveGeneralSettings(Tenant $tenant, mixed $settings): void
    {
        if (! is_array($settings)) {
            return;
        }

        $settings['telegram_notifications_enabled'] = ! empty($settings['telegram_notifications_enabled']);
        $settings['telegram_error_log_enabled'] = ! empty($settings['telegram_error_log_enabled']);
        $settings['mail_notifications_enabled'] = ! empty($settings['mail_notifications_enabled']);
        $settings['mail_order_notifications_enabled'] = ! empty($settings['mail_order_notifications_enabled']);
        $settings['facebook_login_enabled'] = ! empty($settings['facebook_login_enabled']);
        $settings['google_login_enabled'] = ! empty($settings['google_login_enabled']);

        $existingSettings = is_array($tenant->general_settings) ? $tenant->general_settings : [];

        if (array_key_exists('mail_password', $settings) && $settings['mail_password'] === '' && ! empty($existingSettings['mail_password'])) {
            $settings['mail_password'] = $existingSettings['mail_password'];
        }

        if (array_key_exists('facebook_app_secret', $settings) && $settings['facebook_app_secret'] === '' && ! empty($existingSettings['facebook_app_secret'])) {
            $settings['facebook_app_secret'] = $existingSettings['facebook_app_secret'];
        }

        $tenant->forceFill([
            'general_settings' => array_merge($existingSettings, $settings),
        ])->save();
    }

    private function currencyOptionsForTenant(Tenant $tenant): Collection
    {
        $previousTenant = tenant();
        $mustSwitchTenant = ! $previousTenant
            || (string) $previousTenant->getTenantKey() !== (string) $tenant->getTenantKey();

        try {
            if ($mustSwitchTenant) {
                tenancy()->initialize($tenant);
            }

            return $this->currencies->getActiveOptions()
                ->map(fn ($currency) => (object) [
                    'code' => (string) $currency->code,
                    'name' => (string) $currency->name,
                ])
                ->values();
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        } finally {
            if ($mustSwitchTenant) {
                tenancy()->end();

                if ($previousTenant) {
                    tenancy()->initialize($previousTenant);
                }
            }
        }
    }
}
