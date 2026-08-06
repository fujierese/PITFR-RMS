<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Holiday;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Carbon\Carbon;

Venue::query()->where('name', 'Conference Hall & Interaction Center (CHIC)')->delete();
Holiday::query()->where('holiday_date', '2026-07-30')->delete();

Venue::create(['name' => 'Conference Hall & Interaction Center (CHIC)', 'capacity' => 150, 'custodian_id' => 1]);
Holiday::create(['holiday_date' => '2026-07-30', 'name' => 'National Day', 'type' => 'public']);

$service = new AvailabilityService();
$result = $service->checkVenueAvailability(
    'Conference Hall & Interaction Center (CHIC)',
    Carbon::parse('2026-07-30 09:00'),
    Carbon::parse('2026-07-30 10:00')
);

echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
