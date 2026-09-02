<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\FacilityRequest;

$u = User::where('username', 'student1')->first();
if (!$u) {
    echo "User student1 not found. Exiting.\n";
    exit(1);
}

$request = FacilityRequest::create([
    'control_number' => 'QA-' . date('Y') . '-' . rand(1000, 9999),
    'date_requested' => now(),
    'department' => 'Testing',
    'name_of_activity' => 'QA Automation Test Event',
    'expected_participants' => 50,
    'requested_by_id' => $u->id,
    'start_date' => now()->addDays(7)->toDateString(),
    'end_date' => now()->addDays(7)->toDateString(),
    'start_time' => '09:00',
    'end_time' => '12:00',
    'venue' => ['Conference Hall & Interaction Center (CHIC)'],
    'equipment' => ['Wireless Microphones'],
    'equipment_quantities' => [2],
    'status' => 'pending',
    'venue_status' => 'pending',
    'equipment_status' => 'pending',
    'priority' => 'regular',
]);

echo "Created request id: {$request->id} control_number: {$request->control_number}\n"; 
