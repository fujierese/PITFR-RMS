<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'staff_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('staff_id', 50)->nullable()->unique()->after('faculty_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'staff_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['staff_id']);
                $table->dropColumn('staff_id');
            });
        }
    }
};
