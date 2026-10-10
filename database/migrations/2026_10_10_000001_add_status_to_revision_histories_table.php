<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revision_histories', function (Blueprint $table): void {
            $table->string('status', 20)->default('accepted')->after('revision_reason')->index();
            $table->timestamp('responded_at')->nullable()->after('requestor_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('revision_histories', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'responded_at']);
        });
    }
};
