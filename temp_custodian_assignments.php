<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\FacilityRequest;
use Illuminate\Support\Facades\DB;

$ids = [6,7,8,9];
foreach ($ids as $id) {
    $user = DB::table('users')->where('id', $id)->first();
    echo "\nCustodian id={$id} username=".($user->username ?? 'N/A')."\n";
    $reqs = FacilityRequest::where('status','pending')->get();
    foreach ($reqs as $r) {
        $assigned = $r->getAssignedEquipmentForCustodian($id);
        if (!empty($assigned)) {
            echo "  Request {$r->id} {$r->control_number} => ".json_encode($assigned)."\n";
        }
    }
}
