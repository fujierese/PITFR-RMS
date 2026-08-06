<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$venues = DB::table('venues')->get();
if ($venues->isEmpty()) { echo "No venues\n"; exit; }
foreach ($venues as $v) {
    $cust = DB::table('users')->where('id', $v->custodian_id)->first();
    $custUsername = $cust ? ($cust->username ?? $cust->name) : 'NONE';
    echo "Venue ID={$v->id} name={$v->name} custodian_id={$v->custodian_id} custodian={$custUsername}\n";
}

echo "\nPending facility requests and assigned venue custodians:\n";
$reqs = DB::table('facility_requests')->where('status','pending')->get();
foreach ($reqs as $r) {
    $venueNames = json_decode($r->venue, true);
    if (!$venueNames) $venueNames = [];
    echo "Request ID={$r->id} control={$r->control_number} venues=".json_encode($venueNames)."\n";
    foreach ($venueNames as $vn) {
        $v = DB::table('venues')->where('name', $vn)->first();
        $cust = $v ? DB::table('users')->where('id', $v->custodian_id)->first() : null;
        echo "  -> venue='{$vn}' custodian_id=".($v?$v->custodian_id:'NULL')." custodian_username=".($cust?$cust->username:'NONE')."\n";
    }
}
