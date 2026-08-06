<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$equipments = DB::table('equipment')->get();
foreach ($equipments as $e) {
    $cust = DB::table('users')->where('id', $e->custodian_id)->first();
    echo "Equipment ID={$e->id} name={$e->name} custodian_id={$e->custodian_id} custodian_username=".($cust?$cust->username:'NONE')."\n";
}

echo "\nPending requests with equipment:\n";
$reqs = DB::table('facility_requests')->where('status','pending')->get();
foreach ($reqs as $r) {
    $eq = json_decode($r->equipment, true) ?: [];
    echo "Request ID={$r->id} control={$r->control_number} equipment=".json_encode($eq)." equipment_status={$r->equipment_status}\n";
}
