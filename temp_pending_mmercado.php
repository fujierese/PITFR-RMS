<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$venue = DB::table('venues')->where('name', 'Balay Alumni')->first();
$custId = $venue ? $venue->custodian_id : null;
if (!$custId) { echo "No custodian for Balay Alumni\n"; exit; }
$reqs = DB::table('facility_requests')->where('status','pending')->get();
$found = false;
foreach ($reqs as $r) {
    $venues = json_decode($r->venue, true) ?: [];
    if (in_array('Balay Alumni', $venues)) {
        echo "Request ID={$r->id} control={$r->control_number} venueMatch=true venue_status={$r->venue_status} equipment_status={$r->equipment_status}\n";
        $found = true;
    }
}
if(!$found) echo "No pending requests for Balay Alumni\n";
