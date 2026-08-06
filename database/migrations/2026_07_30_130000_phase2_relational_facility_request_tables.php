<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('request_equipment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('request_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->string('status');
            $table->string('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_status_history');
        Schema::dropIfExists('request_equipment');
        Schema::dropIfExists('request_venues');
    }
};
