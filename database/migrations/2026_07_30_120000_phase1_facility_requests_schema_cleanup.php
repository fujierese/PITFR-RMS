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
            if (Schema::hasColumn('facility_requests', 'requesting_date') && !Schema::hasColumn('facility_requests', 'start_date')) {
                $table->renameColumn('requesting_date', 'start_date');
            }

            if (Schema::hasColumn('facility_requests', 'requesting_end_date') && !Schema::hasColumn('facility_requests', 'end_date')) {
                $table->renameColumn('requesting_end_date', 'end_date');
            }

            if (Schema::hasColumn('facility_requests', 'time') && !Schema::hasColumn('facility_requests', 'start_time')) {
                $table->renameColumn('time', 'start_time');
            }
        });

        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'venue_approved_by')) {
                $table->dropColumn('venue_approved_by');
            }

            if (Schema::hasColumn('facility_requests', 'equipment_approved_by')) {
                $table->dropColumn('equipment_approved_by');
            }

            if (Schema::hasColumn('facility_requests', 'requested_by')) {
                $table->dropColumn('requested_by');
            }

            if (Schema::hasColumn('facility_requests', 'requested_by_position')) {
                $table->dropColumn('requested_by_position');
            }

            if (Schema::hasColumn('facility_requests', 'other_equipment')) {
                $table->dropColumn('other_equipment');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE facility_requests MODIFY expected_participants INT UNSIGNED NOT NULL');
        }

        Schema::table('facility_requests', function (Blueprint $table) {
            $table->index('status');
            $table->index('venue_status');
            $table->index('equipment_status');
            $table->index('requested_by_id');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('approved_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['venue_status']);
            $table->dropIndex(['equipment_status']);
            $table->dropIndex(['requested_by_id']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['approved_date']);
        });

        Schema::table('facility_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('facility_requests', 'requesting_date') && Schema::hasColumn('facility_requests', 'start_date')) {
                $table->renameColumn('start_date', 'requesting_date');
            }

            if (!Schema::hasColumn('facility_requests', 'requesting_end_date') && Schema::hasColumn('facility_requests', 'end_date')) {
                $table->renameColumn('end_date', 'requesting_end_date');
            }

            if (!Schema::hasColumn('facility_requests', 'time') && Schema::hasColumn('facility_requests', 'start_time')) {
                $table->renameColumn('start_time', 'time');
            }

            if (!Schema::hasColumn('facility_requests', 'venue_approved_by')) {
                $table->string('venue_approved_by', 100)->nullable();
            }

            if (!Schema::hasColumn('facility_requests', 'equipment_approved_by')) {
                $table->string('equipment_approved_by', 100)->nullable();
            }

            if (!Schema::hasColumn('facility_requests', 'requested_by')) {
                $table->string('requested_by', 100)->nullable();
            }

            if (!Schema::hasColumn('facility_requests', 'requested_by_position')) {
                $table->string('requested_by_position', 100)->nullable();
            }

            if (!Schema::hasColumn('facility_requests', 'other_equipment')) {
                $table->string('other_equipment', 200)->nullable();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE facility_requests MODIFY expected_participants VARCHAR(20) NOT NULL');
        }
    }
};
