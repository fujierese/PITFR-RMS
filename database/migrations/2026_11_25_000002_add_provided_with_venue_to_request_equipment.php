<?php

use App\Services\VenueEquipmentPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up fixes: equipment that comes with a venue ("Provided with venue") is
 * stored on the request as its own rows, flagged, so the details page, custodians
 * and reports see it. Flagged rows are never counted against the equipment
 * inventory. Existing active requests get their rows added once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('request_equipment', 'provided_with_venue')) {
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->boolean('provided_with_venue')->default(false)->index();
            });
        }

        // Backfill pending/approved requests that do not have their provided rows yet.
        $now = now();
        $equipmentIds = DB::table('equipment')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        DB::table('facility_requests')
            ->whereIn('status', ['pending', 'approved', 'needs_reschedule'])
            ->whereNotIn('id', DB::table('request_equipment')->where('provided_with_venue', true)->select('facility_request_id'))
            ->orderBy('id')
            ->select('id')
            ->chunk(200, function ($requests) use ($now, $equipmentIds): void {
                $venues = DB::table('request_venues')->whereIn('facility_request_id', $requests->pluck('id'))->get(['id', 'facility_request_id', 'name']);
                $rows = [];
                foreach ($venues as $venue) {
                    foreach (VenueEquipmentPolicy::providedEquipment((string) $venue->name) as $item => $quantity) {
                        $rows[] = [
                            'facility_request_id' => $venue->facility_request_id,
                            'request_venue_id' => $venue->id,
                            'equipment_id' => $equipmentIds[mb_strtolower($item)] ?? null,
                            'name' => $item,
                            'quantity' => $quantity,
                            'provided_with_venue' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if ($rows !== []) {
                    DB::table('request_equipment')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('request_equipment', 'provided_with_venue')) {
            DB::table('request_equipment')->where('provided_with_venue', true)->delete();
            Schema::table('request_equipment', function (Blueprint $table): void {
                $table->dropColumn('provided_with_venue');
            });
        }
    }
};
