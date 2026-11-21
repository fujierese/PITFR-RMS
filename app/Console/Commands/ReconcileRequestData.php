<?php

namespace App\Console\Commands;

use App\Models\FacilityRequest;
use App\Models\SystemLog;
use App\Support\RequestDataReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * REQ-18: checks that every request's old venue, equipment and date columns agree
 * with its venue, item and schedule rows (the source of truth). By default it only
 * reports; with --fix it repairs. The daily run reports only and logs a warning
 * when something disagrees.
 */
class ReconcileRequestData extends Command
{
    protected $signature = 'requests:reconcile
        {--fix : Repair what is found (build missing rows, rewrite out-of-date copies)}
        {--id=* : Check only these request IDs}';

    protected $description = 'Check that old request columns agree with venue, item and schedule rows, and optionally repair them';

    public function handle(RequestDataReconciler $reconciler): int
    {
        $fix = (bool) $this->option('fix');
        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        $found = [];
        $fixed = 0;

        FacilityRequest::query()
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->with(['requestVenues.venue', 'requestEquipment.equipment', 'reservationSchedule'])
            ->chunkById(100, function ($requests) use ($reconciler, $fix, &$found, &$fixed): void {
                foreach ($requests as $request) {
                    $issues = $reconciler->issues($request);
                    if ($issues === []) {
                        continue;
                    }
                    $label = $request->control_number ?: ('#' . $request->id);
                    $found[$label] = $issues;
                    $this->line("<comment>{$label}</comment>");
                    foreach ($issues as $issue) {
                        $this->line("  - {$issue}");
                    }
                    if ($fix) {
                        $reconciler->fix($request->fresh());
                        $remaining = $reconciler->issues($request->fresh());
                        if ($remaining === []) {
                            $fixed++;
                            $this->line('  <info>Fixed.</info>');
                        } else {
                            $this->line('  <error>Still needs attention:</error> ' . implode(' ', $remaining));
                        }
                    }
                }
            });

        if ($found === []) {
            $this->info('All requests agree with their venue, item and schedule rows.');

            return self::SUCCESS;
        }

        $summary = count($found) . ' request(s) had old columns that disagree with their venue, item or schedule rows'
            . ($fix ? "; {$fixed} fixed." : '. Run "php artisan requests:reconcile --fix" to repair them.');
        $this->warn($summary);

        if (! $fix) {
            Log::warning('REQ-18 request data check: ' . $summary, ['requests' => array_slice($found, 0, 50, true)]);
            try {
                SystemLog::record('request_data_mismatch', $summary, null, null, null, null, ['requests' => array_slice(array_keys($found), 0, 200)]);
            } catch (\Throwable) {
                // The warning above is already logged.
            }
        }

        return self::SUCCESS;
    }
}
