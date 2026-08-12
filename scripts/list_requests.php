<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$rows = DB::table('facility_requests')->select('id','control_number','status','venue_status','equipment_status','requested_by_id')->get();
foreach ($rows as $r) {
    echo "{$r->id}|{$r->control_number}|{$r->status}|{$r->venue_status}|{$r->equipment_status}|{$r->requested_by_id}\n";
}
