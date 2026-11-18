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
Schedule::command(ProcessPendingReviewDeadlines::class)->hourly()->withoutOverlapping();

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
