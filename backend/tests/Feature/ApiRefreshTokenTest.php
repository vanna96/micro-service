<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiRefreshTokenTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/api-refresh-token.sqlite');

        if (! File::exists(dirname($this->databasePath))) {
            File::makeDirectory(dirname($this->databasePath), 0755, true);
        }

        if (! File::exists($this->databasePath)) {
            File::put($this->databasePath, '');
        }

        $sqliteConnection = [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        config()->set('database.default', 'central');
        config()->set('database.connections.central', $sqliteConnection);
        config()->set('database.connections.mysql', $sqliteConnection);
        config()->set('tenancy.database.central_connection', 'central');

        DB::purge('central');
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }

    protected function tearDown(): void
    {
        if (isset($this->databasePath) && File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        foreach ($this->tenantDatabasePaths as $tenantPath) {
            if (File::exists($tenantPath)) {
                File::delete($tenantPath);
            }
        }

        parent::tearDown();
    }

    public function test_admin_login_returns_access_token_and_refresh_token(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'username' => 'admin-user',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);

        $response = $this->postJson('/v1/api/user/login', [
            'username' => 'admin-user',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.timeout', 3600)
            ->assertJsonPath('data.timeout_minutes', 60);

        $accessToken = $response->json('data.access_token');
        $refreshToken = $response->json('data.refresh_token');

        $this->assertNotEmpty($accessToken);
        $this->assertNotEmpty($refreshToken);
        $this->assertNotEquals($accessToken, $refreshToken);

        // Verify access token can access authenticated routes
        $authResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/user/auth');

        $authResponse->assertOk()
            ->assertJsonPath('user.username', 'admin-user');
    }

    public function test_admin_can_refresh_token_and_old_refresh_token_is_revoked(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'username' => 'admin-user',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);

        $loginResponse = $this->postJson('/v1/api/user/login', [
            'username' => 'admin-user',
            'password' => 'secret123',
        ]);

        $initialAccessToken = $loginResponse->json('data.access_token');
        $initialRefreshToken = $loginResponse->json('data.refresh_token');

        // Use refresh token to get a new pair
        $refreshResponse = $this->postJson('/v1/api/user/refresh', [
            'refresh_token' => $initialRefreshToken,
        ]);

        $refreshResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 3600);

        $newAccessToken = $refreshResponse->json('data.access_token');
        $newRefreshToken = $refreshResponse->json('data.refresh_token');

        $this->assertNotEmpty($newAccessToken);
        $this->assertNotEmpty($newRefreshToken);
        $this->assertNotEquals($initialAccessToken, $newAccessToken);
        $this->assertNotEquals($initialRefreshToken, $newRefreshToken);

        // Verify new access token works
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $newAccessToken,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/user/auth')
            ->assertOk()
            ->assertJsonPath('user.username', 'admin-user');

        // Token rotation: using the old refresh token MUST now fail with 401
        $reuseResponse = $this->postJson('/v1/api/user/refresh', [
            'refresh_token' => $initialRefreshToken,
        ]);

        $reuseResponse->assertStatus(401);
    }

    public function test_admin_refresh_token_accepts_bearer_header(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'username' => 'admin-user',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);

        $loginResponse = $this->postJson('/v1/api/user/login', [
            'username' => 'admin-user',
            'password' => 'secret123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        // Refresh via Authorization header
        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/auth/refresh');

        $refreshResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_refresh_token_rejects_access_token_or_invalid_token(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'username' => 'admin-user',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);

        $loginResponse = $this->postJson('/v1/api/user/login', [
            'username' => 'admin-user',
            'password' => 'secret123',
        ]);

        $accessToken = $loginResponse->json('data.access_token');

        // Passing an access token as refresh token should fail with 401
        $this->postJson('/v1/api/user/refresh', [
            'refresh_token' => $accessToken,
        ])->assertStatus(401);

        // Passing completely bogus token
        $this->postJson('/v1/api/user/refresh', [
            'refresh_token' => 'completely-invalid-token-string',
        ])->assertStatus(401);

        // Missing token
        $this->postJson('/v1/api/user/refresh', [])
            ->assertStatus(422);
    }

    public function test_mobile_login_and_refresh_token_lifecycle(): void
    {
        $tenant = $this->createTenant('mobile-refresh-test');

        $registerResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/register', [
            'name' => 'Mobile Shopper',
            'username' => 'mobile-shopper',
            'email' => 'shopper@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country_code' => '+855',
            'phone' => '12345678',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.timeout', 3600)
            ->assertJsonPath('data.token_type', 'Bearer');

        $initialAccessToken = $registerResponse->json('data.access_token');
        $initialRefreshToken = $registerResponse->json('data.refresh_token');

        $this->assertNotEmpty($initialAccessToken);
        $this->assertNotEmpty($initialRefreshToken);

        // Refresh mobile token
        $refreshResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/refresh', [
            'refresh_token' => $initialRefreshToken,
        ]);

        $refreshResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in', 3600);

        $newAccessToken = $refreshResponse->json('data.access_token');
        $newRefreshToken = $refreshResponse->json('data.refresh_token');

        $this->assertNotEmpty($newAccessToken);
        $this->assertNotEmpty($newRefreshToken);
        $this->assertNotEquals($initialAccessToken, $newAccessToken);
        $this->assertNotEquals($initialRefreshToken, $newRefreshToken);

        // Test that new access token can query auth/me
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $newAccessToken,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'mobile-shopper');

        // Test that the old refresh token is revoked
        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/refresh', [
            'refresh_token' => $initialRefreshToken,
        ])->assertStatus(401);
    }

    protected function createTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);
        $fallbackDatabasePath = database_path($id);

        DB::purge('tenant');

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($fallbackDatabasePath)) {
            File::delete($fallbackDatabasePath);
        }

        File::put($databasePath, '');

        $this->tenantDatabasePaths[] = $databasePath;
        $this->tenantDatabasePaths[] = $fallbackDatabasePath;

        $tenant = Tenant::query()->create([
            'id' => $id,
            'db_connection' => 'sqlite',
            'db_port' => '',
            'db_name' => $databaseName,
            'db_host' => '',
            'db_username' => '',
            'db_password' => '',
            'status' => 'Active',
        ]);

        tenancy()->initialize($tenant);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        return $tenant;
    }
}
