<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacilityRequest;

$id = $argv[1] ?? 1;
$r = FacilityRequest::find($id);
if (!$r) {
    echo "Request {$id} not found.\n";
    exit(1);
}

$r->venue_status = 'approved';
$r->equipment_status = 'approved';
$r->save();

echo "Request {$id} endorsed: venue_status={$r->venue_status}, equipment_status={$r->equipment_status}\n";
