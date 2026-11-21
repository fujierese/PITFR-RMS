<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-19: venue rules the server enforces.
 * - venues.open_to_outsiders: outsiders may book the venue (the Gym is not, it is
 *   considered unsafe for outsiders).
 * - venues.requires_activity_type: requests must say which school activity the venue
 *   is for (the Gym: prom, orientation, seminar, pickleball, badminton or other).
 * - request_venues.activity_type / activity_type_detail: what each request chose.
 * Existing venues whose name contains "gym" are closed to outsiders and ask for the
 * activity type; every other venue keeps working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('venues', 'open_to_outsiders')) {
                $table->boolean('open_to_outsiders')->default(true);
            }
            if (! Schema::hasColumn('venues', 'requires_activity_type')) {
                $table->boolean('requires_activity_type')->default(false);
            }
        });

        DB::table('venues')
            ->whereRaw('LOWER(name) LIKE ?', ['%gym%'])
            ->update(['open_to_outsiders' => false, 'requires_activity_type' => true]);

        Schema::table('request_venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_venues', 'activity_type')) {
                $table->string('activity_type', 40)->nullable();
            }
            if (! Schema::hasColumn('request_venues', 'activity_type_detail')) {
                $table->string('activity_type_detail', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_venues', function (Blueprint $table): void {
            foreach (['activity_type', 'activity_type_detail'] as $column) {
                if (Schema::hasColumn('request_venues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('venues', function (Blueprint $table): void {
            foreach (['open_to_outsiders', 'requires_activity_type'] as $column) {
                if (Schema::hasColumn('venues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
