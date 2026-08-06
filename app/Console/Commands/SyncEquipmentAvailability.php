<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncEquipmentAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'equipment:sync-availability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync equipment availability based on approved requests that haven\'t been returned';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing equipment availability...');

        // Reset all equipment to full availability first
        \App\Models\Equipment::query()->update(['quantity_available' => \DB::raw('quantity')]);
        $this->info('Reset all equipment to full availability');

        // Get all approved requests that haven't been fully returned
        $approvedRequests = \App\Models\FacilityRequest::where('status', 'approved')
            ->where(function($query) {
                $query->where('equipment_returned_status', '!=', 'returned')
                      ->where('equipment_returned_status', '!=', 'overdue');
            })
            ->get();

        $this->info('Found ' . $approvedRequests->count() . ' approved requests with outstanding equipment');

        foreach ($approvedRequests as $request) {
            // Get quantities, defaulting to 1 for each equipment if not specified
            $quantities = $request->equipment_quantities ?? [];
            if (empty($quantities) && !empty($request->equipment)) {
                $quantities = array_fill_keys($request->equipment, 1);
            }

            foreach ($quantities as $itemName => $qty) {
                $equipment = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
                if ($equipment) {
                    $newAvailable = max(0, $equipment->quantity_available - (int) $qty);
                    $equipment->update(['quantity_available' => $newAvailable]);
                    $this->line("Reserved {$qty} x {$itemName} for request {$request->control_number} (available: {$newAvailable})");
                }
            }
        }

        $this->info('Equipment availability sync completed!');
    }
}
