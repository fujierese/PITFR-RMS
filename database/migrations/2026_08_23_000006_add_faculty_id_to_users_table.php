<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'faculty_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('faculty_id', 50)->nullable()->unique()->after('school_id_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'faculty_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['faculty_id']);
                $table->dropColumn('faculty_id');
            });
        }
    }
};