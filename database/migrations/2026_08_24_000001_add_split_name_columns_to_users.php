<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'surname')) {
                $table->string('surname', 100)->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('surname');
            }

            if (!Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }

            if (!Schema::hasColumn('users', 'suffix')) {
                $table->string('suffix', 50)->nullable()->after('middle_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['suffix', 'middle_name', 'first_name', 'surname'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
