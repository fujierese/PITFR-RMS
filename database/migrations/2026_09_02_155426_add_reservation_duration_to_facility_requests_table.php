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
            if (!Schema::hasColumn('facility_requests', 'reservation_duration')) {
                $table->enum('reservation_duration', ['specific_time', 'whole_day', 'whole-day', 'whole day'])
                    ->default('specific_time')
                    ->after('end_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'reservation_duration')) {
                $table->dropColumn('reservation_duration');
            }
        });
    }
};
