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
        $tables = [
            'facility_requests' => ['deleted_at'],
            'reservation_schedules' => ['deleted_at'],
            'request_equipment' => ['deleted_at'],
            'request_venues' => ['deleted_at'],
            'request_status_history' => ['deleted_at'],
        ];

        foreach ($tables as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($column): void {
                        $tableBlueprint->timestamp($column)->nullable();
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'request_status_history',
            'request_venues',
            'request_equipment',
            'reservation_schedules',
            'facility_requests',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $tableBlueprint): void {
                    $tableBlueprint->dropColumn('deleted_at');
                });
            }
        }
    }
};
