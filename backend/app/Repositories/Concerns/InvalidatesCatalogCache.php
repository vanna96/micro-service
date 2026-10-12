<?php

namespace App\Repositories\Concerns;

use App\Contracts\CatalogCacheInvalidator;

trait InvalidatesCatalogCache
{
    /** @param array<int, string> $resources */
    protected function invalidateCatalogCache(array $resources, ?string $connection = null): void
    {
        app(CatalogCacheInvalidator::class)->invalidateAfterCommit(
            $resources,
            $connection ?? config('database.default'),
            tenant() ? (string) tenant()->getTenantKey() : null
        );
    }
}
