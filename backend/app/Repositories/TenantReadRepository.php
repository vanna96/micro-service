<?php

namespace App\Repositories;

use App\Cache\CatalogCacheTags;
use App\Models\Tenant;
use App\Services\CatalogQueryCache;
use Illuminate\Database\Eloquent\Collection;

class TenantReadRepository
{
    public function __construct(private CatalogQueryCache $cache)
    {
    }

    public function activeStores(): Collection
    {
        return $this->cache->run(
            CatalogCacheTags::STORES,
            $this->ttl(),
            fn (bool $cached) => Tenant::query()
                ->where('status', 'Active')
                ->whereHas('domains')
                ->with(['domains' => $this->domainRelation($cached)])
                ->orderBy('alias'),
            fn ($query) => $query->get(),
            [CatalogCacheTags::TENANTS],
            true,
            null
        );
    }

    public function resolve(string $identifier): ?Tenant
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        foreach (['id', 'alias', 'domain'] as $strategy) {
            $tenant = $this->cache->run(
                CatalogCacheTags::TENANTS,
                $this->ttl(),
                function (bool $cached) use ($identifier, $strategy) {
                    $query = Tenant::query()->with(['domains' => $this->domainRelation($cached)]);

                    return match ($strategy) {
                        'id' => $query->whereKey($identifier),
                        'alias' => $query->where('alias', $identifier),
                        default => $query->whereHas('domains', function ($domainQuery) use ($identifier) {
                            $domainQuery->where('domain', $identifier)
                                ->orWhere('domain', 'like', $identifier.'.%');
                        }),
                    };
                },
                fn ($query) => $query->first(),
                [CatalogCacheTags::STORES],
                true,
                null
            );

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return null;
    }

    private function ttl(): int
    {
        return (int) config('query-cache.models.tenant', 3600);
    }

    private function domainRelation(bool $cached): callable
    {
        return function ($query) use ($cached): void {
            if ($cached) {
                $this->cache->apply(
                    $query,
                    CatalogCacheTags::TENANTS,
                    $this->ttl(),
                    [CatalogCacheTags::STORES],
                    null
                );
            } else {
                $query->dontCache();
            }
        };
    }
}
