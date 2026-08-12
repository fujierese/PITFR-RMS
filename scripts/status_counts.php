<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$rows = DB::table('facility_requests')->select('status', DB::raw('count(*) as c'))->groupBy('status')->get();
foreach ($rows as $r) { echo $r->status . ': ' . $r->c . "\n"; }
