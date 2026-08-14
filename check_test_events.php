<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

$container = $app->make(\Illuminate\Contracts\Container\Container::class);
$container->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

$events = \App\Models\FacilityRequest::whereIn('name_of_activity', [
    'Same day test',
    'Two-day test', 
    'Three-day test',
    'One-week test',
    'Multi-week test'
])->with('reservationSchedule')->get();

foreach ($events as $event) {
    $schedule = $event->reservationSchedule;
    $start = $schedule?->start_datetime ?? ($event->start_date . ' ' . ($event->start_time ?? '00:00:00'));
    $end = $schedule?->end_datetime ?? (($event->end_date ?? $event->start_date) . ' ' . ($event->end_time ?? $event->start_time ?? '00:00:00'));
    
    echo sprintf(
        "%s: %s to %s\n",
        $event->name_of_activity,
        $start,
        $end
    );
}
