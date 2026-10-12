<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CaddyCheckTest extends TestCase
{
    public function test_allowed_tenant_domain_is_cached_for_repeated_caddy_checks(): void
    {
        $this->withoutMiddleware();
        config(['tenancy.central_domains' => []]);

        $domain = 'shop.example.test';
        $cacheKey = 'caddy-check:allowed-domain:'.hash('sha256', $domain);
        $query = Mockery::mock();
        $connection = Mockery::mock();

        Cache::shouldReceive('get')->once()->with($cacheKey)->andReturn(null);
        Cache::shouldReceive('put')->once()->with($cacheKey, true, 60);
        DB::shouldReceive('connection')->once()->with('central')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('domains')->andReturn($query);
        $query->shouldReceive('where')->once()->with('domain', $domain)->andReturnSelf();
        $query->shouldReceive('exists')->once()->andReturnTrue();

        $this->get('/caddy-check?domain='.$domain)
            ->assertOk()
            ->assertSeeText('OK');
    }

    public function test_cached_allowed_domain_skips_the_database(): void
    {
        $this->withoutMiddleware();
        config(['tenancy.central_domains' => []]);

        $domain = 'cached.example.test';
        $cacheKey = 'caddy-check:allowed-domain:'.hash('sha256', $domain);

        Cache::shouldReceive('get')->once()->with($cacheKey)->andReturn(true);
        Cache::shouldReceive('put')->never();
        DB::shouldReceive('connection')->never();

        $this->get('/caddy-check?domain='.$domain)->assertOk();
    }

    public function test_unknown_domain_is_not_cached(): void
    {
        $this->withoutMiddleware();
        config(['tenancy.central_domains' => []]);

        $domain = 'unknown.example.test';
        $cacheKey = 'caddy-check:allowed-domain:'.hash('sha256', $domain);
        $query = Mockery::mock();
        $connection = Mockery::mock();

        Cache::shouldReceive('get')->once()->with($cacheKey)->andReturn(null);
        Cache::shouldReceive('put')->never();
        DB::shouldReceive('connection')->once()->with('central')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('domains')->andReturn($query);
        $query->shouldReceive('where')->once()->with('domain', $domain)->andReturnSelf();
        $query->shouldReceive('exists')->once()->andReturnFalse();

        $this->get('/caddy-check?domain='.$domain)->assertForbidden();
    }
}
