<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$id = $argv[1] ?? 1;
$rows = DB::table('notifications')->where('data', 'like', "%{$id}%")->get();

echo "notifications referencing request {$id}: " . $rows->count() . "\n";
foreach ($rows as $r) {
    echo $r->id . ' | ' . $r->type . ' | ' . substr($r->data,0,200) . "\n";
}
