<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B: outsider reservation inquiries go Outsider → IGP → Venue Custodian → Supply Office
 * → IGP (requirements) → Supply Office (confirmation). The stage is stored on the same
 * facility_requests row, so the internal workflow and its tables are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('facility_requests', 'outsider_stage')) {
                $table->string('outsider_stage', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('facility_requests', 'contact_person')) {
                $table->string('contact_person', 191)->nullable();
                $table->string('contact_number', 50)->nullable();
                $table->string('contact_email', 191)->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'additional_remarks')) {
                $table->text('additional_remarks')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'igp_review_notes')) {
                $table->text('igp_review_notes')->nullable();
                $table->foreignId('igp_forwarded_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('igp_forwarded_at')->nullable();
                $table->text('igp_coordination_notes')->nullable();
                $table->timestamp('igp_coordination_started_at')->nullable();
                $table->timestamp('igp_requirements_reported_at')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'outsider_hold_until')) {
                $table->timestamp('outsider_hold_until')->nullable();
            }
        });

        // Place outsider requests already in progress on the matching stage, so none of
        // them are pulled back to IGP review or left without a status.
        $outsiderIds = DB::table('users')->where('requestor_type', 'outsider')->pluck('id');
        if ($outsiderIds->isEmpty()) {
            return;
        }

        $requests = DB::table('facility_requests')
            ->whereIn('requested_by_id', $outsiderIds)
            ->whereNull('outsider_stage')
            ->get(['id', 'status', 'venue_status', 'supply_office_review_status', 'igp_payment_status']);

        foreach ($requests as $row) {
            $stage = match (true) {
                $row->status === 'approved', $row->status === 'completed' => 'confirmed',
                $row->status === 'rejected' => 'rejected',
                $row->status === 'cancelled' => 'cancelled',
                $row->supply_office_review_status === 'confirmed' => $row->igp_payment_status === 'paid' ? 'igp_coordination' : 'approved',
                $row->venue_status === 'approved' => 'endorsed',
                in_array($row->venue_status, ['rejected', 'needs_reschedule'], true) => 'not_endorsed',
                default => 'custodian_review',
            };

            DB::table('facility_requests')->where('id', $row->id)->update(['outsider_stage' => $stage]);
        }
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('facility_requests', 'igp_forwarded_by_id')) {
                $table->dropConstrainedForeignId('igp_forwarded_by_id');
            }
            foreach (['outsider_stage', 'contact_person', 'contact_number', 'contact_email', 'additional_remarks', 'igp_review_notes', 'igp_forwarded_at', 'igp_coordination_notes', 'igp_coordination_started_at', 'igp_requirements_reported_at', 'outsider_hold_until'] as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
