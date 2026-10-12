<?php

namespace Tests\Unit;

use App\Tenancy\FailOpenDomainTenantResolver;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant;
use Tests\TestCase;

class FailOpenDomainTenantResolverTest extends TestCase
{
    public function test_resolution_falls_back_to_the_database_when_redis_is_unavailable(): void
    {
        FailOpenDomainTenantResolver::$cacheStore = 'redis';
        $tenant = Mockery::mock(Tenant::class);
        $cache = Mockery::mock(Repository::class);
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('store')->once()->with('redis')->andReturn($cache);
        $cache->shouldReceive('get')->once()->andThrow(new \RuntimeException('Redis connection refused'));

        $resolver = new class($factory, $tenant) extends FailOpenDomainTenantResolver
        {
            public function __construct(Factory $cache, private Tenant $fallback)
            {
                parent::__construct($cache);
            }

            public function resolveWithoutCache(...$args): Tenant
            {
                return $this->fallback;
            }
        };
        $resolver::$shouldCache = true;
        $resolver::$cacheStore = 'redis';

        $this->assertSame($tenant, $resolver->resolve('store.example.test'));
    }

    public function test_cache_invalidation_does_not_break_tenant_updates_when_redis_is_unavailable(): void
    {
        FailOpenDomainTenantResolver::$cacheStore = 'redis';
        $tenant = Mockery::mock(Tenant::class);
        $cache = Mockery::mock(Repository::class);
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('store')->once()->with('redis')->andReturn($cache);
        $cache->shouldReceive('forget')->once()->andThrow(new \RuntimeException('Redis connection refused'));

        $resolver = new class($factory) extends FailOpenDomainTenantResolver
        {
            public function getArgsForTenant(Tenant $tenant): array
            {
                return [['store.example.test']];
            }
        };
        $resolver::$shouldCache = true;
        $resolver::$cacheStore = 'redis';

        $resolver->invalidateCache($tenant);
        $this->addToAssertionCount(1);
    }
}
