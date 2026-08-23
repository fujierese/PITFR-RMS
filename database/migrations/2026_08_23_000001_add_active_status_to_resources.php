<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('capacity');
        });

        Schema::table('equipment', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('quantity_available');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('equipment', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
