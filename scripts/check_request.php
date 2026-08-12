<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacilityRequest;
use App\Models\Equipment;

$id = $argv[1] ?? 1;
$r = FacilityRequest::find($id);
if (!$r) {
    echo "Request {$id} not found.\n";
    exit(1);
}

echo "Request {$id} control_number: {$r->control_number}\n";
echo "status: {$r->status}\n";
echo "venue_status: {$r->venue_status}\n";
echo "equipment_status: {$r->equipment_status}\n";

$equipment = $r->equipment ?? [];
$quantities = $r->equipment_quantities ?? [];
foreach ($equipment as $i => $name) {
    $qty = $quantities[$i] ?? 1;
    $eq = Equipment::where('name', $name)->first();
    if ($eq) {
        echo "Equipment '{$name}' qty_requested={$qty} available={$eq->quantity_available} total={$eq->quantity}\n";
    } else {
        echo "Equipment '{$name}' not found in inventory\n";
    }
}

// Check audit logs table if exists
if (Schema::hasTable('audit_logs')) {
    $logs = DB::table('audit_logs')->where('facility_request_id', $id)->get();
    echo "audit_logs: " . $logs->count() . " entries\n";
}
