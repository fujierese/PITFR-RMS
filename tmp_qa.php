<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();
$db = $app->make('db');
foreach ($db->select('select id, username, name, role from users order by id') as $u) {
    echo "{$u->id}|{$u->username}|{$u->name}|{$u->role}\n";
}
echo "---\n";
foreach ($db->select('SHOW COLUMNS FROM facility_requests') as $c) {
    echo "{$c->Field}|{$c->Type}\n";
}
