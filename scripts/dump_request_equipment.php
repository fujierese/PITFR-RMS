<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$rows = DB::table('request_equipment')->where('facility_request_id', 1)->get();
echo "request_equipment rows: " . $rows->count() . "\n";
foreach ($rows as $r) { print_r((array)$r); }
