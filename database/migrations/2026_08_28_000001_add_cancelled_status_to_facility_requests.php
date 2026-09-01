<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE facility_requests MODIFY status ENUM('pending','approved','rejected','needs_reschedule','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE facility_requests MODIFY venue_status ENUM('pending','approved','rejected','needs_reschedule','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE facility_requests MODIFY equipment_status ENUM('pending','approved','rejected','needs_reschedule','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('facility_requests')->whereIn('status', ['cancelled'])->update(['status' => 'rejected']);
        DB::table('facility_requests')->whereIn('venue_status', ['cancelled'])->update(['venue_status' => 'rejected']);
        DB::table('facility_requests')->whereIn('equipment_status', ['cancelled'])->update(['equipment_status' => 'rejected']);

        DB::statement("ALTER TABLE facility_requests MODIFY status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE facility_requests MODIFY venue_status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE facility_requests MODIFY equipment_status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
    }
};
