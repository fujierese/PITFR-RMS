<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_equipment') && ! Schema::hasColumn('request_equipment', 'request_venue_id')) {
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->foreignId('request_venue_id')
                    ->nullable()
                    ->after('facility_request_id')
                    ->constrained('request_venues')
                    ->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('equipment')
            && Schema::hasColumn('equipment', 'quantity')
            && Schema::hasColumn('equipment', 'quantity_available')
        ) {
            \Illuminate\Support\Facades\DB::table('equipment')
                ->where('name', 'Sound System')
                ->where('quantity', '<', 3)
                ->update([
                    'quantity_available' => \Illuminate\Support\Facades\DB::raw('CASE WHEN quantity_available + (3 - quantity) > 3 THEN 3 ELSE quantity_available + (3 - quantity) END'),
                    'quantity' => 3,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('request_equipment') && Schema::hasColumn('request_equipment', 'request_venue_id')) {
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('request_venue_id');
            });
        }
    }
};
