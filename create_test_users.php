<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Equipment;

// Create test users
$student = User::firstOrCreate(
    ['username' => 'student_test'],
    [
        'name' => 'Test Student',
        'password' => bcrypt('password'),
        'role' => 'requestor',
        'requestor_type' => 'student',
    ]
);
echo "Student user: {$student->username} (ID: {$student->id})\n";

$faculty = User::firstOrCreate(
    ['username' => 'faculty_test'],
    [
        'name' => 'Test Faculty',
        'password' => bcrypt('password'),
        'role' => 'faculty',
        'requestor_type' => 'faculty',
    ]
);
echo "Faculty user: {$faculty->username} (ID: {$faculty->id})\n";

$external = User::firstOrCreate(
    ['username' => 'external_test'],
    [
        'name' => 'External Partner',
        'password' => bcrypt('password'),
        'role' => 'requestor',
        'requestor_type' => 'outsider',
    ]
);
echo "External user: {$external->username} (ID: {$external->id})\n";

// Create equipment if not exists
$custodian = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

$equipment_names = ['Sound System', 'Microphones', 'Canopies', 'Industrial Fans', 'Iwata Cooler Fans', 'Tables', 'Monobloc chairs'];
foreach ($equipment_names as $name) {
    Equipment::firstOrCreate(
        ['name' => $name],
        [
            'custodian_id' => $custodian->id,
            'quantity' => 50,
            'quantity_available' => 50,
            'authorized_custodian_ids' => [],
        ]
    );
}
echo "Equipment created.\n";
echo "\n=== Test Credentials ===\n";
echo "Student: student_test / password\n";
echo "Faculty: faculty_test / password\n";
echo "External: external_test / password\n";
