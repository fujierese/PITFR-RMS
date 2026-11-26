<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additional Part 2, Activity Proposal management. Every uploaded proposal is kept
 * as a version (never overwritten); the Supply Office verifies Final copies and sets
 * the submission deadline. This is independent of the reservation status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_proposal_versions')) {
            Schema::create('activity_proposal_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('submission_type', 10); // draft | final
                $table->string('file_name'); // stored name under storage/app/documents/activity_proposal
                $table->string('original_name')->nullable();
                $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at');
                // pending (draft or awaiting verification) | verified | requires_correction | superseded
                $table->string('review_status', 30)->default('pending');
                $table->text('remarks')->nullable();
                $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->boolean('imported')->default(false);
                $table->timestamps();

                // Short explicit name: the generated one (68 characters) exceeds MySQL's 64.
                $table->unique(['facility_request_id', 'version_number'], 'apv_request_version_unique');
            });
        }

        if (! Schema::hasTable('activity_proposal_deadline_changes')) {
            Schema::create('activity_proposal_deadline_changes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
                $table->date('previous_deadline')->nullable();
                $table->date('new_deadline');
                $table->string('kind', 20); // assigned | modified | extended | default
                $table->text('reason')->nullable();
                $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('facility_requests', 'proposal_deadline')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->date('proposal_deadline')->nullable();
            });
        }

        // Existing requests: their current proposal becomes version 1. Its type was never
        // recorded, so it counts as a draft (a Final Copy can then be submitted).
        if (Schema::hasColumn('facility_requests', 'activity_proposal_file')) {
            $now = now();
            DB::table('facility_requests')
                ->whereNotNull('activity_proposal_file')
                ->where('activity_proposal_file', '!=', '')
                ->whereNotIn('id', DB::table('activity_proposal_versions')->select('facility_request_id'))
                ->orderBy('id')
                ->select(['id', 'activity_proposal_file', 'requested_by_id', 'created_at'])
                ->chunk(200, function ($rows) use ($now): void {
                    DB::table('activity_proposal_versions')->insert($rows->map(fn ($row) => [
                        'facility_request_id' => $row->id,
                        'version_number' => 1,
                        'submission_type' => 'draft',
                        'file_name' => $row->activity_proposal_file,
                        'original_name' => null,
                        'uploaded_by_id' => $row->requested_by_id,
                        'submitted_at' => $row->created_at ?? $now,
                        'review_status' => 'pending',
                        'imported' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_proposal_deadline_changes');
        Schema::dropIfExists('activity_proposal_versions');
        if (Schema::hasColumn('facility_requests', 'proposal_deadline')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropColumn('proposal_deadline');
            });
        }
    }
};
