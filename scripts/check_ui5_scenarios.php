<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;

$requests = FacilityRequest::whereIn('control_number', ['UI5-SAME', 'UI5-TWO', 'UI5-THREE', 'UI5-WEEK', 'UI5-MULTI', 'UI5-BND', 'UI5-TIMES'])->get();

echo "Control Number | Start Date | Start Time | End Date | End Time | Schedule Start | Schedule End\n";
echo str_repeat("-", 120) . "\n";

foreach ($requests as $req) {
    $schedStart = $req->reservationSchedule ? $req->reservationSchedule->start_datetime : 'N/A';
    $schedEnd = $req->reservationSchedule ? $req->reservationSchedule->end_datetime : 'N/A';
    printf("%-15s | %-10s | %-10s | %-8s | %-8s | %-14s | %-14s\n",
        $req->control_number,
        $req->start_date,
        $req->start_time ?? 'NULL',
        $req->end_date,
        $req->end_time ?? 'NULL',
        $schedStart,
        $schedEnd
    );
}
