<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('facility_requests', 'organization_name')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->string('organization_name', 191)->nullable()->after('department');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facility_requests', 'organization_name')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropColumn('organization_name');
            });
        }
    }
};