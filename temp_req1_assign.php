<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\FacilityRequest;

$r = FacilityRequest::find(1);
echo "Request 1 equipment=" . json_encode($r->equipment) . "\n";
echo "Request 1 equipment_quantities=" . json_encode($r->equipment_quantities) . "\n";
foreach ([6,7,8,9] as $id) {
    $assigned = $r->getAssignedEquipmentForCustodian($id);
    echo "Assigned for {$id}: " . json_encode($assigned) . "\n";
}
