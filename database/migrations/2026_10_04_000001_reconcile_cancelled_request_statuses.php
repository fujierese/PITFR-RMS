<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('facility_requests')
            ->whereNotIn('status', ['approved', 'rejected', 'completed'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('request_histories')
                    ->whereColumn('request_histories.facility_request_id', 'facility_requests.id')
                    ->where('request_histories.action', 'cancelled');
            })
            ->update([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
            ]);
    }

    public function down(): void
    {
        // Cancellation history is authoritative; reverting these statuses would recreate the inconsistency.
    }
};
