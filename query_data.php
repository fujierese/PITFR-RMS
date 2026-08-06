<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$cols = $db->select("SHOW COLUMNS FROM facility_requests");
foreach ($cols as $c) {
    echo $c->Field . "\n";
}
echo "---REQUESTS---\n";
$reqs = $db->select("SELECT id, status, is_priority, is_cancelled, is_needs_revision, is_needs_reschedule, created_at FROM facility_requests ORDER BY id DESC LIMIT 50");
foreach ($reqs as $r) {
    echo sprintf("%s|%s|%s|%s|%s|%s|%s\n", $r->id, $r->status, $r->is_priority, $r->is_cancelled, $r->is_needs_revision, $r->is_needs_reschedule, $r->created_at);
}
?>
