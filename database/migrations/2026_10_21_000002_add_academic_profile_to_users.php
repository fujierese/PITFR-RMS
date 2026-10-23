<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('academic_program_id')
                ->nullable()
                ->after('department_id')
                ->constrained('academic_programs')
                ->nullOnDelete();
            $table->string('major', 255)->nullable()->after('academic_program_id');
            $table->string('grade_level', 20)->nullable()->after('major');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['academic_program_id']);
            $table->dropColumn(['academic_program_id', 'major', 'grade_level']);
        });
    }
};
