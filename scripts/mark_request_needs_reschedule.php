<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacilityRequest;

$id = $argv[1] ?? null;
if (!$id) {
    echo "Usage: php mark_request_needs_reschedule.php <request_id>\n";
    exit(1);
}

$request = FacilityRequest::find($id);
if (!$request) {
    echo "Request id {$id} not found.\n";
    exit(1);
}

$request->status = 'needs_reschedule';
$request->venue_status = 'needs_reschedule';
$request->equipment_status = 'needs_reschedule';
$request->notes = 'Marked needs reschedule for QA by assistant.';
$request->save();

echo "Request id {$id} updated to needs_reschedule.\n";
