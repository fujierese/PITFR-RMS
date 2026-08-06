<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\User;

foreach ([1,2,5,6,7] as $id) {
    $req = FacilityRequest::with(['requestEquipment'])->find($id);
    if (! $req) {
        echo "Request {$id} not found\n";
        continue;
    }
    echo "Request {$id} {$req->control_number}\n";
    echo "  equipment quantities: " . json_encode($req->getEquipmentQuantities()) . "\n";
    echo "  requestEquipment relation loaded: " . ($req->relationLoaded('requestEquipment') ? 'yes' : 'no') . "\n";
    echo "  requestEquipment rows: \n";
    foreach ($req->requestEquipment as $row) {
        echo "    id={$row->id} name={$row->name} qty={$row->quantity}\n";
    }
    echo "  assigned equipment custodian ids: " . json_encode($req->getAssignedEquipmentCustodianIds()) . "\n";
    foreach ([6,7] as $uid) {
        $user = User::find($uid);
        echo "  custodian {$uid} ({$user?->username}) assigned: " . json_encode($req->getAssignedEquipmentForCustodian($uid)) . "\n";
    }
    echo "--\n";
}
