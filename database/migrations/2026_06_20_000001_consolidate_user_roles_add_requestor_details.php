<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new requestor details first so we can preserve requestor_type values
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

        // Preserve current requestor classification before re-mapping the role enum
        DB::statement("UPDATE users SET requestor_type = CASE WHEN role = 'student' THEN 'student' WHEN role = 'faculty' THEN 'faculty' ELSE requestor_type END");

        // Add the new consolidated role values while keeping legacy values available for migration
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('student','faculty','custodian-venue','custodian-equipment','admin','requestor','custodian') NOT NULL DEFAULT 'requestor'");
        }

        // Consolidate legacy role values into three main roles
        DB::statement("UPDATE users SET role = CASE WHEN role IN ('student','faculty') THEN 'requestor' WHEN role IN ('custodian-venue','custodian-equipment') THEN 'custodian' WHEN role = 'supply_office' THEN 'admin' ELSE role END");

        // Strip the legacy enum options and leave only the consolidated role set
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('requestor','custodian','admin') NOT NULL DEFAULT 'requestor'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('requestor','custodian','admin') NOT NULL DEFAULT 'requestor'");
        }
        DB::statement("UPDATE users SET role = CASE WHEN role = 'requestor' THEN COALESCE(requestor_type, 'student') WHEN role = 'custodian' THEN 'custodian-venue' WHEN role = 'admin' THEN 'admin' ELSE role END");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('student','faculty','custodian-venue','custodian-equipment','admin') NOT NULL DEFAULT 'student'");
        }

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
