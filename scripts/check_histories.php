<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$id = $argv[1] ?? 1;
$h1 = DB::table('request_status_history')->where('facility_request_id', $id)->get();
$h2 = DB::table('request_histories')->where('facility_request_id', $id)->get();

echo "request_status_history: " . $h1->count() . "\n";
foreach ($h1 as $r) { print_r($r); }

echo "request_histories: " . $h2->count() . "\n";
foreach ($h2 as $r) { print_r($r); }
