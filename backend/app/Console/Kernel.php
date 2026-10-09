<?php

namespace App\Console;

use App\Console\Commands\TestQueueCron;
use App\Monitoring\Services\MetricsRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected $commands = [
        TestQueueCron::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function (): void {
            app(MetricsRepository::class)->recordSchedulerHeartbeat();
        })->name('monitoring:scheduler-heartbeat')->everyMinute()->withoutOverlapping();

        // $schedule->command('inspire')->hourly();
        $schedule->command('test:queue-cron')->hourly(); /* ->everyMinute(); */
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
