<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facility_requests', function (Blueprint $table) {
            $table->id();
            $table->string('control_number', 30)->unique();
            $table->date('date_requested');
            $table->string('department', 100);
            $table->string('name_of_activity', 200);
            $table->string('expected_participants', 20);
            $table->date('requesting_date');
            $table->time('time');
            $table->json('venue');
            $table->json('equipment');
            $table->string('other_venue', 200)->nullable();
            $table->string('other_equipment', 200)->nullable();
            $table->string('requested_by', 100);
            $table->string('requested_by_position', 100);
            $table->foreignId('requested_by_id')->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('venue_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('equipment_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('venue_approved_by', 100)->nullable();
            $table->string('equipment_approved_by', 100)->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->datetime('approved_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('venue_notes')->nullable();
            $table->text('equipment_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_requests');
    }
};
