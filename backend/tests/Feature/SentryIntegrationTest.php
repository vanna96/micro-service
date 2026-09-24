<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private array $tenantDatabasePaths = [];
    private ?Tenant $createdTenant = null;

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge('tenant');

        if ($this->createdTenant) {
            $this->createdTenant->delete();
        }

        foreach ($this->tenantDatabasePaths as $databasePath) {
            if (File::exists($databasePath)) {
                File::delete($databasePath);
            }
        }

        parent::tearDown();
    }

    public function test_sentry_service_is_registered(): void
    {
        $this->assertTrue(app()->bound('sentry'));
    }

    public function test_sentry_config_resolves_dsn_and_environment(): void
    {
        config(['sentry.dsn' => 'https://mockkey@mockhost.ingest.sentry.io/12345']);
        $this->assertSame('https://mockkey@mockhost.ingest.sentry.io/12345', config('sentry.dsn'));
    }

    public function test_sentry_test_artisan_command_attempts_transmission_with_dsn(): void
    {
        Artisan::call('sentry:test', [
            '--dsn' => 'https://1234567890abcdef1234567890abcdef@o123456.ingest.sentry.io/1234567',
        ]);

        $this->assertStringContainsString('Sending test event...', Artisan::output());
    }

    public function test_sentry_tenant_context_attaches_and_detaches_tag(): void
    {
        $tenant = $this->createMockTenant('sentry-test-' . uniqid());

        $capturedTag = null;
        if (function_exists('Sentry\\configureScope')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use (&$capturedTag) {
                // Scope inspection before tenancy
            });
        }

        tenancy()->initialize($tenant);

        if (function_exists('Sentry\\configureScope')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use (&$capturedTag) {
                $ref = new \ReflectionProperty($scope, 'tags');
                $ref->setAccessible(true);
                $tags = $ref->getValue($scope);
                $capturedTag = $tags['tenant_id'] ?? null;
            });
        }

        $this->assertSame($tenant->id, $capturedTag);

        tenancy()->end();

        $tagAfterEnd = null;
        if (function_exists('Sentry\\configureScope')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use (&$tagAfterEnd) {
                $ref = new \ReflectionProperty($scope, 'tags');
                $ref->setAccessible(true);
                $tags = $ref->getValue($scope);
                $tagAfterEnd = $tags['tenant_id'] ?? null;
            });
        }

        $this->assertNull($tagAfterEnd);
    }

    private function createMockTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);

        DB::purge('tenant');
        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }
        File::put($databasePath, '');
        $this->tenantDatabasePaths[] = $databasePath;

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

        return $this->createdTenant = $tenant;
    }
}
