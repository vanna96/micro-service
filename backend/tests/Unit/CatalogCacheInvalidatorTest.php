<?php

namespace Tests\Unit;

use App\Services\QueryCacheTelemetry;
use App\Services\TaggedCatalogCacheInvalidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CatalogCacheInvalidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.catalog_cache_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('query-cache.store', 'redis');
        DB::purge('catalog_cache_test');
    }

    protected function tearDown(): void
    {
        DB::disconnect('catalog_cache_test');
        parent::tearDown();
    }

    public function test_rollback_discards_invalidation_and_a_later_commit_still_flushes(): void
    {
        $repository = Mockery::mock();
        $tagged = Mockery::mock();
        Cache::shouldReceive('store')->once()->with('redis')->andReturn($repository);
        $repository->shouldReceive('tags')->once()->with(['catalog:tenant-a:items'])->andReturn($tagged);
        $tagged->shouldReceive('flush')->once();

        $invalidator = new TaggedCatalogCacheInvalidator(app(QueryCacheTelemetry::class));
        $connection = DB::connection('catalog_cache_test');

        $connection->beginTransaction();
        $invalidator->invalidateAfterCommit(['items'], 'catalog_cache_test', 'tenant-a');
        $connection->rollBack();

        $connection->beginTransaction();
        $invalidator->invalidateAfterCommit(['items'], 'catalog_cache_test', 'tenant-a');
        $connection->commit();
    }
}
