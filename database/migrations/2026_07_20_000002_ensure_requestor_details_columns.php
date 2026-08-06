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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'requestor_type')) {
                $table->enum('requestor_type', ['student', 'faculty', 'outsider'])->nullable()->after('role');
            }

            if (!Schema::hasColumn('users', 'school_id_number')) {
                $table->string('school_id_number', 50)->nullable()->after('requestor_type');
            }

            if (!Schema::hasColumn('users', 'office_or_organization')) {
                $table->string('office_or_organization', 191)->nullable()->after('school_id_number');
            }

            if (!Schema::hasColumn('users', 'contact_number')) {
                $table->string('contact_number', 50)->nullable()->after('office_or_organization');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'contact_number')) {
                $table->dropColumn('contact_number');
            }
            if (Schema::hasColumn('users', 'office_or_organization')) {
                $table->dropColumn('office_or_organization');
            }
            if (Schema::hasColumn('users', 'school_id_number')) {
                $table->dropColumn('school_id_number');
            }
            if (Schema::hasColumn('users', 'requestor_type')) {
                $table->dropColumn('requestor_type');
            }
        });
    }
};
