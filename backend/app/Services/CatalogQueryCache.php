<?php

namespace App\Services;

use App\Cache\CatalogCacheTags;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CatalogQueryCache
{
    public function __construct(private QueryCacheTelemetry $telemetry)
    {
    }

    public function run(
        string $resource,
        int $ttl,
        callable $queryFactory,
        callable $terminal,
        array $relatedResources = [],
        bool $cache = true,
        ?string $tenantId = null
    ): mixed {
        $cache = $cache && (bool) config('query-cache.enabled', true);

        try {
            $query = $queryFactory($cache);
            if ($cache) {
                $this->apply($query, $resource, $ttl, $relatedResources, $tenantId);
            } else {
                $query->dontCache();
                $this->telemetry->record('bypass');
            }

            return $terminal($query);
        } catch (Throwable $exception) {
            if (! $cache || ! $this->isCacheFailure($exception)) {
                throw $exception;
            }

            $this->telemetry->record('bypass');
            $query = $queryFactory(false);
            $query->dontCache();

            return $terminal($query);
        }
    }

    public function apply(
        mixed $query,
        string $resource,
        int $ttl,
        array $relatedResources = [],
        ?string $tenantId = null
    ): mixed {
        $tenantKey = CatalogCacheTags::tenantKey($tenantId);
        $context = $this->requestContext();
        $resources = array_values(array_unique(array_merge([$resource], $relatedResources)));

        return $query
            ->cacheFor($ttl)
            ->cacheDriver((string) config('query-cache.store', 'redis'))
            ->cachePrefix(sprintf(
                '%s:catalog:%s:%s:%s',
                config('query-cache.prefix', 'micro_service_backend'),
                $tenantKey,
                $resource,
                $context
            ))
            ->cacheTags(CatalogCacheTags::resources($resources, $tenantId));
    }

    private function requestContext(): string
    {
        if (! app()->bound('request')) {
            return sha1('console|'.app()->getLocale());
        }

        $request = request();
        $host = strtolower((string) $request->getHost());

        return sha1($host.'|'.app()->getLocale());
    }

    private function isCacheFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $class = strtolower($current::class);
            $message = strtolower($current->getMessage());

            if (str_contains($class, 'redis')
                || str_contains($class, 'predis')
                || str_contains($message, 'redis')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'connection timed out')) {
                return true;
            }
        }

        return false;
    }
}
