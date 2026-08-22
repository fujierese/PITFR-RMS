<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'college_id')) {
                $table->foreignId('college_id')->nullable()->after('department')->constrained('colleges')->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('college_id')->constrained('departments')->nullOnDelete();
            }
        });

        Schema::table('facility_requests', function (Blueprint $table): void {
            if (!Schema::hasColumn('facility_requests', 'purpose')) {
                $table->text('purpose')->nullable()->after('name_of_activity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('facility_requests', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }

            if (Schema::hasColumn('users', 'college_id')) {
                $table->dropForeign(['college_id']);
                $table->dropColumn('college_id');
            }
        });
    }
};
