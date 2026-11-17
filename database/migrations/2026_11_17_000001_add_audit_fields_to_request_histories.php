<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue APR-03: each approval-history entry records the role the user acted in
 * and the version of the request it applied to. Additive and nullable, so
 * existing history rows are kept unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('request_histories', 'actor_role')) {
                $table->string('actor_role', 50)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('request_histories', 'request_version')) {
                $table->unsignedInteger('request_version')->nullable()->after('actor_role');
            }
            if (! Schema::hasColumn('request_histories', 'request_fingerprint')) {
                $table->char('request_fingerprint', 64)->nullable()->after('request_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_histories', function (Blueprint $table) {
            foreach (['request_fingerprint', 'request_version', 'actor_role'] as $column) {
                if (Schema::hasColumn('request_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
