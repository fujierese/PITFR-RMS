<?php

namespace App\Console\Commands;

use App\Services\FacilityRequestRelationBackfillService;
use Illuminate\Console\Command;

class BackfillFacilityRequestRelations extends Command
{
    protected $signature = 'facility-requests:backfill-relations';

    protected $description = 'Backfill legacy facility request venue/equipment JSON data into relational tables';

    public function handle(FacilityRequestRelationBackfillService $service): int
    {
        $summary = $service->run();

        $this->info('Facility request relation backfill summary');
        $this->info("- Total requests scanned: {$summary['total_requests_scanned']}");
        $this->info("- request_venues created: {$summary['request_venues_created']}");
        $this->info("- request_equipment created: {$summary['request_equipment_created']}");
        $this->info("- skipped requests: {$summary['skipped_requests']}");
        $this->info("- unmatched venues: {$summary['unmatched_venues']}");
        $this->info("- unmatched equipment: {$summary['unmatched_equipment']}");

        if (!empty($summary['missing_quantity_requests'])) {
            $this->warn('Requests with missing equipment_quantities: ' . implode(', ', $summary['missing_quantity_requests']));
        }

        if (!empty($summary['missing_venue_names'])) {
            $this->warn('Unmatched venue names: ' . implode(', ', $summary['missing_venue_names']));
        }

        if (!empty($summary['missing_equipment_names'])) {
            $this->warn('Unmatched equipment names: ' . implode(', ', $summary['missing_equipment_names']));
        }

        return self::SUCCESS;
    }
}
