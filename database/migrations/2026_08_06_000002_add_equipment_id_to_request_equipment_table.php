<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_equipment')) {
            return;
        }

        if (!Schema::hasColumn('request_equipment', 'equipment_id')) {
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->foreignId('equipment_id')
                    ->nullable()
                    ->after('facility_request_id')
                    ->constrained('equipment')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_equipment')) {
            return;
        }

        if (Schema::hasColumn('request_equipment', 'equipment_id')) {
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->dropForeign(['equipment_id']);
                $table->dropColumn('equipment_id');
            });
        }
    }

};
