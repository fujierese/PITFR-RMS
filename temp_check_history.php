<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$hist = DB::table('request_histories')->where('facility_request_id', 4)->get();
if ($hist->isEmpty()) {
    echo "NO HISTORIES FOUND for facility_request_id=4\n";
    exit(0);
}
foreach ($hist as $h) {
    echo "ID={$h->id} action={$h->action} user_id={$h->user_id} notes=" . ($h->notes ?? '') . " created_at={$h->created_at}\n";
}
