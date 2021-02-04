<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ScheduleWorker extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'schedule:worker';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Doesn’t do anything, only used to schedule a queue:worker';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command('queue:work --tries=3 --stop-when-empty')
                 ->withoutOverlapping()
                 ->everyMinute();
    }
}
