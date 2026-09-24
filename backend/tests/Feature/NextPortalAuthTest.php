<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NextPortalAuthTest extends TestCase
{
    private string $databasePath;
    private string $queryCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/next-portal-auth.sqlite');
        $this->queryCachePath = storage_path('framework/testing/next-portal-query-cache');

        File::ensureDirectoryExists(dirname($this->databasePath));
        File::put($this->databasePath, '');
        File::deleteDirectory($this->queryCachePath);

        $sqliteConnection = [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        config()->set('cache.default', 'array');
        config()->set('cache.portal_session_store', 'array');
        config()->set('session.driver', 'array');
        config()->set('database.default', 'central');
        config()->set('database.connections.central', $sqliteConnection);
        config()->set('database.connections.mysql', $sqliteConnection);

        DB::purge('central');
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }

    protected function tearDown(): void
    {
        if (isset($this->databasePath) && File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        if (isset($this->queryCachePath)) {
            File::deleteDirectory($this->queryCachePath);
        }

        parent::tearDown();
    }

    public function test_administrator_can_choose_only_an_assigned_tenant_and_redeem_once(): void
    {
        $user = $this->createUser();
        $assignedTenant = $this->createTenant('rechna', 'rechna.localhost');
        $unassignedTenant = $this->createTenant('other', 'other.localhost');
        $user->tenants()->sync([$assignedTenant->id]);

        $this->postJson('http://localhost/next/auth/login', [
            'username' => $user->username,
            'password' => 'secret123',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenants.0.id', 'rechna')
            ->assertJsonPath('data.tenants.0.domains.0', 'rechna.localhost')
            ->assertJsonCount(1, 'data.tenants');

        $this->assertAuthenticatedAs($user);
        $this->get('http://localhost/home')->assertRedirect('/admin/dashboard');
        $this->get('http://localhost/admin/dashboard')->assertOk();

        $this->postJson('http://localhost/next/auth/tenant-handoff', [
            'tenant_id' => $unassignedTenant->id,
            'domain' => 'other.localhost',
        ])->assertForbidden();

        $handoffResponse = $this->postJson('http://localhost/next/auth/tenant-handoff', [
            'tenant_id' => $assignedTenant->id,
            'domain' => 'rechna.localhost',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $handoffUrl = (string) $handoffResponse->json('url');
        $this->assertStringStartsWith('http://rechna.localhost/next/auth/tenant-handoff?ticket=', $handoffUrl);

        parse_str((string) parse_url($handoffUrl, PHP_URL_QUERY), $query);
        $ticket = (string) ($query['ticket'] ?? '');
        $this->assertNotSame('', $ticket);

        $this->get('http://rechna.localhost/next/auth/tenant-handoff?ticket=' . urlencode($ticket))
            ->assertRedirect('/');

        $this->getJson('http://rechna.localhost/next/auth/session')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('data.tenant.id', 'rechna')
            ->assertJsonPath('data.tenant.domain', 'rechna.localhost');

        $this->get('http://rechna.localhost/next/auth/tenant-handoff?ticket=' . urlencode($ticket))
            ->assertRedirect('http://localhost/');
    }

    public function test_tenant_domain_has_no_portal_session_without_a_handoff(): void
    {
        $this->getJson('http://rechna.localhost/next/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('authenticated', false);
    }

    public function test_query_cache_invalidation_does_not_end_the_portal_session(): void
    {
        $user = $this->createUser();

        config()->set('cache.stores.query_cache_test', [
            'driver' => 'file',
            'path' => $this->queryCachePath,
        ]);
        config()->set('cache.default', 'query_cache_test');
        Cache::setDefaultDriver('query_cache_test');

        $this->postJson('http://localhost/next/auth/login', [
            'username' => $user->username,
            'password' => 'secret123',
        ])->assertOk();

        Cache::put('query-cache-probe', true, 120);
        User::flushQueryCache();

        $this->assertFalse(Cache::has('query-cache-probe'));
        $this->getJson('http://localhost/next/auth/session')
            ->assertOk()
            ->assertJsonPath('authenticated', true);
    }

    public function test_portal_login_rejects_a_tenant_domain(): void
    {
        $user = $this->createUser();

        $this->postJson('http://rechna.localhost/next/auth/login', [
            'username' => $user->username,
            'password' => 'secret123',
        ])->assertForbidden();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Portal Administrator',
            'username' => 'portal-admin',
            'email' => 'portal@example.com',
            'phone' => '012345678',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
    }

    private function createTenant(string $id, string $domain): Tenant
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
            'id' => $id,
            'status' => 'Active',
        ]));

        $tenant->domains()->create(['domain' => $domain]);

        return $tenant;
    }
}
