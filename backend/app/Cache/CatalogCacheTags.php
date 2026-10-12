<?php

namespace App\Cache;

final class CatalogCacheTags
{
    public const ITEMS = 'items';
    public const CATEGORIES = 'categories';
    public const BRANCHES = 'branches';
    public const BANNERS = 'banners';
    public const CURRENCIES = 'currencies';
    public const RATES = 'rates';
    public const PROMOTIONS = 'promotions';
    public const BOOTSTRAP = 'bootstrap';
    public const STORES = 'stores';
    public const TENANTS = 'tenants';

    public static function tenantKey(?string $tenantId = null): string
    {
        if ($tenantId === null && tenant()) {
            $tenantId = (string) tenant()->getTenantKey();
        }

        return $tenantId !== null && $tenantId !== '' ? $tenantId : 'central';
    }

    public static function resource(string $resource, ?string $tenantId = null): string
    {
        return 'catalog:'.self::tenantKey($tenantId).':'.$resource;
    }

    /** @return array<int, string> */
    public static function resources(array $resources, ?string $tenantId = null): array
    {
        return array_values(array_unique(array_map(
            fn (string $resource): string => self::resource($resource, $tenantId),
            $resources
        )));
    }
}
