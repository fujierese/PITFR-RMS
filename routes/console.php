<?php

use App\Console\Commands\BackfillFacilityRequestRelations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('facility-requests:backfill-relations', function () {
    $this->call(BackfillFacilityRequestRelations::class);
})->purpose('Backfill request venue and equipment data into relational tables');
