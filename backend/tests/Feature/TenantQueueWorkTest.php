<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantQueueWorkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_tenant_database_has_isolated_jobs_and_failed_jobs_tables(): void
    {
        $tenant = Tenant::find('RechnaDB');
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            $this->assertTrue(Schema::hasTable('jobs'));
            $this->assertTrue(Schema::hasTable('failed_jobs'));
        });
    }

    public function test_job_dispatched_in_tenant_context_is_stored_in_tenant_jobs_table(): void
    {
        $tenant = Tenant::find('RechnaDB');
        $this->assertNotNull($tenant);

        // Clear existing test jobs
        $tenant->run(fn () => DB::table('jobs')->truncate());
        DB::connection('central')->table('jobs')->truncate();

        // Dispatch within tenant context
        $tenant->run(function () {
            SendTelegramMessageJob::dispatch('Tenant isolation test message', 'TOKEN', 'CHAT');
        });

        // Assert job is in RechnaDB.jobs and NOT in central.jobs
        $tenantJobsCount = $tenant->run(fn () => DB::table('jobs')->count());
        $centralJobsCount = DB::connection('central')->table('jobs')->count();

        $this->assertSame(1, $tenantJobsCount);
        $this->assertSame(0, $centralJobsCount);
    }

    public function test_tenants_queue_work_command_processes_tenant_job(): void
    {
        $tenant = Tenant::find('RechnaDB');
        $this->assertNotNull($tenant);

        // Clear existing jobs
        $tenant->run(fn () => DB::table('jobs')->truncate());

        // Dispatch job in tenant
        $tenant->run(function () {
            SendTelegramMessageJob::dispatch('Worker test message', 'TOKEN', 'CHAT');
        });

        $this->assertSame(1, $tenant->run(fn () => DB::table('jobs')->count()));

        // Run tenants:queue-work for one job
        Artisan::call('tenants:queue-work', ['--once' => true]);

        // Assert job was processed and removed from RechnaDB.jobs
        $this->assertSame(0, $tenant->run(fn () => DB::table('jobs')->count()));
    }
}
