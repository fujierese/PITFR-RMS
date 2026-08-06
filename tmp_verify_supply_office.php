<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\SupplyOfficeController;
use App\Models\FacilityRequest;
use Illuminate\Http\Request;

$request = FacilityRequest::create([
    'control_number' => 'VERIFY-URGENT-001',
    'date_requested' => now()->toDateString(),
    'department' => 'IT',
    'name_of_activity' => 'Verification urgent request',
    'expected_participants' => 20,
    'start_date' => now()->toDateString(),
    'end_date' => now()->toDateString(),
    'start_time' => '09:00',
    'end_time' => '12:00',
    'venue' => ['Conference Hall & Interaction Center (CHIC)'],
    'equipment' => ['Sound System'],
    'equipment_quantities' => ['Sound System' => 1],
    'requested_by_id' => 1,
    'status' => 'pending',
    'venue_status' => 'pending',
    'equipment_status' => 'pending',
    'priority' => 'regular',
    'is_emergency' => true,
]);

$controller = app(SupplyOfficeController::class);
$response = $controller->index(new Request());
$viewData = $response->getData();
$ids = $viewData['allRequests']->pluck('id')->all();
$found = in_array($request->id, $ids, true);
echo $found ? 'FOUND' : 'NOT_FOUND';
echo PHP_EOL;
echo 'count=' . count($ids) . PHP_EOL;
