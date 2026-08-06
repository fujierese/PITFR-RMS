<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\FacilityRequest;

$r = FacilityRequest::where('name_of_activity', 'QA Auto Test Event')->orderBy('id','desc')->first();
if (!$r) {
    echo "NOT FOUND\n";
    exit(0);
}
echo "ID: {$r->id}\n";
echo "Control: {$r->control_number}\n";
echo "Status: {$r->status}\n";
echo "Venue: ".json_encode($r->venue)."\n";
echo "Equipment: ".json_encode($r->equipment)."\n";
echo "Equipment quantities: ".json_encode($r->equipment_quantities)."\n";
echo "Requested by: {$r->requested_by_id}\n";
foreach ($r->histories as $h) {
    echo "HISTORY: {$h->action} by {$h->user_id} at {$h->created_at} notes: {$h->notes}\n";
}
