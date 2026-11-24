<?php

use App\Console\Commands\BackfillFacilityRequestRelations;
use App\Console\Commands\ProcessPendingReviewDeadlines;
use App\Console\Commands\SendReservationReminderNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('facility-requests:backfill-relations', function () {
    $this->call(BackfillFacilityRequestRelations::class);
})->purpose('Backfill request venue and equipment data into relational tables');

Schedule::command(SendReservationReminderNotifications::class)->everyMinute();
// Every 5 minutes so a request expires close to its start time (reminders stay once per stage).
Schedule::command(ProcessPendingReviewDeadlines::class)->everyFiveMinutes()->withoutOverlapping();

// REQ-05: delete kept uploads from failed submissions after 24 hours.
Schedule::call(function (): void {
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    $cutoff = now()->subDay()->getTimestamp();
    foreach ($disk->allFiles('staged-uploads') as $path) {
        if ($disk->lastModified($path) < $cutoff) {
            $disk->delete($path);
        }
    }
})->hourly()->name('cleanup-staged-uploads')->withoutOverlapping();

// NTF-02: deliver queued notification emails without a long-running daemon.
// The heartbeat tells the app a worker is active (otherwise mail is sent immediately).
// The worker keeps polling for ~55 seconds, so queued emails go out within seconds.
Schedule::call(fn () => \App\Support\MailQueue::recordHeartbeat())
    ->everyMinute()
    ->name('queue-worker-heartbeat');
Schedule::command('queue:work', [
    '--queue' => 'default',
    '--sleep' => 3,
    '--tries' => 3,
    '--max-time' => 55,
])->everyMinute()->withoutOverlapping(2)->runInBackground()->name('queue-worker');
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();

// REQ-18: report-only check that old request columns agree with venue, item and
// schedule rows; logs a warning when they do not. Repair with requests:reconcile --fix.
Schedule::command('requests:reconcile')->dailyAt('02:30')->withoutOverlapping()->name('request-data-check');
