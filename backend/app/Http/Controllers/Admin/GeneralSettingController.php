<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
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

    private function validateSettings(Request $request): array
    {
        $currencyCodes = $this->currencies->getActiveOptions()->pluck('code')->all();
        $currencyRules = empty($currencyCodes)
            ? ['nullable', 'string', 'max:10']
            : ['required', 'string', Rule::in($currencyCodes)];

        return $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => $currencyRules,
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);
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
        ], is_array($tenant->general_settings) ? $tenant->general_settings : []);
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
