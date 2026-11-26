<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 2 (role-based workflow):
 * - venue_verifications: each venue of a request is confirmed by its own Venue
 *   Custodian (venue => status, user, time, schedule fingerprint).
 * - capacity_exception_decisions: the Venue Custodian's capacity exception decision per
 *   venue (approved / not approved with reason, next step, suggested venue, requestor's
 *   response). The full history is also kept in request_histories and audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('facility_requests', 'venue_verifications')) {
                $table->json('venue_verifications')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'capacity_exception_decisions')) {
                $table->json('capacity_exception_decisions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            foreach (['capacity_exception_decisions', 'venue_verifications'] as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
