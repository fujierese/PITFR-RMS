<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\User;

$requests = FacilityRequest::with(['requestVenues', 'requestEquipment'])->where('status', 'pending')->get();
foreach ($requests as $req) {
    echo "Request {$req->id} {$req->control_number} status={$req->status} venue_status={$req->venue_status} equipment_status={$req->equipment_status}\n";
    echo "  Requested venues: " . json_encode($req->getVenueNames()) . "\n";
    echo "  Requested equipment: " . json_encode($req->getEquipmentQuantities()) . "\n";
    echo "  Assigned equipment custodian ids: " . json_encode($req->getAssignedEquipmentCustodianIds()) . "\n";
    foreach ($req->getAssignedEquipmentCustodianIds() as $uid) {
        $user = User::find($uid);
        echo "    custodian {$uid} {$user?->username} assigned=" . json_encode($req->getAssignedEquipmentForCustodian($uid)) . "\n";
        $endorsed = $req->histories()->where('user_id', $uid)->where('action', 'custodian_endorsed')->exists();
        echo "      endorsed=" . ($endorsed ? 'yes' : 'no') . "\n";
    }
    echo "  equipment_custodian_statuses: "; var_export($req->equipment_custodian_statuses); echo "\n";
}
