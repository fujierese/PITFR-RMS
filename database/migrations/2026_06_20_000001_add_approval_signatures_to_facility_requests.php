<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalSignaturesToFacilityRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('facility_requests', 'venue_approval_signature')) {
                $table->string('venue_approval_signature')->nullable();
            }
            if (!Schema::hasColumn('facility_requests', 'equipment_approval_signature')) {
                $table->string('equipment_approval_signature')->nullable();
            }
            if (!Schema::hasColumn('facility_requests', 'approval_signature_meta')) {
                $table->json('approval_signature_meta')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->dropColumn(['venue_approval_signature', 'equipment_approval_signature', 'approval_signature_meta']);
        });
    }
}
