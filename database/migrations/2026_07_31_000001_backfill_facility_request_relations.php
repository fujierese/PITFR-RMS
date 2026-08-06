<?php

use App\Services\FacilityRequestRelationBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(FacilityRequestRelationBackfillService::class);
        $summary = $service->run();

        Log::info('Phase 4 relation backfill migration completed', $summary);
    }

    public function down(): void
    {
        // No-op: retain migrated data and keep the legacy columns intact.
    }
};
