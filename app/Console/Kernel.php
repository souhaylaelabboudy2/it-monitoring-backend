<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run alert cleanup daily at 2:00 AM
        $schedule->command('alerts:cleanup')
                 ->dailyAt('02:00')
                 ->onOneServer()
                 ->withoutOverlapping()
                 ->sendOutputTo(storage_path('logs/alert_cleanup.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
