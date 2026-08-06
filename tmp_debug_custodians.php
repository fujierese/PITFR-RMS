<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$req = DB::table('facility_requests')->where('id', 1)->first();
if (! $req) {
    echo "Request 1 not found\n";
    exit(1);
}

echo "Request 1: status={$req->status} venue_status={$req->venue_status} equipment_status={$req->equipment_status} requested_by_id={$req->requested_by_id}\n";
$equip = DB::table('request_equipment')->where('facility_request_id', 1)->get();
foreach ($equip as $row) {
    echo json_encode($row) . "\n";
}
$reqVenues = DB::table('request_venues')->where('facility_request_id', 1)->pluck('name');
echo "Venues: " . json_encode($reqVenues->all()) . "\n";
$users = DB::table('users')->whereIn('id', [3, 6, 7, 8, 9])->get();
foreach ($users as $u) {
    echo json_encode($u) . "\n";
    $venues = DB::table('venues')->where('custodian_id', $u->id)->pluck('name');
    echo "  venues=" . json_encode($venues->all()) . "\n";
    $equipment = DB::table('equipment')->where('custodian_id', $u->id)->pluck('name');
    echo "  equipment=" . json_encode($equipment->all()) . "\n";
}
