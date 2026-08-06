<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\User;

$fr = FacilityRequest::with(['requestVenues', 'requestEquipment', 'histories'])->find(1);
if (! $fr) {
    echo "FacilityRequest 1 not found\n";
    exit(1);
}

foreach ([6,7] as $uid) {
    $user = User::find($uid);
    if (! $user) {
        echo "User {$uid} not found\n";
        continue;
    }
    echo "User {$uid} {$user->username} role={$user->role}\n";
    $assigned = $fr->getAssignedEquipmentForCustodian($uid);
    echo "  assigned equipment: " . json_encode($assigned) . "\n";
    $history = $fr->histories()->where('user_id', $uid)->where('action', 'custodian_endorsed')->get();
    echo "  endorsement history count: " . $history->count() . "\n";
    foreach ($history as $h) {
        echo "    history id={$h->id} action={$h->action} notes={$h->notes}\n";
    }
}

echo "equipment_custodian_statuses: ";
var_export($fr->equipment_custodian_statuses);
echo "\n";

echo "assigned equipment custodian ids: " . json_encode($fr->getAssignedEquipmentCustodianIds()) . "\n";

echo "requested equipment: " . json_encode($fr->getEquipmentQuantities()) . "\n";
