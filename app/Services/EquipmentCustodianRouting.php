<?php

namespace App\Services;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\EquipmentReservationNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Follow-up fixes: every equipment item on a reservation, requested or provided with
 * the venue, reaches its own custodian. One notice per custodian per event, listing
 * only their items; items without a custodian go to the Supply Office (and are
 * logged). This only informs: the review and approval flow is unchanged.
 */
class EquipmentCustodianRouting
{
    /** @return list<array{name: string, quantity: int, venue: string, provided: bool}> */
    public function items(FacilityRequest $request): array
    {
        $items = [];
        foreach ($request->getVenueEquipmentAllocations() as $venue => $venueItems) {
            foreach ((array) $venueItems as $name => $quantity) {
                if ((int) $quantity > 0 && ! $request->isVenueIncludedEquipment((string) $name)) {
                    $items[] = ['name' => (string) $name, 'quantity' => (int) $quantity, 'venue' => (string) $venue, 'provided' => false];
                }
            }
        }
        if ($items === []) {
            foreach ($request->getAdditionalEquipmentQuantities() as $name => $quantity) {
                $items[] = ['name' => (string) $name, 'quantity' => (int) $quantity, 'venue' => implode(', ', $request->getVenueNames()), 'provided' => false];
            }
        }
        foreach ($request->getProvidedEquipmentByVenue() as $venue => $venueItems) {
            foreach ($venueItems as $name => $quantity) {
                // The same item requested manually stays its own line; both are listed.
                $items[] = ['name' => (string) $name, 'quantity' => (int) $quantity, 'venue' => (string) $venue, 'provided' => true];
            }
        }

        return $items;
    }

    /** Notify each affected custodian once for this event (after the transaction commits). */
    public function notify(FacilityRequest $request, string $event): void
    {
        DB::afterCommit(function () use ($request, $event): void {
            try {
                $this->send($request->fresh() ?? $request, $event);
            } catch (\Throwable $e) {
                Log::warning('Equipment custodian notice failed.', ['request_id' => $request->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        });
    }

    private function send(FacilityRequest $request, string $event): void
    {
        $byCustodian = [];
        $unassigned = [];
        foreach ($this->items($request) as $item) {
            $ids = $request->getAuthorizedCustodianIdsForEquipment($item['name']);
            if ($ids === []) {
                if (! $item['provided']) {
                    $unassigned[] = $item;
                }
                continue;
            }
            foreach ($ids as $id) {
                $byCustodian[$id][] = $item;
            }
        }

        $fingerprint = md5(json_encode([$event, $request->start_date?->toDateString(), $request->end_date?->toDateString(), $request->start_time, $request->end_time, $request->getVenueNames()]));
        foreach (User::query()->whereIn('id', array_keys($byCustodian))->get() as $custodian) {
            $items = $byCustodian[$custodian->id];
            $key = $request->id . ':' . $event . ':' . $fingerprint . ':' . md5(json_encode($items));
            $this->sendOnce($custodian, new EquipmentReservationNotice($request, $event, $items, $key));
        }

        if ($unassigned !== []) {
            Log::warning('Reservation equipment has no assigned custodian; sent to the Supply Office.', [
                'request_id' => $request->id,
                'control_number' => $request->control_number,
                'items' => array_column($unassigned, 'name'),
            ]);
            $key = $request->id . ':unassigned:' . $event . ':' . md5(json_encode($unassigned));
            foreach (User::query()->withAnyRole(['admin', 'supply_office'])->get() as $officer) {
                $this->sendOnce($officer, new EquipmentReservationNotice($request, $event === 'submitted' ? 'unassigned' : $event, $unassigned, $key));
            }
        }
    }

    private function sendOnce(User $user, EquipmentReservationNotice $notice): void
    {
        $exists = $user->notifications()->where('type', EquipmentReservationNotice::class)->get()
            ->contains(fn ($n) => ($n->data['notice_key'] ?? null) === $notice->noticeKey);
        if (! $exists) {
            $user->notify($notice);
        }
    }
}
