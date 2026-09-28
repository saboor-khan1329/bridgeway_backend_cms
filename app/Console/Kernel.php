<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        if ((bool) config('queue.scheduled_worker.enabled', true)) {
            $schedule->command(sprintf(
                'queue:work --stop-when-empty --tries=%d --sleep=%d --timeout=%d --queue=%s',
                (int) config('queue.scheduled_worker.tries', 1),
                (int) config('queue.scheduled_worker.sleep', 1),
                (int) config('queue.scheduled_worker.timeout', 0),
                (string) config('queue.scheduled_worker.queues', 'default,mail-outbox')
            ))
                ->everyMinute()
                ->withoutOverlapping();
        }

        if ((bool) config('mail_outbox.schedule.enabled', true)) {
            $schedule->command('mail-dispatches:process')
                ->everyMinute()
                ->withoutOverlapping();
        }

        // Regenerate sitemap.xml from DB — a true rolling 48 hours, not a
        // calendar approximation. This runs every 6 hours, but the command
        // itself tracks its own last-run timestamp and no-ops until 48 real
        // hours have passed (see GenerateSitemap::dueToRun()) — the frequent
        // check just keeps the actual gap tight (worst case ~6h drift)
        // instead of a cron expression that can silently become 24h/72h
        // around month boundaries. The file is written to public/sitemap.xml
        // where the web server serves it.
        $schedule->command('sitemap:generate')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
