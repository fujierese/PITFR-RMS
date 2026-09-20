<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'organization_acronym')) {
                $table->string('organization_acronym', 50)->nullable()->after('office_or_organization');
            }

            if (! Schema::hasColumn('users', 'organization_type')) {
                $table->string('organization_type', 100)->nullable()->after('organization_acronym');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'organization_type')) {
                $table->dropColumn('organization_type');
            }

            if (Schema::hasColumn('users', 'organization_acronym')) {
                $table->dropColumn('organization_acronym');
            }
        });
    }
};
