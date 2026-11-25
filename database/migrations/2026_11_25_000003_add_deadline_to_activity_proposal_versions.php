<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Follow-up fixes: each proposal version records the deadline that applied when it was submitted. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_proposal_versions') && ! Schema::hasColumn('activity_proposal_versions', 'deadline_at_submission')) {
            Schema::table('activity_proposal_versions', function (Blueprint $table): void {
                $table->date('deadline_at_submission')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_proposal_versions', 'deadline_at_submission')) {
            Schema::table('activity_proposal_versions', function (Blueprint $table): void {
                $table->dropColumn('deadline_at_submission');
            });
        }
    }
};
