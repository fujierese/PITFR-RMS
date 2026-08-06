<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->call('migrate:fresh', ['--seed' => false]);

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;

$custodian = User::create([
    'username' => 'custodian-relational',
    'password' => 'secret',
    'name' => 'Custodian Relational',
    'role' => 'custodian',
]);

Venue::create([
    'name' => 'Conference Hall & Interaction Center (CHIC)',
    'capacity' => 150,
    'custodian_id' => $custodian->id,
]);

$requestor = User::create([
    'username' => 'requestor-relational',
    'password' => 'secret',
    'name' => 'Requestor Relational',
    'role' => 'requestor',
]);

$request = FacilityRequest::create([
    'control_number' => 'FER-2026-001',
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

echo "request id: {$request->id}\n";
echo "has schedule: " . ($request->reservationSchedule()->exists() ? 'yes' : 'no') . "\n";
echo "venue names: "; var_dump($request->getVenueNames());
echo "requested start: " . $request->getRequestedStartDateTime()->toDateTimeString() . "\n";
echo "requested end: " . $request->getRequestedEndDateTime()->toDateTimeString() . "\n";
$start = Carbon::parse('2026-08-10 09:30');
$end = Carbon::parse('2026-08-10 10:30');
echo "overlaps: " . ($request->overlapsTimeRange($start, $end) ? 'yes' : 'no') . "\n";
