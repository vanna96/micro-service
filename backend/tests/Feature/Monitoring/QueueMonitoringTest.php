<?php

namespace Tests\Feature\Monitoring;

use App\Jobs\DeductPosSaleStockJob;
use App\Jobs\SendTelegramMessageJob;
use App\Jobs\SendTelegramOrderNotificationJob;
use App\Monitoring\Contracts\MonitoringDriver;
use App\Monitoring\Services\MonitoringService;
use App\Services\TelegramNotificationService;
use App\Services\TenantJobContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Queues;
use Tests\TestCase;

class QueueMonitoringTest extends TestCase
{
    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(database_path('testing'));
        $this->tenantDatabase = 'testing/queue-monitor-'.uniqid().'.sqlite';
        File::put(database_path($this->tenantDatabase), '');
        config()->set('database.default', 'central');
        config()->set('database.connections.central', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('tenancy.database.central_connection', 'central');
        config()->set('queue.default', 'database');
        config()->set('queue.failed.database', 'central');
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.components.laravel', false);
        config()->set('monitoring.prometheus.enabled', false);
        config()->set('logging.default', 'null');
        DB::purge('central');

        $central = DB::connection('central');
        $central->getSchemaBuilder()->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        $central->table('tenants')->insert([
            'id' => 'queue-monitor-test',
            'data' => json_encode(['tenancy_db_name' => $this->tenantDatabase]),
        ]);
        $this->createQueueTables($central);
        $tenant = DB::build(['driver' => 'sqlite', 'database' => database_path($this->tenantDatabase)]);
        $this->createQueueTables($tenant);
        DB::purge($tenant->getName());
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        DB::purge('central');
        DB::purge('tenant');
        File::delete(database_path($this->tenantDatabase));
        parent::tearDown();
    }

