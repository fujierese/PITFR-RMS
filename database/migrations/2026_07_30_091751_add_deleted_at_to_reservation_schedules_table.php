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
        if (!Schema::hasTable('reservation_schedules')) {
            return;
        }

        if (!Schema::hasColumn('reservation_schedules', 'deleted_at')) {
            Schema::table('reservation_schedules', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('reservation_schedules', 'deleted_at')) {
            Schema::table('reservation_schedules', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
