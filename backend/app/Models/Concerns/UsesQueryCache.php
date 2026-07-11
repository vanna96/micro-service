<?php

namespace App\Models\Concerns;

trait UsesQueryCache
{
    protected static $flushCacheOnUpdate = true;

    public function cachePrefixValue(): string
    {
        return (string) config('query-cache.prefix', 'micro_service_backend');
    }

    protected function buildQueryCacheBaseTags(): array
    {
        $connection = $this->getConnection();
        $databaseIdentifier = null;

        if ($connection && method_exists($connection, 'getDatabaseName')) {
            $databaseIdentifier = $connection->getDatabaseName();
        }

        return [
            $this->cachePrefixValue(),
            static::class,
            (string) ($databaseIdentifier ?: $this->getConnectionName() ?: config('database.default')),
            $this->getTable(),
        ];
    }
}
