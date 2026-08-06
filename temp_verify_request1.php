<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$r = DB::table('facility_requests')->where('id', 1)->first();
echo "REQ1 equipment_custodian_statuses=" . json_encode($r?->equipment_custodian_statuses) . "\n";
$h = DB::table('request_histories')->where('facility_request_id', 1)->get();
foreach ($h as $row) {
    echo "H: id={$row->id} action={$row->action} user_id={$row->user_id} notes=" . ($row->notes ?? '') . " created_at={$row->created_at}\n";
}
