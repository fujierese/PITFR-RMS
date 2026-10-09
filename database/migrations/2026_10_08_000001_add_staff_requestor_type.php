<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'requestor_type')) {
            return;
        }

        DB::statement("UPDATE users SET requestor_type = 'student' WHERE requestor_type = 'student_organization'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY requestor_type ENUM('student','faculty','staff','outsider') NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'requestor_type')) {
            return;
        }

        DB::statement("UPDATE users SET requestor_type = 'student_organization' WHERE requestor_type = 'student' AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'requestor_type')");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY requestor_type ENUM('student','faculty','outsider','student_organization') NULL");
        }
    }
};
