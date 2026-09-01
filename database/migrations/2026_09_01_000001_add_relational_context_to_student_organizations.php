<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_organizations', 'college_id')) {
                $table->foreignId('college_id')->nullable()->after('name')->constrained('colleges')->nullOnDelete();
            }

            if (! Schema::hasColumn('student_organizations', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('college_id')->constrained('departments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('student_organizations', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }

            if (Schema::hasColumn('student_organizations', 'college_id')) {
                $table->dropConstrainedForeignId('college_id');
            }
        });
    }
};
