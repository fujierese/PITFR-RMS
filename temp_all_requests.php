<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$reqs = DB::table('facility_requests')->orderBy('id','asc')->get();
if ($reqs->isEmpty()) { echo "No requests\n"; exit; }
foreach ($reqs as $r) {
    $venues = json_decode($r->venue, true) ?: [];
    echo "ID={$r->id} control={$r->control_number} status={$r->status} venue_status={$r->venue_status} equipment_status={$r->equipment_status} venues=".json_encode($venues)."\n";
    foreach ($venues as $vn) {
        $v = DB::table('venues')->where('name', $vn)->first();
        $cust = $v ? DB::table('users')->where('id', $v->custodian_id)->first() : null;
        echo "  -> venue='{$vn}' custodian_id=".($v?$v->custodian_id:'NULL')." custodian_username=".($cust?$cust->username:'NONE')."\n";
    }
}
