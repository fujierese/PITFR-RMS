<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$cnt = $db->table('facility_requests')->count();
echo "COUNT|$cnt\n";
$reqs = $db->table('facility_requests')->select('id','control_number','requested_by_id','status','priority','is_emergency','created_at','updated_at')->orderBy('id','desc')->limit(50)->get();
foreach ($reqs as $r) {
    echo implode('|', [$r->id, $r->control_number, $r->requested_by_id, $r->status, $r->priority, $r->is_emergency, $r->created_at, $r->updated_at]) . "\n";
}
$users = $db->table('users')->select('id','username','name','role','requestor_type')->orderBy('id')->get();
foreach ($users as $u) {
    echo implode('|', [$u->id, $u->username, $u->name, $u->role, $u->requestor_type]) . "\n";
}
?>
