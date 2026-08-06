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
            if (!Schema::hasColumn('facility_requests', 'priority')) {
                $table->enum('priority', ['regular', 'institutional'])->default('regular')->after('equipment_returned_items');
            }
            if (!Schema::hasColumn('facility_requests', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false)->after('priority');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('facility_requests', 'is_emergency')) {
                $table->dropColumn('is_emergency');
            }
        });
    }
};
