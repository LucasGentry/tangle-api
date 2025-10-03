<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * These cron jobs are run in the Artisan command line when the cron is triggered.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('collaborations:check-deadlines')->hourly();
        $schedule->command('notifications:retry')->hourly();
        
        // Send reminders daily at 9 AM
        $schedule->command('reminders:send')->dailyAt('09:00');
        
        // Send specific reminder types at different times
        $schedule->command('reminders:send --type=day_3')->dailyAt('10:00');
        $schedule->command('reminders:send --type=day_7')->dailyAt('11:00');
        $schedule->command('reminders:send --type=day_14')->dailyAt('12:00');
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