<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additional Part 2: requests whose start passes before final approval get their own
 * "expired" status. Existing rows are not changed. SQLite (tests) stores the status
 * as text, so only MySQL needs the ENUM widened.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE facility_requests MODIFY status ENUM('pending','approved','rejected','needs_reschedule','cancelled','completed','expired') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('facility_requests')->where('status', 'expired')->update(['status' => 'cancelled']);
        DB::statement("ALTER TABLE facility_requests MODIFY status ENUM('pending','approved','rejected','needs_reschedule','cancelled','completed') NOT NULL DEFAULT 'pending'");
    }
};
