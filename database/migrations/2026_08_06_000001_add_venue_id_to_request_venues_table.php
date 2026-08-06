<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_venues')) {
            return;
        }

        if (!Schema::hasColumn('request_venues', 'venue_id')) {
            Schema::table('request_venues', function (Blueprint $table): void {
                $table->foreignId('venue_id')
                    ->nullable()
                    ->after('facility_request_id')
                    ->constrained('venues')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_venues')) {
            return;
        }

        if (Schema::hasColumn('request_venues', 'venue_id')) {
            Schema::table('request_venues', function (Blueprint $table): void {
                $table->dropForeign(['venue_id']);
                $table->dropColumn('venue_id');
            });
        }
    }

};
