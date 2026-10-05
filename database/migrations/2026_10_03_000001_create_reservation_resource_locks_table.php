<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_resource_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 20);
            $table->char('resource_hash', 64);
            $table->timestamps();
            $table->unique(['resource_type', 'resource_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_resource_locks');
    }
};
