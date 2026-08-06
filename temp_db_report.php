<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\Equipment;
use App\Models\User;

echo "--- USERS ---\n";
foreach (User::all() as $u) {
    echo "$u->id,$u->username,$u->role\n";
}

echo "--- REQUESTS ---\n";
foreach (FacilityRequest::with('histories')->get() as $r) {
    $statuses = is_array($r->equipment_custodian_statuses) ? json_encode($r->equipment_custodian_statuses) : '[]';
    echo "$r->id,$r->control_number,$r->status,$r->venue_status,$r->equipment_status,$statuses\n";
    foreach ($r->histories as $h) {
        $notes = str_replace(["\n", "\r"], [' ', ' '], $h->notes);
        echo "  $h->action,$h->user_id,$notes\n";
    }
}

echo "--- EQUIPMENT ---\n";
foreach (Equipment::all() as $e) {
    echo "$e->id,$e->name,$e->custodian_id\n";
}
