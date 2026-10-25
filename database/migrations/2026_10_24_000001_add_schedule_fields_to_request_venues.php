<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('request_venues')) {
            return;
        }

        Schema::table('request_venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_venues', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (! Schema::hasColumn('request_venues', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (! Schema::hasColumn('request_venues', 'start_time')) {
                $table->string('start_time', 8)->nullable();
            } else {
                $table->string('start_time', 8)->nullable()->change();
            }
            if (! Schema::hasColumn('request_venues', 'end_time')) {
                $table->string('end_time', 8)->nullable();
            } else {
                $table->string('end_time', 8)->nullable()->change();
            }
            if (! Schema::hasColumn('request_venues', 'reservation_duration')) {
                $table->string('reservation_duration', 20)->nullable();
            }
        });

        DB::table('request_venues')
            ->join('facility_requests', 'facility_requests.id', '=', 'request_venues.facility_request_id')
            ->whereNull('request_venues.start_date')
            ->select([
                'request_venues.id',
                'facility_requests.start_date',
                'facility_requests.end_date',
                'facility_requests.start_time',
                'facility_requests.end_time',
                'facility_requests.reservation_duration',
            ])
            ->orderBy('request_venues.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('request_venues')
                        ->where('id', $row->id)
                        ->update([
                            'start_date' => $row->start_date,
                            'end_date' => $row->end_date ?? $row->start_date,
                            'start_time' => $row->start_time,
                            'end_time' => $row->end_time,
                            'reservation_duration' => $row->reservation_duration ?? 'specific_time',
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('request_venues')) {
            return;
        }

        Schema::table('request_venues', function (Blueprint $table): void {
            foreach (['start_date', 'end_date', 'start_time', 'end_time', 'reservation_duration'] as $column) {
                if (Schema::hasColumn('request_venues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
