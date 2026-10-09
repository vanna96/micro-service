<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantJobContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Pulse;
use Throwable;

class TenantsQueueWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:queue-work
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--tries=3 : The number of times to attempt a job before logging it failed}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--once : Only process the next available job across tenants and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the queue worker across all isolated tenant databases and central queue';

    /**
     * Timestamp when the worker started.
     */
    protected int $workerStartTime;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->workerStartTime = time();
        $sleep = max((int) $this->option('sleep'), 1);
        $tries = (int) $this->option('tries');
        $timeout = (int) $this->option('timeout');
        $runOnce = (bool) $this->option('once');

        $this->info("Starting tenant queue worker [sleep: {$sleep}s, tries: {$tries}, timeout: {$timeout}s]...");

        while (true) {
            // Check if queue:restart was signaled
            if ($this->shouldRestart()) {
                $this->info('Queue worker restart signal detected. Exiting gracefully.');

                return 0;
            }

            $jobProcessed = false;

            // 1. Process pending jobs in each tenant's isolated database
            try {
                $tenants = Tenant::all();
            } catch (Throwable $e) {
                $this->error('Failed to load tenants: '.$e->getMessage());
                $tenants = collect();
            }

            foreach ($tenants as $tenant) {
                try {
                    $hasTenantJob = false;

                    TenantJobContext::run((string) $tenant->id, function () use (&$hasTenantJob, $tries, $timeout) {
                        if ($this->hasAvailableJob('tenant', 'tenant')) {
                            $hasTenantJob = true;

                            // Process one job inside this tenant database context
                            $this->forgetQueueConnections();
                            $this->processQueueJob('tenant', $tries, $timeout);
                        }
                    });

                    if ($hasTenantJob) {
                        $jobProcessed = true;
                        if ($runOnce) {
                            return 0;
                        }
                    }
                } catch (Throwable $e) {
                    Log::error("[TenantsQueueWork] Error processing queue for tenant {$tenant->id}: ".$e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            }

            // 2. Process central queue jobs if any exist
            try {
                $centralConnection = config('queue.connections.database.connection') ?: config('tenancy.database.central_connection', 'central');
                if ($this->hasAvailableJob($centralConnection, 'database')) {
                    // Ensure tenancy is not initialized for central job processing
                    if (function_exists('tenancy') && tenancy()->initialized) {
                        tenancy()->end();
                    }

                    $this->forgetQueueConnections();
                    $this->processQueueJob('database', $tries, $timeout);

                    $jobProcessed = true;
                    if ($runOnce) {
                        return 0;
                    }
                }
            } catch (Throwable $e) {
                Log::error('[TenantsQueueWork] Error processing central queue: '.$e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            if ($runOnce) {
                return 0;
            }

            // If no jobs were found across all tenants and central, sleep
            if (! $jobProcessed) {
                sleep($sleep);
            }
        }

        return 0;
    }

    protected function processQueueJob(string $connection, int $tries, int $timeout): void
    {
        try {
            Artisan::call('queue:work', [
                'connection' => $connection,
                '--once' => true,
                '--tries' => $tries,
                '--timeout' => $timeout,
            ]);
        } finally {
            // --once skips Pulse's Looping/WorkerStopping hooks. Save each job's
            // buffered telemetry before this long-running command continues.
            if (config('pulse.enabled') && class_exists(Pulse::class)) {
                try {
                    app(Pulse::class)->ingest();
                } catch (Throwable) {
                    // Optional telemetry must never interrupt queue processing.
                }
            }
        }
    }

    protected function hasAvailableJob(string $databaseConnection, string $queueConnection): bool
    {
        $table = config("queue.connections.{$queueConnection}.table", 'jobs');
        if (! Schema::connection($databaseConnection)->hasTable($table)) {
            return false;
        }

        $now = time();
        $expired = $now - (int) config("queue.connections.{$queueConnection}.retry_after", 90);

        return DB::connection($databaseConnection)->table($table)
            ->where('queue', config("queue.connections.{$queueConnection}.queue", 'default'))
            ->where(function ($query) use ($now, $expired): void {
                $query->where(function ($query) use ($now): void {
                    $query->whereNull('reserved_at')->where('available_at', '<=', $now);
                })->orWhere('reserved_at', '<=', $expired);
            })->exists();
    }

    /**
     * Clear cached QueueManager connections so each tenant resolves a fresh DatabaseQueue with its active connection.
     */
    protected function forgetQueueConnections(): void
    {
        if (app()->resolved('queue')) {
            try {
                $queue = app('queue');
                $ref = new \ReflectionProperty($queue, 'connections');
                $ref->setAccessible(true);
                $ref->setValue($queue, []);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Determine if the queue worker should restart.
     */
    protected function shouldRestart(): bool
    {
        try {
            $lastRestart = Cache::get('illuminate:queue:restart');

            return $lastRestart && $this->workerStartTime < $lastRestart;
        } catch (Throwable) {
            return false;
        }
    }
}
