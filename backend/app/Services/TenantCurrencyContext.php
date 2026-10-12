<?php

namespace App\Services;

use App\Cache\CatalogCacheTags;
use App\Models\Currency;
use App\Models\RateIndexValue;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class TenantCurrencyContext
{
    private array $baseCurrencies = [];
    private array $activeCurrencies = [];
    private array $dailyRates = [];

    public function __construct(private CatalogQueryCache $cache)
    {
    }

    public function baseCurrency(?Tenant $tenantModel = null): ?Currency
    {
        $tenantModel ??= tenant() ?: admin_current_tenant();
        $tenantKey = $this->scopeKey($tenantModel ? (string) $tenantModel->getTenantKey() : 'central');

        if (array_key_exists($tenantKey, $this->baseCurrencies)) {
            return $this->baseCurrencies[$tenantKey];
        }

        $code = strtoupper(trim((string) data_get($tenantModel?->general_settings, 'currency', '')));
        if ($code === '') {
            return $this->baseCurrencies[$tenantKey] = null;
        }

        return $this->baseCurrencies[$tenantKey] = $this->cache->run(
            CatalogCacheTags::CURRENCIES,
            $this->currencyTtl(),
            fn (bool $cached) => Currency::query()
                ->where('status', 'Active')
                ->where('code', $code),
            fn ($query) => $query->first(),
            [CatalogCacheTags::BOOTSTRAP],
            true,
            $tenantModel ? (string) $tenantModel->getTenantKey() : null
        );
    }

    public function activeCurrencies(): Collection
    {
        $tenantKey = $this->scopeKey(CatalogCacheTags::tenantKey());
        if (isset($this->activeCurrencies[$tenantKey])) {
            return $this->activeCurrencies[$tenantKey];
        }

        return $this->activeCurrencies[$tenantKey] = $this->cache->run(
            CatalogCacheTags::CURRENCIES,
            $this->currencyTtl(),
            fn (bool $cached) => Currency::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('code'),
            fn ($query) => $query->get(),
            [CatalogCacheTags::BOOTSTRAP]
        );
    }

    /** @return array<int, float> */
    public function todayRates(): array
    {
        $tenantKey = CatalogCacheTags::tenantKey();
        $settings = is_array(tenant()?->general_settings) ? tenant()->general_settings : [];
        $timezone = (string) ($settings['timezone'] ?? config('app.timezone', 'UTC'));
        $today = Carbon::now($timezone);
        $memoKey = $this->scopeKey($tenantKey).':'.$today->toDateString();

        if (isset($this->dailyRates[$memoKey])) {
            return $this->dailyRates[$memoKey];
        }

        $rows = $this->cache->run(
            CatalogCacheTags::RATES,
            (int) config('query-cache.models.rate', 300),
            fn (bool $cached) => RateIndexValue::query()
                ->where('dataset_type', 'exchange_rate')
                ->where('year', $today->year)
                ->where('month', $today->month)
                ->where('day', $today->day),
            fn ($query) => $query->get(),
            [CatalogCacheTags::CURRENCIES, CatalogCacheTags::BOOTSTRAP]
        );

        $rates = $rows->mapWithKeys(fn (RateIndexValue $rate): array => [
            (int) $rate->currency_id => (float) $rate->value,
        ])->all();

        $baseCurrency = $this->baseCurrency();
        if ($baseCurrency) {
            $rates[(int) $baseCurrency->id] = 1.0;
        }

        return $this->dailyRates[$memoKey] = $rates;
    }

    private function currencyTtl(): int
    {
        return (int) config('query-cache.models.currency', 300);
    }

    private function scopeKey(string $tenantKey): string
    {
        $requestKey = app()->bound('request') ? (string) spl_object_id(request()) : 'console';

        return $requestKey.':'.$tenantKey;
    }
}
