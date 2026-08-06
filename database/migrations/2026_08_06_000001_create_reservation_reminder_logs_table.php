<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('facility_request_id');
            $table->string('reminder_type');
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->foreign('facility_request_id')->references('id')->on('facility_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reminder_logs');
    }
};
