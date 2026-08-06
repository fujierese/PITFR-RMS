<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->timestamps();

            $table->index('facility_request_id');
            $table->index('start_datetime');
            $table->index('end_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_schedules');
    }
};
