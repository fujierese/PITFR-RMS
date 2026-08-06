<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->enum('equipment_returned_status', ['pending', 'partial', 'returned', 'overdue'])->default('pending')->after('equipment_notes');
            $table->string('equipment_returned_by', 100)->nullable()->after('equipment_returned_status');
            $table->datetime('equipment_returned_date')->nullable()->after('equipment_returned_by');
            $table->text('equipment_return_notes')->nullable()->after('equipment_returned_date');
            $table->json('equipment_returned_items')->nullable()->after('equipment_return_notes'); // Track which items are returned
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'equipment_returned_status')) {
                $table->dropColumn('equipment_returned_status');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_returned_by')) {
                $table->dropColumn('equipment_returned_by');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_returned_date')) {
                $table->dropColumn('equipment_returned_date');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_return_notes')) {
                $table->dropColumn('equipment_return_notes');
            }
            if (Schema::hasColumn('facility_requests', 'equipment_returned_items')) {
                $table->dropColumn('equipment_returned_items');
            }
        });
    }
};
