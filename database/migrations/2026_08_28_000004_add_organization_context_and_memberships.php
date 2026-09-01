<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_organizations')) {
            Schema::create('student_organizations', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 191)->unique();
                $table->string('category', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_organization_members')) {
            Schema::create('student_organization_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
                $table->foreignId('student_organization_id')->constrained('student_organizations')->cascadeOnUpdate()->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'student_organization_id'], 'org_member_user_org_unique');
            });
        }

        if (! Schema::hasColumn('facility_requests', 'request_context')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->string('request_context', 50)->nullable()->after('organization_name');
            });
        }

        if (! Schema::hasColumn('facility_requests', 'student_organization_id')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->foreignId('student_organization_id')->nullable()->after('request_context')->constrained('student_organizations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facility_requests', 'student_organization_id')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropForeign(['student_organization_id']);
                $table->dropColumn('student_organization_id');
            });
        }
        if (Schema::hasColumn('facility_requests', 'request_context')) {
            Schema::table('facility_requests', function (Blueprint $table): void {
                $table->dropColumn('request_context');
            });
        }
        Schema::dropIfExists('student_organization_members');
        Schema::dropIfExists('student_organizations');
    }
};