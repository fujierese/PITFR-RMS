<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\SupplyOfficeController;
use Illuminate\Http\Request;
use App\Models\FacilityRequest;

$request = Request::create('/supply-office/requests/pending', 'GET');
$ctrl = new SupplyOfficeController();
$reflection = new ReflectionClass($ctrl);
$method = $reflection->getMethod('buildRequestListQuery');
$method->setAccessible(true);
$query = $method->invoke($ctrl, $request);
$requests = $query->where('status', 'pending')->get();

foreach ($requests as $r) {
    echo "id={$r->id} control_number={$r->control_number} status={$r->status} venue_status={$r->venue_status} equipment_status={$r->equipment_status} requested_by_id={$r->requested_by_id}\n";
}

echo "total=" . $requests->count() . "\n";

$req2 = FacilityRequest::find(2);
if ($req2) {
    echo "req2 loaded status={$req2->status} venue_status={$req2->venue_status} equipment_status={$req2->equipment_status} requested_by_id={$req2->requested_by_id}\n";
    echo "venue=" . json_encode($req2->venue) . " equipment=" . json_encode($req2->equipment) . " equipment_quantities=" . json_encode($req2->equipment_quantities) . "\n";
}
