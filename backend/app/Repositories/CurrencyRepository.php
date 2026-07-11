<?php

namespace App\Repositories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CurrencyRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = Currency::class;

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->currencyModel()
            ->newQuery()
            ->cacheFor($this->currencyCacheTtl())
            ->cachePrefix($this->currencyListingCachePrefix())
            ->cacheTags($this->currencyListingCacheTags())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%")
                        ->orWhere('decimal_places', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('code');

        $this->reportCacheState($query, 'admin.currencies.index');

        return $query->get();
    }

    public function getActiveOptions(): Collection
    {
        $query = $this->currencyModel()
            ->newQuery()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('code');

        $this->reportCacheState($query, 'admin.currencies.options');

        return $query->get();
    }

    public function loadForAdminEdit(int $currencyId): Currency
    {
        $query = $this->currencyModel()
            ->newQuery()
            ->whereKey($currencyId);

        $this->reportCacheState($query, 'admin.currencies.edit');

        return $query->firstOrFail();
    }

    public function findActiveByCode(?string $code): ?Currency
    {
        $normalizedCode = strtoupper(trim((string) $code));

        if ($normalizedCode === '') {
            return null;
        }

        return $this->currencyModel()
            ->newQuery()
            ->where('status', 'Active')
            ->where('code', $normalizedCode)
            ->first();
    }

    public function findActiveById(?int $currencyId): ?Currency
    {
        if (! $currencyId) {
            return null;
        }

        return $this->currencyModel()
            ->newQuery()
            ->where('status', 'Active')
            ->whereKey($currencyId)
            ->first();
    }

    public function createForAdmin(array $attributes): Currency
    {
        $currency = $this->currencyModel();
        $currency->fill($this->normalizeAttributes($attributes));
        $currency->save();

        Currency::flushQueryCache();

        return $currency;
    }

    public function updateForAdmin(Currency $currency, array $attributes): Currency
    {
        $currency->fill($this->normalizeAttributes($attributes));
        $currency->save();

        Currency::flushQueryCache();

        return $currency;
    }

    public function deleteForAdmin(Currency $currency): void
    {
        $currency->delete();

        Currency::flushQueryCache();
    }

    protected function normalizeAttributes(array $attributes): array
    {
        $attributes['code'] = strtoupper(trim((string) ($attributes['code'] ?? '')));
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['symbol'] = ($attributes['symbol'] ?? null) !== null
            ? trim((string) $attributes['symbol'])
            : null;
        $attributes['decimal_places'] = max((int) ($attributes['decimal_places'] ?? ($attributes['code'] === 'KHR' ? 0 : 2)), 0);
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);

        return $attributes;
    }

    protected function currencyModel(): Currency
    {
        /** @var \App\Models\Currency $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name ?: 'tenant');
        }

        return $model;
    }

    protected function currencyCacheTtl(): int
    {
        return (int) config('query-cache.models.currency', config('query-cache.default_ttl', 300));
    }

    protected function currencyListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-currency-listing:' . tenant()->getTenantKey()
            : 'tenant-currency-listing:central';

        return [
            'admin-currency-listing',
            $tenantTag,
        ];
    }

    protected function currencyListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':currency-listing:' . $tenantKey;
    }

    protected function reportCacheState(EloquentBuilder $query, string $context): void
    {
        if (! config('app.debug')) {
            return;
        }

        $baseQuery = $query->getQuery();

        if (! method_exists($baseQuery, 'getCacheKey') || ! method_exists($baseQuery, 'getCache')) {
            return;
        }

        $cacheKey = $baseQuery->getCacheKey('get');
        $cacheHit = $baseQuery->getCache()->has($cacheKey);
        $payload = [
            'context' => $context,
            'cache_hit' => $cacheHit,
            'cache_key' => $cacheKey,
            'ttl' => $this->currencyCacheTtl(),
        ];

        Log::debug('Currency repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
