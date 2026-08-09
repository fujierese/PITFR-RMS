<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_reminder_logs', function (Blueprint $table): void {
            $table->unique(
                ['facility_request_id', 'reminder_type', 'scheduled_for'],
                'reservation_reminder_logs_unique_reminder'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reservation_reminder_logs', function (Blueprint $table): void {
            $table->dropUnique('reservation_reminder_logs_unique_reminder');
        });
    }
};