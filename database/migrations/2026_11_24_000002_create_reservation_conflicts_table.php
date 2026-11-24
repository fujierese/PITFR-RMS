<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional Part 2: an approved urgent reservation that overlaps another reservation
 * opens a conflict for the Supply Office to resolve. Nothing is changed
 * automatically; the record keeps the original details and the decision made.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_conflicts')) {
            return;
        }

        Schema::create('reservation_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('urgent_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->foreignId('affected_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->string('venue_name')->nullable();
            $table->string('status', 20)->default('open'); // open | resolved
            $table->string('resolution', 40)->nullable(); // alternative_venue | rescheduled | declined | no_longer_overlapping
            $table->foreignId('resolved_request_id')->nullable()->constrained('facility_requests')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('explanation')->nullable();
            $table->json('previous_details')->nullable();
            $table->json('new_details')->nullable();
            $table->foreignId('detected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['urgent_request_id', 'affected_request_id']);
            $table->index(['status', 'affected_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_conflicts');
    }
};
