<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('college_id')->constrained('colleges')->cascadeOnDelete();
            $table->string('name', 255);
            $table->json('majors')->nullable();
            $table->timestamps();
            $table->unique(['college_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
    }
};
