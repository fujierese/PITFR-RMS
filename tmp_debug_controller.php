<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

$user = User::find(7);
if (! $user) {
    echo "User 7 not found\n";
    exit(1);
}

echo "user7 role={$user->role}\n";
echo "isCustodian=" . ($user->isCustodian() ? 'yes' : 'no') . "\n";
echo "isCustodianEquipment=" . ($user->isCustodianEquipment() ? 'yes' : 'no') . "\n";

$req = FacilityRequest::with(['requestVenues','requestEquipment','reservationSchedule'])->find(7);
if (! $req) {
    echo "Request 7 not found\n";
    exit(1);
}

echo "request7 equipment quantities: " . json_encode($req->getEquipmentQuantities()) . "\n";
$assigned = $req->getAssignedEquipmentForCustodian(7);
echo "assigned to 7: " . json_encode($assigned) . "\n";

$assignedVenueNames = $req->getVenueNames();

$isAssignedVenueCustodian = $user->isCustodianVenue() && !empty($assignedVenueNames) && $user->venues()->pluck('name')->intersect($assignedVenueNames)->isNotEmpty();
$assignedEquipment = $assigned;
$isAssignedEquipmentCustodian = $user->isCustodianEquipment() && !empty($assignedEquipment);

echo "isAssignedVenueCustodian={$isAssignedVenueCustodian}\n";
echo "isAssignedEquipmentCustodian={$isAssignedEquipmentCustodian}\n";

$currentCustodianEquipment = $user->isCustodian() ? $req->getAssignedEquipmentForCustodian((int) $user->id) : [];
echo "currentCustodianEquipment: " . json_encode($currentCustodianEquipment) . "\n";
