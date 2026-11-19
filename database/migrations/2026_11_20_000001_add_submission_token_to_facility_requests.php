<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-14:
 * - Each request form carries a one-time token. A second submit of the same form
 *   (double click, refresh, Back and resubmit, network retry) finds the request the
 *   first one created instead of making a duplicate; the unique index settles two
 *   submits that arrive at the same moment.
 * - Makes sure control numbers are unique in the database. The original table had
 *   this index, but an older SQLite-only table rebuild dropped it; it is added back
 *   wherever it is missing. If duplicate numbers already exist, the index is skipped
 *   and a warning is logged rather than failing the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('facility_requests', 'submission_token')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->string('submission_token', 64)->nullable()->unique();
            });
        }

        if ($this->hasUniqueControlNumberIndex()) {
            return;
        }

        $duplicates = DB::table('facility_requests')
            ->select('control_number')
            ->groupBy('control_number')
            ->havingRaw('COUNT(*) > 1')
            ->limit(20)
            ->pluck('control_number');
        if ($duplicates->isNotEmpty()) {
            Log::warning('REQ-14: control numbers are not unique, so the unique index was not added. Duplicates: ' . $duplicates->implode(', '));

            return;
        }

        Schema::table('facility_requests', function (Blueprint $table): void {
            $table->unique('control_number', 'facility_requests_control_number_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('facility_requests', 'submission_token')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropUnique(['submission_token']);
                $table->dropColumn('submission_token');
            });
        }
        // The control number index is left in place: the table was created with it.
    }

    private function hasUniqueControlNumberIndex(): bool
    {
        foreach (Schema::getIndexes('facility_requests') as $index) {
            if (($index['unique'] ?? false) && array_values($index['columns'] ?? []) === ['control_number']) {
                return true;
            }
        }

        return false;
    }
};
