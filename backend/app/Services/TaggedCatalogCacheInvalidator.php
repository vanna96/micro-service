<?php

namespace App\Services;

use App\Cache\CatalogCacheTags;
use App\Contracts\CatalogCacheInvalidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class TaggedCatalogCacheInvalidator implements CatalogCacheInvalidator
{
    public function __construct(private QueryCacheTelemetry $telemetry)
    {
    }

    public function invalidate(array $resources, ?string $tenantId = null): void
    {
        $tags = CatalogCacheTags::resources($resources, $tenantId);
        if ($tags === []) {
            return;
        }

        try {
            $cache = Cache::store((string) config('query-cache.store', 'redis'));
            foreach ($tags as $tag) {
                $cache->tags([$tag])->flush();
            }
            $this->telemetry->record('invalidation', count($tags));
        } catch (Throwable) {
            // Cache invalidation must not make a committed backend write fail.
        }
    }

    public function invalidateAfterCommit(
        array $resources,
        ?string $connection = null,
        ?string $tenantId = null
    ): void {
        $connection ??= config('database.default');
        $database = DB::connection($connection);

        if ($database->transactionLevel() === 0) {
            $this->invalidate($resources, $tenantId);

            return;
        }

        // Laravel discards after-commit callbacks when the transaction rolls
        // back. Keeping no parallel pending state also makes a later
        // transaction in the same long-running worker safe after a rollback.
        $database->afterCommit(fn () => $this->invalidate($resources, $tenantId));
    }
}
