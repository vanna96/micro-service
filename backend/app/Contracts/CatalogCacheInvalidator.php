<?php

namespace App\Contracts;

interface CatalogCacheInvalidator
{
    /** @param array<int, string> $resources */
    public function invalidate(array $resources, ?string $tenantId = null): void;

    /** @param array<int, string> $resources */
    public function invalidateAfterCommit(
        array $resources,
        ?string $connection = null,
        ?string $tenantId = null
    ): void;
}