    public function test_queue_totals_include_tenants_without_changing_application_context(): void
    {
        DB::connection('central')->table('jobs')->insert($this->jobRow());
        $tenant = DB::build(['driver' => 'sqlite', 'database' => database_path($this->tenantDatabase)]);
        $tenant->table('jobs')->insert([$this->jobRow(), $this->jobRow()]);
        $tenant->table('failed_jobs')->insert(['uuid' => 'failed-test', 'connection' => 'tenant', 'queue' => 'default', 'payload' => '{}', 'exception' => 'test']);
        DB::purge($tenant->getName());
        $storagePath = storage_path();
        $tenantConfig = config('database.connections.tenant');

        $queues = app(MonitoringService::class)->dashboard()['queues'];

        $this->assertSame(3, $queues['pending']);
        $this->assertSame(1, $queues['failed_total']);
        $this->assertSame(0, $queues['unavailable_databases']);
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());
        $this->assertSame('database', config('queue.default'));
        $this->assertSame($storagePath, storage_path());
        $this->assertSame($tenantConfig, config('database.connections.tenant'));
    }

    public function test_an_unreachable_tenant_does_not_hide_other_queue_totals(): void
    {
        DB::connection('central')->table('jobs')->insert($this->jobRow());
        DB::connection('central')->table('tenants')->insert([
            'id' => 'missing-queue-db',
            'data' => json_encode(['tenancy_db_name' => 'testing/missing-queue-db.sqlite']),
        ]);

        $queues = app(MonitoringService::class)->dashboard()['queues'];

        $this->assertSame(1, $queues['pending']);
        $this->assertSame(0, $queues['failed_total']);
        $this->assertSame(1, $queues['unavailable_databases']);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_idle_rates_are_zero_only_when_laravel_scraping_is_connected(): void
    {
        config()->set('monitoring.components.laravel', true);
        config()->set('monitoring.prometheus.enabled', true);
        $driver = $this->mock(MonitoringDriver::class);
        $driver->shouldReceive('isConfigured', 'isAvailable')->andReturn(true);
        $driver->shouldReceive('query')->andReturnUsing(fn (string $query) => $query === 'up'
            ? [['metric' => ['job' => 'laravel'], 'value' => [time(), '1']]] : []);
        $driver->shouldReceive('queryRange')->andReturn([]);

        $queues = app(MonitoringService::class)->dashboard()['queues'];
        $this->assertSame(0.0, $queues['processed_per_minute']);
        $this->assertSame(0.0, $queues['failed_per_minute']);

        config()->set('monitoring.components.laravel', false);
        $queues = app(MonitoringService::class)->dashboard()['queues'];
        $this->assertNull($queues['processed_per_minute']);
        $this->assertNull($queues['failed_per_minute']);
    }

    public function test_custom_worker_saves_central_and_tenant_pulse_records_after_each_job(): void
    {
        config()->set('pulse.enabled', true);
        config()->set('pulse.storage.database.connection', 'central');
        (require database_path('migrations/2023_06_07_000001_create_pulse_tables.php'))->up();
        app(Pulse::class)->startRecording()->register([Queues::class => ['enabled' => true]]);

        foreach (['database', 'tenant'] as $queueConnection) {
            if ($queueConnection === 'tenant') {
                tenancy()->initialize('queue-monitor-test');
            }
            Queue::connection($queueConnection)->push(new QueueMonitorNoopJob);
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $this->assertSame(0, Artisan::call('tenants:queue-work', ['--once' => true]));

            // Check storage before Console Kernel termination: the outer worker
            // stays alive in production and cannot rely on a command-end flush.
            $pulse = app(Pulse::class);
            $this->assertFalse($pulse->wantsIngesting());
            $this->assertTrue($pulse->ignore(fn () => DB::connection('central')->table('pulse_aggregates')
                ->where('type', 'processed')->where('key', $queueConnection.':default')->exists()));
        }
    }

    public function test_failed_telegram_deliveries_are_retried_and_recorded_as_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 503)]);
        config()->set('pulse.enabled', true);
        config()->set('pulse.storage.database.connection', 'central');
        (require database_path('migrations/2023_06_07_000001_create_pulse_tables.php'))->up();
        app(Pulse::class)->startRecording()->register([Queues::class => ['enabled' => true]]);

        Queue::connection('database')->push(new SendTelegramMessageJob('test', 'fake-token', 'fake-chat'));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            DB::connection('central')->table('jobs')->update(['available_at' => time()]);
            $this->assertSame(0, Artisan::call('tenants:queue-work', ['--once' => true]));
            $this->assertSame($attempt < 3 ? 1 : 0, DB::connection('central')->table('jobs')->count());
        }

        Http::assertSentCount(3);
        $this->assertSame(1, DB::connection('central')->table('failed_jobs')->count());
        $this->assertStringContainsString('Telegram message delivery failed', DB::connection('central')->table('failed_jobs')->value('exception'));
        $this->assertFalse(DB::connection('central')->table('pulse_aggregates')->where('type', 'processed')->exists());
        $this->assertTrue(DB::connection('central')->table('pulse_aggregates')->where('type', 'failed')->exists());
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_telegram_order_delivery_failure_restores_central_context(): void
    {
        $tenant = DB::build(['driver' => 'sqlite', 'database' => database_path($this->tenantDatabase)]);
        $tenant->getSchemaBuilder()->create('pos_sales', function (Blueprint $table): void {
            $table->id();
        });
        $tenant->getSchemaBuilder()->create('pos_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('pos_sale_id');
        });
        $tenant->table('pos_sales')->insert(['id' => 1]);
        DB::purge($tenant->getName());
        $telegram = $this->mock(TelegramNotificationService::class);
        $telegram->shouldReceive('isEnabled')->once()->andReturn(true);
        $telegram->shouldReceive('notifyPosSale')->once()->andReturn(false);
        $originalStorage = storage_path();
        try {
            (new SendTelegramOrderNotificationJob('pos_sale', 1, 'queue-monitor-test'))->handle($telegram);
            $this->fail('A delivery failure must escape the job handler.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Telegram order notification delivery failed.', $exception->getMessage());
        }
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());
        $this->assertSame($originalStorage, storage_path());
    }

    public function test_disabled_order_notifications_are_skipped_without_querying_sales(): void
    {
        $telegram = $this->mock(TelegramNotificationService::class);
        $telegram->shouldReceive('isEnabled')->once()->andReturn(false);
        $telegram->shouldNotReceive('notifyPosSale');
        (new SendTelegramOrderNotificationJob('pos_sale', 1, 'queue-monitor-test'))->handle($telegram);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenant_context_is_restored_after_a_job_switches_tenants_and_throws(): void
    {
        DB::connection('central')->table('tenants')->insert([
            'id' => 'other-tenant', 'data' => json_encode(['tenancy_db_name' => $this->tenantDatabase]),
        ]);
        tenancy()->initialize('queue-monitor-test');
        $originalStorage = storage_path();
        try {
            TenantJobContext::run('other-tenant', function (): void {
                $this->assertSame('other-tenant', tenant('id'));
                throw new \RuntimeException('Test job failure');
            });
            $this->fail('Job exceptions must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test job failure', $exception->getMessage());
        }
        $this->assertSame('queue-monitor-test', tenant('id'));
        $this->assertSame($originalStorage, storage_path());
        $this->assertSame('tenant', config('queue.default'));
    }

    public function test_a_missing_job_tenant_cannot_fall_back_to_another_tenants_credentials(): void
    {
        tenancy()->initialize('queue-monitor-test');
        $telegram = $this->mock(TelegramNotificationService::class);
        $telegram->shouldNotReceive('sendDirectMessage');
        try {
            (new SendTelegramMessageJob('test', null, null, null, 'deleted-tenant'))->handle($telegram);
            $this->fail('Missing tenants must fail the job.');
        } catch (ModelNotFoundException) {
            $this->assertSame('queue-monitor-test', tenant('id'));
        }
    }

    public function test_stock_jobs_reject_missing_tenants_before_modifying_stock(): void
    {
        $this->expectException(ModelNotFoundException::class);
        (new DeductPosSaleStockJob(1, 'deleted-tenant'))->handle();
    }

    public function test_reserved_tenant_jobs_do_not_prevent_ready_central_jobs_from_running(): void
    {
        $tenant = DB::build(['driver' => 'sqlite', 'database' => database_path($this->tenantDatabase)]);
        $tenant->table('jobs')->insert([...$this->jobRow(), 'reserved_at' => time()]);
        DB::purge($tenant->getName());
        Queue::connection('database')->push(new QueueMonitorNoopJob);
        $this->assertSame(0, Artisan::call('tenants:queue-work', ['--once' => true]));
        $this->assertSame(0, DB::connection('central')->table('jobs')->count());
        $this->assertFalse(tenancy()->initialized);
    }

    private function createQueueTables(Connection $connection): void
    {
        $schema = $connection->getSchemaBuilder();
        $schema->create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->text('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        $schema->create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    private function jobRow(): array
    {
        return ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()];
    }
}

class QueueMonitorNoopJob implements ShouldQueue
{
    public function handle(): void {}
}
