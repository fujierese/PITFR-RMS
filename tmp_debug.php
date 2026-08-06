<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

// create a minimal sqlite db if needed

$custodian = User::create([
    'username' => 'custodian-relational-debug',
    'password' => 'secret',
    'name' => 'Custodian Relational Debug',
    'role' => 'custodian',
]);

Venue::create([
    'name' => 'Conference Hall & Interaction Center (CHIC)',
    'capacity' => 150,
    'custodian_id' => $custodian->id,
]);

$requestor = User::create([
    'username' => 'requestor-relational-debug',
    'password' => 'secret',
    'name' => 'Requestor Relational Debug',
    'role' => 'requestor',
]);

$request = FacilityRequest::create([
    'control_number' => 'FER-2026-001-debug',
    'date_requested' => '2026-08-10',
    'department' => 'ICT',
    'name_of_activity' => 'Test Activity',
    'expected_participants' => 50,
    'requested_by_id' => $requestor->id,
    'start_date' => '2026-08-10',
    'end_date' => '2026-08-10',
    'start_time' => '09:00',
    'end_time' => '10:00',
    'venue' => ['Conference Hall & Interaction Center (CHIC)'],
    'equipment' => [],
    'equipment_quantities' => [],
    'status' => 'approved',
    'venue_status' => 'approved',
    'equipment_status' => 'approved',
]);

$service = new AvailabilityService();
$result = $service->checkVenueAvailability(
    'Conference Hall & Interaction Center (CHIC)',
    now()->parse('2026-08-10 09:30'),
    now()->parse('2026-08-10 10:30')
);

var_dump($result);
var_dump($request->getVenueNames());
var_dump($request->getRequestedStartDateTime()->toDateTimeString());
var_dump($request->getRequestedEndDateTime()->toDateTimeString());
var_dump($request->overlapsTimeRange(now()->parse('2026-08-10 09:30'), now()->parse('2026-08-10 10:30')));
