<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (!Schema::hasColumn('facility_requests', 'requested_priority')) {
                $table->enum('requested_priority', ['regular', 'institutional'])
                    ->nullable()
                    ->after('priority');
            }
            if (!Schema::hasColumn('facility_requests', 'requested_is_emergency')) {
                $table->boolean('requested_is_emergency')
                    ->default(false)
                    ->after('requested_priority');
            }
            if (!Schema::hasColumn('facility_requests', 'emergency_justification')) {
                $table->text('emergency_justification')
                    ->nullable()
                    ->after('requested_is_emergency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            $columns = [];
            foreach (['requested_priority', 'requested_is_emergency', 'emergency_justification'] as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
