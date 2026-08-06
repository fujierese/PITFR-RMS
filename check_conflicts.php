<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = new Illuminate\Http\Request();
$request->merge([
    'venues' => ['Conference Hall & Interaction Center (CHIC)'],
    'start_date' => '2026-08-01',
    'start_time' => '09:00',
    'end_date' => '2026-08-01',
    'end_time' => '10:00',
]);
$controller = app(App\Http\Controllers\CalendarController::class);
$response = $controller->checkConflicts($request);
echo $response->getContent();
