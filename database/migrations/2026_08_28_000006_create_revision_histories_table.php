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
        Schema::create('revision_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->foreignId('revised_by_id')->constrained('users')->restrictOnDelete();

            // Old values (before revision)
            $table->date('old_start_date')->nullable();
            $table->date('old_end_date')->nullable();
            $table->string('old_start_time', 20)->nullable();
            $table->string('old_end_time', 20)->nullable();
            $table->json('old_venue')->nullable(); // Array of venue names
            $table->json('old_equipment')->nullable(); // Array of equipment
            $table->json('old_equipment_quantities')->nullable(); // { "equipment_name": qty, ... }

            // New values (after revision)
            $table->date('new_start_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->string('new_start_time', 20)->nullable();
            $table->string('new_end_time', 20)->nullable();
            $table->json('new_venue')->nullable();
            $table->json('new_equipment')->nullable();
            $table->json('new_equipment_quantities')->nullable();

            // Revision metadata
            $table->text('revision_reason')->nullable(); // Admin reason for revision (required)
            $table->boolean('conflict_detected')->default(false);
            $table->text('conflict_details')->nullable(); // Description of conflicts if any
            $table->boolean('override_conflict')->default(false); // Did admin override conflicts?
            $table->text('override_reason')->nullable(); // Why admin overrode conflicts

            // Notification tracking
            $table->timestamp('requestor_notified_at')->nullable();
            $table->timestamp('custodian_notified_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('facility_request_id');
            $table->index('revised_by_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_histories');
    }
};
