<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            // Remove the old proposal_file if it exists (migration 2026_05_03 adds it)
            // We'll keep it for backward compatibility and add new specific fields
            
            // Activity Proposal (for students and faculty)
            $table->string('activity_proposal_file')->nullable()->after('proposal_file');
            
            // IGP Receipt (for external/organization)
            $table->string('igp_receipt_file')->nullable()->after('activity_proposal_file');
            
            // E-Signature (required for all requestors)
            $table->string('e_signature_file')->nullable()->after('igp_receipt_file');
            
            // Metadata for file uploads
            $table->json('document_metadata')->nullable()->after('e_signature_file');

            // Preserved private file snapshots for requestor and approval signatures
            if (!Schema::hasColumn('facility_requests', 'venue_approval_signature_file')) {
                $table->string('venue_approval_signature_file')->nullable()->after('e_signature_file');
            }
            if (!Schema::hasColumn('facility_requests', 'equipment_approval_signature_file')) {
                $table->string('equipment_approval_signature_file')->nullable()->after('venue_approval_signature_file');
            }
            if (!Schema::hasColumn('facility_requests', 'final_approval_signature_file')) {
                $table->string('final_approval_signature_file')->nullable()->after('equipment_approval_signature_file');
            }
            if (!Schema::hasColumn('facility_requests', 'final_approval_signature')) {
                $table->string('final_approval_signature')->nullable()->after('final_approval_signature_file');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->dropColumn([
                'activity_proposal_file',
                'igp_receipt_file',
                'e_signature_file',
                'document_metadata',
                'venue_approval_signature_file',
                'equipment_approval_signature_file',
                'final_approval_signature_file',
                'final_approval_signature',
            ]);
        });
    }
};
