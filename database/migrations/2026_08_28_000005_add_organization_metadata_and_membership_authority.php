<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'acronym' => ['string', 50],
            'organization_type' => ['string', 100],
            'adviser' => ['string', 191],
        ] as $column => [$type, $length]) {
            if (! Schema::hasColumn('student_organizations', $column)) {
                Schema::table('student_organizations', function (Blueprint $table) use ($column, $type, $length): void {
                    $table->{$type}($column, $length)->nullable();
                });
            }
        }

        if (! Schema::hasColumn('student_organization_members', 'membership_role')) {
            Schema::table('student_organization_members', function (Blueprint $table): void {
                $table->string('membership_role', 100)->nullable()->after('student_organization_id');
                $table->boolean('can_submit_requests')->default(false)->after('membership_role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_organization_members', 'membership_role')) {
            Schema::table('student_organization_members', function (Blueprint $table): void {
                $table->dropColumn(['membership_role', 'can_submit_requests']);
            });
        }

        foreach (['adviser', 'organization_type', 'acronym'] as $column) {
            if (Schema::hasColumn('student_organizations', $column)) {
                Schema::table('student_organizations', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};