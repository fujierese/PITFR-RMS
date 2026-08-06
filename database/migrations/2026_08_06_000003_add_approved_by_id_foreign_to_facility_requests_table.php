<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facility_requests')) {
            return;
        }

        if (!Schema::hasColumn('facility_requests', 'approved_by_id')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->foreignId('approved_by_id')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('facility_requests')) {
            return;
        }

        if (Schema::hasColumn('facility_requests', 'approved_by_id')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropForeign(['approved_by_id']);
                $table->dropColumn('approved_by_id');
            });
        }
    }

};
