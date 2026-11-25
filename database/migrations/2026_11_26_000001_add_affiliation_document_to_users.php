<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity and Affiliation Verification: PIT members upload a second document (COR or
 * proof of current affiliation) with its academic period / issue date. The file is
 * deleted after the Supply Office decision; the type and period are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'verification_status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'verification_affiliation_type')) {
                $table->string('verification_affiliation_type', 50)->nullable();
            }
            if (! Schema::hasColumn('users', 'verification_affiliation_file')) {
                $table->string('verification_affiliation_file')->nullable();
            }
            if (! Schema::hasColumn('users', 'verification_document_period')) {
                $table->string('verification_document_period', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['verification_document_period', 'verification_affiliation_file', 'verification_affiliation_type'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
