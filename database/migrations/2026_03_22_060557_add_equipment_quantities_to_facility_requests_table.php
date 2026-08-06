<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            // Stores quantities per equipment item e.g. {"Sound System": 2, "Tables": 10}
            $table->json('equipment_quantities')->nullable()->after('equipment');
        });
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->dropColumn('equipment_quantities');
        });
    }
};