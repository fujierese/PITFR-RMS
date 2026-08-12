<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacilityRequest;

$r = FacilityRequest::find($argv[1] ?? 1);
$s = $r->reservationSchedule()->first();
if (!$s) { echo "no schedule\n"; exit; }

print_r($s->toArray());
