<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\RequestorController;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$custodian = User::factory()->create(['role' => 'custodian', 'requestor_type' => 'faculty']);
$user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'username' => 'urgent-debug-user']);
Venue::create(['name' => 'Conference Hall & Interaction Center (CHIC)', 'custodian_id' => $custodian->id]);
Equipment::factory()->create(['name' => 'Sound System', 'quantity' => 5, 'quantity_available' => 5, 'custodian_id' => $custodian->id]);

$existing = FacilityRequest::create([
    'control_number' => 'TEST-EXIST-001',
    'date_requested' => now()->toDateString(),
    'department' => 'IT Department',
    'name_of_activity' => 'Existing Seminar',
    'expected_participants' => 50,
    'start_date' => '2026-08-01',
    'end_date' => '2026-08-01',
    'start_time' => '09:00',
    'end_time' => '12:00',
    'venue' => ['Conference Hall & Interaction Center (CHIC)'],
    'equipment' => ['Sound System'],
    'equipment_quantities' => ['Sound System' => 1],
    'requested_by_id' => $user->id,
    'status' => 'approved',
    'venue_status' => 'approved',
    'equipment_status' => 'approved',
    'priority' => 'regular',
    'is_emergency' => false,
]);
$existing->syncRelationalItems();

Auth::login($user);

$request = new Request();
$request->merge([
    'department' => 'IT Department',
    'name_of_activity' => 'Urgent Debug Request',
    'expected_participants' => 50,
    'start_date' => '2026-08-01',
    'end_date' => '2026-08-01',
    'start_time' => '09:00',
    'end_time' => '12:00',
    'venue' => 'Conference Hall & Interaction Center (CHIC)',
    'equipment' => ['Sound System'],
    'equipment_quantities' => ['Sound System' => 1],
    'emergency_justification' => 'Need immediate review',
    'is_emergency' => true,
]);

$controller = app(RequestorController::class);
$response = $controller->store($request);
echo get_class($response) . PHP_EOL;
if (method_exists($response, 'getTargetUrl')) {
    echo 'target=' . $response->getTargetUrl() . PHP_EOL;
}
if (method_exists($response, 'getSession')) {
    $errors = $response->getSession()->get('errors');
    echo 'errors=' . ($errors ? $errors->first() : 'none') . PHP_EOL;
}
