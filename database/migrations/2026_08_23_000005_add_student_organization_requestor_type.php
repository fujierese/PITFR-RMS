<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY requestor_type ENUM('student','faculty','outsider','student_organization') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET requestor_type = 'outsider' WHERE requestor_type = 'student_organization'");
            DB::statement("ALTER TABLE users MODIFY requestor_type ENUM('student','faculty','outsider') NULL");
        }
    }
};