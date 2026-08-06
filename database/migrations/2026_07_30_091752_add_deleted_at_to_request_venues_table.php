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
        if (Schema::hasTable('request_venues') && !Schema::hasColumn('request_venues', 'deleted_at')) {
            Schema::table('request_venues', function (Blueprint $table): void {
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('request_venues') && Schema::hasColumn('request_venues', 'deleted_at')) {
            Schema::table('request_venues', function (Blueprint $table): void {
                $table->dropColumn('deleted_at');
            });
        }
    }
};
