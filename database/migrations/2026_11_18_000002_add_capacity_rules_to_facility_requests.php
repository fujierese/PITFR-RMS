<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision D2 (REQ-01, REQ-02): each venue in a request is a separate booking with
 * its own participant count. A venue below that count can only be used through a
 * capacity exception that the Supply Office approves (reason, approver, time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_requests', 'venue_participants')) {
                $table->json('venue_participants')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'capacity_exceptions')) {
                $table->json('capacity_exceptions')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'capacity_exception_status')) {
                $table->string('capacity_exception_status', 20)->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'capacity_exception_reviewed_by_id')) {
                $table->unsignedBigInteger('capacity_exception_reviewed_by_id')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'capacity_exception_reviewed_at')) {
                $table->timestamp('capacity_exception_reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            foreach ([
                'capacity_exception_reviewed_at', 'capacity_exception_reviewed_by_id',
                'capacity_exception_status', 'capacity_exceptions', 'venue_participants',
            ] as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
