<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            // 1) Авто-закриття standby по wait_until: reverse або expire
            $now = now();

            $expired = \App\Models\StandbyRequest::whereIn('status',['pending','authorized'])
                ->whereNotNull('wait_until')
                ->where('wait_until','<=',$now)
                ->get();

            foreach ($expired as $sr) {
                if ($sr->status === 'authorized') {
                    try {
                        app(\App\Services\WayForPayStandby::class)
                            ->void($sr->order_reference, (float)$sr->amount, $sr->currency_code);
                        $sr->status = 'voided';
                        $sr->voided_at = now();
                    } catch (\Throwable $e) {
                        $sr->status = 'expired';
                    }
                } else {
                    $sr->status = 'expired';
                }
                $sr->save();
            }

            // 2) Періодичний матчинг (раптом звільнились місця)
            $pairs = \App\Models\StandbyRequest::where('status','authorized')
                ->select('trip_id','date')->distinct()->get();

            $matcher = app(\App\Services\StandbyMatcher::class);
            foreach ($pairs as $row) {
                $matcher->tryMatch($row->trip_id, \Carbon\Carbon::parse($row->date)->toDateString());
            }
        })->everyMinute();

        // 1) Чистимо прострочені hold-и
        $schedule->call(function () {
            DB::table('bookings')
                ->where('status', 'hold')
                ->whereNotNull('held_until')
                ->where('held_until', '<', now())
                ->update(['status' => 'expired']);
        })->everyMinute();

        // 2) Нагадування пасажирам/водіям (твоя консольна команда)
        $schedule->command('reminders:trips')
            ->hourly()
            ->withoutOverlapping();
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
