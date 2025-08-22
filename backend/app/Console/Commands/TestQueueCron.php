<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestQueueCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan test:queue-cron
     */
    protected $signature = 'test:queue-cron {--delay=0 : Delay in seconds before simulating queue job completion}';

    /**
     * The console command description.
     */
    protected $description = 'Test if queue worker and cron scheduling are working';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $delay = (int) $this->option('delay');

        // Log to file so you can check later
        $message = "[TEST] Command run at: " . now()->toDateTimeString();
        // Log::channel('daily')->info($message);
        info($message);
        info("✅ Command executed: {$message}");

        // Simulate pushing a queue job
        dispatch(function () use ($delay) {
            if ($delay > 0) {
                sleep($delay);
            }
            info("[TEST] Queue job processed at: " . now()->toDateTimeString());
        })->onQueue('default');

        $this->info("📤 Test job dispatched to queue (delay: {$delay}s).");
    }
}