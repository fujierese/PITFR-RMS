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
        Schema::table('equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment', 'authorized_custodian_ids')) {
                $table->json('authorized_custodian_ids')->nullable()->after('custodian_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            if (Schema::hasColumn('equipment', 'authorized_custodian_ids')) {
                $table->dropColumn('authorized_custodian_ids');
            }
        });
    }
};
