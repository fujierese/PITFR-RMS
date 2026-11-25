<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts from the public sign-up had no position, so the request form's
 * "All required fields are completed" item could never check. Give verified
 * requestor accounts the same default the server already used. Outsiders keep
 * none (they use "External Partner" automatically); filled positions are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'position') || ! Schema::hasColumn('users', 'requestor_type')) {
            return;
        }
        foreach (['student' => 'Student Organization', 'faculty' => 'Faculty', 'staff' => 'Staff'] as $type => $position) {
            DB::table('users')
                ->where('requestor_type', $type)
                ->where(fn ($q) => $q->whereNull('position')->orWhere('position', ''))
                ->update(['position' => $position]);
        }
    }

    public function down(): void
    {
        // Positions set here are indistinguishable from chosen ones; nothing to undo.
    }
};
