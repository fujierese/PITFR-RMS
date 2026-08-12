<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacilityRequest;

$id = $argv[1] ?? 1;
$r = FacilityRequest::find($id);
if (!$r) { echo "Request {$id} not found.\n"; exit(1); }

$hasSchedule = method_exists($r, 'reservationSchedule') && $r->reservationSchedule()->exists();

echo "request {$id} has_reservation_schedule: " . ($hasSchedule ? 'yes' : 'no') . "\n";

if ($hasSchedule) {
    $s = $r->reservationSchedule()->first();
    echo "schedule id: {$s->id}, start: {$s->start}, end: {$s->end}\n";
}
