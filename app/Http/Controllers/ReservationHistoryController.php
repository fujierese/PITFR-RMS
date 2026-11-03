<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReservationHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = FacilityRequest::query()
            ->with('requestVenues')
            ->where(function ($dateRange): void {
                $today = now()->toDateString();
                $dateRange->whereDate('end_date', '<=', $today)
                    ->orWhere(function ($legacyRange) use ($today): void {
                        $legacyRange->whereNull('end_date')
                            ->whereDate('start_date', '<=', $today);
                    })
                    ->orWhereHas('requestVenues', function ($venueRange) use ($today): void {
                        $venueRange->where(function ($scheduleRange) use ($today): void {
                            $scheduleRange->whereDate('end_date', '<=', $today)
                                ->orWhere(function ($legacyVenueRange) use ($today): void {
                                    $legacyVenueRange->whereNull('end_date')
                                        ->whereDate('start_date', '<=', $today);
                                });
                        });
                    });
            })
            ->whereIn('status', ['approved', 'completed'])
            ->orderByDesc('start_date')
            ->orderByDesc('start_time');

        if (! $user->isAdmin()) {
            if ($user->isIgp()) {
                $query->whereHas('requester', fn ($requester) => $requester->where('requestor_type', 'outsider'));
            } elseif ($user->isCustodian()) {
                $venueNames = Venue::query()->where('custodian_id', $user->id)->pluck('name');
                $equipmentNames = Equipment::query()
                    ->where('custodian_id', $user->id)
                    ->orWhereJsonContains('authorized_custodian_ids', (string) $user->id)
                    ->pluck('name');

                $query->where(function ($assignedRequests) use ($venueNames, $equipmentNames): void {
                    foreach ($venueNames as $name) {
                        $assignedRequests->orWhere(fn ($match) => $match->matchesVenue($name));
                    }
                    foreach ($equipmentNames as $name) {
                        $assignedRequests->orWhere(fn ($match) => $match->matchesEquipment($name));
                    }

                    if ($venueNames->isEmpty() && $equipmentNames->isEmpty()) {
                        $assignedRequests->whereRaw('1 = 0');
                    }
                });
            } else {
                $query->where('requested_by_id', $user->id);
            }
        }

        if ($request->filled('search')) {
            $term = '%' . mb_strtolower(trim((string) $request->input('search'))) . '%';
            $query->where(function ($filter) use ($term): void {
                $filter->whereRaw('LOWER(control_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name_of_activity) LIKE ?', [$term])
                    ->orWhereHas('requestVenues', fn ($venues) => $venues->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }

        $completedReservations = $query->get()
            ->filter(fn (FacilityRequest $reservation): bool => $reservation->isReservationComplete())
            ->values();

        $years = $completedReservations
            ->flatMap(function (FacilityRequest $reservation): array {
                return collect($reservation->getVenueNames())
                    ->map(function (string $venueName) use ($reservation): ?int {
                        $startDate = $reservation->getVenueSchedule($venueName)['start_date'];

                        return $startDate ? (int) substr($startDate, 0, 4) : null;
                    })
                    ->all();
            })
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();
        $venues = $completedReservations
            ->flatMap(fn (FacilityRequest $reservation): array => $reservation->getVenueNames())
            ->unique(fn (string $venueName): string => mb_strtolower($venueName))
            ->sort()
            ->values();

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $completedReservations = $completedReservations->filter(
                function (FacilityRequest $reservation) use ($year): bool {
                    foreach ($reservation->getVenueNames() as $venueName) {
                        $startDate = $reservation->getVenueSchedule($venueName)['start_date'];
                        if ($startDate && (int) substr($startDate, 0, 4) === $year) {
                            return true;
                        }
                    }

                    return false;
                }
            )->values();
        }

        if ($request->filled('venue')) {
            $venue = mb_strtolower(trim((string) $request->input('venue')));
            $completedReservations = $completedReservations->filter(
                fn (FacilityRequest $reservation): bool => collect($reservation->getVenueNames())
                    ->contains(fn (string $venueName): bool => mb_strtolower($venueName) === $venue)
            )->values();
        }

        if ($request->input('sort') === 'oldest') {
            $completedReservations = $completedReservations->sortBy(
                fn (FacilityRequest $reservation): string => $reservation->getRequestedStartDateTime()->toDateTimeString()
            )->values();
        }

        $perPage = 30;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $reservations = new LengthAwarePaginator(
            $completedReservations->forPage($page, $perPage)->values(),
            $completedReservations->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('history.index', [
            'reservations' => $reservations,
            'years' => $years,
            'venues' => $venues,
        ]);
    }
}
