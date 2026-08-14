<?php
require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;

$app = app();
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create a test user
$user = User::factory()->create(['name' => 'September Test User', 'role' => 'requestor']);

// Reservation A: Sep 1 08:00 AM → Sep 4 05:00 PM
$reqA = FacilityRequest::create([
    'control_number' => 'TEST-A-SEP',
    'date_requested' => now()->toDateString(),
    'department' => 'IT',
    'name_of_activity' => 'Reservation A',
    'expected_participants' => 10,
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-04',
    'start_time' => '08:00',
    'end_time' => '17:00',
    'requested_by_id' => $user->id,
    'status' => 'approved',
    'venue_status' => 'approved',
    'equipment_status' => 'approved',
]);
ReservationSchedule::create([
    'facility_request_id' => $reqA->id,
    'start_datetime' => '2026-09-01 08:00:00',
    'end_datetime' => '2026-09-04 17:00:00',
]);

// Reservation B: Sep 1 10:00 AM → Sep 4 03:00 PM
$reqB = FacilityRequest::create([
    'control_number' => 'TEST-B-SEP',
    'date_requested' => now()->toDateString(),
    'department' => 'IT',
    'name_of_activity' => 'Reservation B',
    'expected_participants' => 15,
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-04',
    'start_time' => '10:00',
    'end_time' => '15:00',
    'requested_by_id' => $user->id,
    'status' => 'approved',
    'venue_status' => 'approved',
    'equipment_status' => 'approved',
]);
ReservationSchedule::create([
    'facility_request_id' => $reqB->id,
    'start_datetime' => '2026-09-01 10:00:00',
    'end_datetime' => '2026-09-04 15:00:00',
]);

// Reservation C: Sep 1 09:30 AM → Sep 1 02:15 PM
$reqC = FacilityRequest::create([
    'control_number' => 'TEST-C-SEP',
    'date_requested' => now()->toDateString(),
    'department' => 'IT',
    'name_of_activity' => 'Reservation C',
    'expected_participants' => 5,
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-01',
    'start_time' => '09:30',
    'end_time' => '14:15',
    'requested_by_id' => $user->id,
    'status' => 'approved',
    'venue_status' => 'approved',
    'equipment_status' => 'approved',
]);
ReservationSchedule::create([
    'facility_request_id' => $reqC->id,
    'start_datetime' => '2026-09-01 09:30:00',
    'end_datetime' => '2026-09-01 14:15:00',
]);

echo "✓ Created 3 test reservations for September 1-4, 2026\n";
echo "  A: Sep 1 08:00 AM → Sep 4 05:00 PM\n";
echo "  B: Sep 1 10:00 AM → Sep 4 03:00 PM\n";
echo "  C: Sep 1 09:30 AM → Sep 1 02:15 PM\n";
