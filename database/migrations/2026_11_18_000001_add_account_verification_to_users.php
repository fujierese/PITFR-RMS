<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision D4 / REG-01, REG-05: self-registered requestors are verified by an
 * institutional email or a reviewed ID before their claimed category applies.
 *
 * Existing accounts and accounts created by the Supply Office default to
 * "verified", so nothing changes for current users. The uploaded ID image is
 * deleted after review; only the ID type and the decision are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'verification_status')) {
                $table->string('verification_status', 20)->default('verified')->after('requestor_type');
            }
            if (! Schema::hasColumn('users', 'claimed_requestor_type')) {
                $table->string('claimed_requestor_type', 20)->nullable()->after('verification_status');
            }
            if (! Schema::hasColumn('users', 'verification_method')) {
                $table->string('verification_method', 30)->nullable()->after('claimed_requestor_type');
            }
            if (! Schema::hasColumn('users', 'verification_id_type')) {
                $table->string('verification_id_type', 50)->nullable()->after('verification_method');
            }
            if (! Schema::hasColumn('users', 'verification_id_file')) {
                $table->string('verification_id_file')->nullable()->after('verification_id_type');
            }
            if (! Schema::hasColumn('users', 'verification_note')) {
                $table->string('verification_note', 500)->nullable()->after('verification_id_file');
            }
            if (! Schema::hasColumn('users', 'verification_submitted_at')) {
                $table->timestamp('verification_submitted_at')->nullable()->after('verification_note');
            }
            if (! Schema::hasColumn('users', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_submitted_at');
            }
            if (! Schema::hasColumn('users', 'verified_by_id')) {
                $table->unsignedBigInteger('verified_by_id')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'verified_by_id', 'verified_at', 'verification_submitted_at', 'verification_note',
                'verification_id_file', 'verification_id_type', 'verification_method',
                'claimed_requestor_type', 'verification_status',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
