<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;

$user = User::firstOrCreate(
    ['username' => 'ui5-verifier'],
    [
        'name' => 'UI5 Verifier',
        'role' => 'requestor',
        'contact_number' => '09170000001',
        'password' => bcrypt('password'),
    ]
);

$scenarios = [
    ['control_number' => 'UI5-SAME', 'name_of_activity' => 'Same day test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-14', 'start_time' => '09:00', 'end_time' => '12:00', 'venue' => ['Gymnasium']],
    ['control_number' => 'UI5-TWO', 'name_of_activity' => 'Two-day test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-15', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => ['Covered Court']],
    ['control_number' => 'UI5-THREE', 'name_of_activity' => 'Three-day test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-16', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => ['Conference Hall & Interaction Center (CHIC)']],
    ['control_number' => 'UI5-WEEK', 'name_of_activity' => 'One-week test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-20', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => ['Oval Grounds']],
    ['control_number' => 'UI5-MULTI', 'name_of_activity' => 'Multi-week test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-28', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => ['Balay Alumni']],
    ['control_number' => 'UI5-BND', 'name_of_activity' => 'Month boundary test', 'start_date' => '2026-08-30', 'end_date' => '2026-09-02', 'start_time' => '09:00', 'end_time' => '17:00', 'venue' => ['AVR']],
    ['control_number' => 'UI5-TIMES', 'name_of_activity' => 'Different times test', 'start_date' => '2026-08-14', 'end_date' => '2026-08-16', 'start_time' => '13:00', 'end_time' => '10:00', 'venue' => ['Volleyball Court']],
];

foreach ($scenarios as $payload) {
    $request = FacilityRequest::create(array_merge([
        'date_requested' => now()->toDateString(),
        'department' => 'IT Department',
        'expected_participants' => 25,
        'requested_by_id' => $user->id,
        'status' => 'approved',
        'venue_status' => 'approved',
        'equipment_status' => 'approved',
        'priority' => 'regular',
        'is_emergency' => false,
        'equipment' => [],
        'equipment_quantities' => [],
    ], $payload));

    ReservationSchedule::updateOrCreate(
        ['facility_request_id' => $request->id],
        [
            'start_datetime' => $payload['start_date'] . ' ' . $payload['start_time'] . ':00',
            'end_datetime' => $payload['end_date'] . ' ' . $payload['end_time'] . ':00',
        ]
    );

    echo $payload['control_number'] . PHP_EOL;
}
