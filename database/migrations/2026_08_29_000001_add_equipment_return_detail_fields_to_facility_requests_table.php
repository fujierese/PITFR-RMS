<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_requests', 'equipment_return_damaged_quantity')) {
                $table->unsignedInteger('equipment_return_damaged_quantity')->default(0)->after('equipment_return_notes');
            }

            if (! Schema::hasColumn('facility_requests', 'equipment_return_missing_quantity')) {
                $table->unsignedInteger('equipment_return_missing_quantity')->default(0)->after('equipment_return_damaged_quantity');
            }

            if (! Schema::hasColumn('facility_requests', 'equipment_return_damage_remarks')) {
                $table->text('equipment_return_damage_remarks')->nullable()->after('equipment_return_missing_quantity');
            }

            if (! Schema::hasColumn('facility_requests', 'equipment_return_missing_remarks')) {
                $table->text('equipment_return_missing_remarks')->nullable()->after('equipment_return_damage_remarks');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE facility_requests MODIFY equipment_returned_status ENUM('pending','partial','returned','fulfilled','overdue') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'equipment_return_damaged_quantity')) {
                $table->dropColumn('equipment_return_damaged_quantity');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_return_missing_quantity')) {
                $table->dropColumn('equipment_return_missing_quantity');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_return_damage_remarks')) {
                $table->dropColumn('equipment_return_damage_remarks');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_return_missing_remarks')) {
                $table->dropColumn('equipment_return_missing_remarks');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE facility_requests MODIFY equipment_returned_status ENUM('pending','partial','returned','overdue') NOT NULL DEFAULT 'pending'");
        }
    }
};
