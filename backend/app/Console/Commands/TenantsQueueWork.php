<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
                $this->error('Failed to load tenants: ' . $e->getMessage());
                $tenants = collect();
            }

            foreach ($tenants as $tenant) {
                try {
                    $hasTenantJob = false;

                    $tenant->run(function () use (&$hasTenantJob, $tries, $timeout) {
                        if (! Schema::hasTable('jobs')) {
                            return;
                        }

                        $jobCount = DB::table('jobs')->where('available_at', '<=', time())->count();
                        if ($jobCount > 0) {
                            $hasTenantJob = true;

                            // Process one job inside this tenant database context
                            $this->forgetQueueConnections();
                            Artisan::call('queue:work', [
                                'connection' => 'tenant',
                                '--once' => true,
                                '--tries' => $tries,
                                '--timeout' => $timeout,
                            ]);
                        }
                    });

                    if ($hasTenantJob) {
                        $jobProcessed = true;
                        if ($runOnce) {
                            return 0;
                        }
                    }
                } catch (Throwable $e) {
                    Log::error("[TenantsQueueWork] Error processing queue for tenant {$tenant->id}: " . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            }

            // 2. Process central queue jobs if any exist
            try {
                $centralConnection = config('tenancy.database.central_connection', 'central');
                if (Schema::connection($centralConnection)->hasTable('jobs')) {
                    $centralJobs = DB::connection($centralConnection)->table('jobs')->where('available_at', '<=', time())->count();
                    if ($centralJobs > 0) {
                        // Ensure tenancy is not initialized for central job processing
                        if (function_exists('tenancy') && tenancy()->initialized) {
                            tenancy()->end();
                        }

                        $this->forgetQueueConnections();
                        Artisan::call('queue:work', [
                            'connection' => 'database',
                            '--once' => true,
                            '--tries' => $tries,
                            '--timeout' => $timeout,
                        ]);

                        $jobProcessed = true;
                        if ($runOnce) {
                            return 0;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::error('[TenantsQueueWork] Error processing central queue: ' . $e->getMessage(), [
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
