<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['facility_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_change_requests');
    }
};