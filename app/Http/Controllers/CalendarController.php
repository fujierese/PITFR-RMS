<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $request->get('role', $this->getUserRole($user));
        $dashboardData = $this->buildDashboardData($role, $user);

        return view('calendar.index', compact('role', 'dashboardData'));
    }

    public function getEvents(Request $request)
    {
        $user = auth()->user();
        $role = $this->getUserRole($user);

        $query = FacilityRequest::with(['user', 'requestVenues', 'requestEquipment', 'reservationSchedule']);

        if ($user && $user->isCustodian()) {
            $requests = $this->getRequestsForCustodian($user);
        } elseif ($user && $user->isAdmin()) {
            $requests = $query->get();
        } else {
            // Guest/public view should see only approved and pending requests for availability checking
            $requests = $query->whereIn('status', ['approved', 'pending'])->get();
        }

        $events = $requests->map(function($req) use ($role) {
            $schedule = $req->reservationSchedule;
            $startDateTime = $schedule ? $schedule->start_datetime : \Illuminate\Support\Carbon::parse($req->start_date . ' ' . ($req->start_time ?? '00:00'));
            $endDateTime = $schedule ? $schedule->end_datetime : \Illuminate\Support\Carbon::parse(($req->end_date ?? $req->start_date) . ' ' . ($req->end_time ?? $req->start_time ?? '00:00'));

            // Ensure times are Carbon instances in the app timezone (Asia/Manila)
            if (!$startDateTime instanceof \Illuminate\Support\Carbon) {
                $startDateTime = \Illuminate\Support\Carbon::parse($startDateTime);
            }
            if (!$endDateTime instanceof \Illuminate\Support\Carbon) {
                $endDateTime = \Illuminate\Support\Carbon::parse($endDateTime);
            }

            // Calendar design requirement:
            // - explicit whole-day reservations are all-day events
            // - timed reservations must keep their exact start/end timestamps
            $normalizedDuration = strtolower((string) ($req->reservation_duration ?? ''));
            $isAllDay = in_array($normalizedDuration, ['whole_day', 'whole-day', 'whole day'], true);

            $eventStart = $startDateTime->copy()->format('Y-m-d\TH:i:s');
            $eventEnd = $endDateTime->copy()->format('Y-m-d\TH:i:s');

            $venueNames = $req->getVenueNames();
            $requestor = $req->user ?: $req->requester;
            $department = trim((string) ($req->department ?: ($requestor?->department ?? '')));
            $organization = trim((string) ($requestor?->office_or_organization ?? ''));
            if ($requestor?->requestor_type === 'outsider') {
                $organization = $organization !== '' ? $organization : 'External Requestor';
            } elseif ($organization === '') {
                $organization = null;
            }

            if ($department === '') {
                $department = $requestor?->department ?: 'N/A';
            }

            // Determine event color based on role and status
            $eventColor = $this->getEventColor($req, $role);

            if ($isAllDay && $eventEnd) {
                $eventEnd = $endDateTime->copy()->addDay()->format('Y-m-d\TH:i:s');
            }

            return [
                'id' => $req->id,
                'title' => $req->name_of_activity . ' (' . $req->control_number . ')',
                'start' => $eventStart,
                'end' => $eventEnd,
                'start_datetime' => $startDateTime->copy()->format('Y-m-d H:i:s'),
                'end_datetime' => $endDateTime->copy()->format('Y-m-d H:i:s'),
                'allDay' => $isAllDay,
                'status' => $req->status,
                'venue' => implode(', ', $venueNames),
                'backgroundColor' => $eventColor['background'],
                'borderColor' => $eventColor['border'],
                'textColor' => $eventColor['text'],
                'className' => $eventColor['className'],
                'extendedProps' => [
                    'status' => ucfirst($req->status),
                    'venue' => implode(', ', $venueNames),
                    'equipment' => $req->getEquipmentItems(),
                    'requestor' => $requestor ? $requestor->name : 'Unknown',
                    'controlNumber' => $req->control_number,
                    'time' => $startDateTime->format('H:i:s'),
                    'endTime' => $endDateTime->format('H:i:s'),
                    'purpose' => $req->name_of_activity,
                    'department' => $department,
                    'organization' => $organization,
                    'participants' => $req->expected_participants,
                    'priority' => $req->priority ?? 'regular',
                    'isUrgent' => (bool) ($req->is_emergency ?? false),
                    'urgentReason' => $req->emergency_justification ?? null,
                    'venueStatus' => $req->venue_status,
                    'equipmentStatus' => $req->equipment_status,
                    'facilityRequestId' => $req->id,
                    'requestUrl' => route('request.show', $req->id),
                ]
            ];
        });


        return response()->json($events);
    }

    private function getEventColor($request, $role = null)
    {
        $venueColor = $this->getVenueColor($request);
        $isApproved = $request->status === 'approved';

        if ($role === 'requestor' && $isApproved) {
            return [
                'background' => '#10B981',
                'border' => '#10B981',
                'text' => '#FFFFFF',
                'className' => 'approved-event'
            ];
        }

        // For guest and all other dashboards, use the venue color for status-aware events.
        return [
            'background' => $venueColor,
            'border' => $venueColor,
            'text' => '#FFFFFF',
            'className' => $isApproved ? 'approved-event' : 'pending-event'
        ];
    }

    private function getVenueColor($request)
    {
        $venue = implode(', ', $request->getVenueNames());

        // Use request-specific color_code when available.
        if (! empty($request->color_code)) {
            return $request->color_code;
        }

        $primaryVenue = explode(', ', $venue)[0] ?? null;
        if ($primaryVenue) {
            $venueModel = Venue::where('name', $primaryVenue)->first();
            if ($venueModel && ! empty($venueModel->color_code)) {
                return $venueModel->color_code;
            }
        }

        return match($primaryVenue) {
            'Conference Hall & Interaction Center (CHIC)' => '#3B82F6', 
            'Gymnasium' => '#3B82F6',
            'Balay Alumni' => '#10B981',
            'Oval Grounds' => '#F59E0B',
            'Covered Court' => '#8B5CF6',
            'AVR' => '#8B5CF6',
            'Volleyball Court' => '#F97316',
            'Others (specify)' => '#6B7280',
            default => '#6B7280'
        };
    }

    private function getUserRole($user)
    {
        if (! $user) {
            return 'guest';
        }

        if ($user->isAdmin()) {
            return 'admin';
        } elseif ($user->isCustodian()) {
            return 'custodian';
        } else {
            return 'requestor';
        }
    }

    private function buildDashboardData(string $role, $user = null): array
    {
        $today = now()->toDateString();
        $data = [
            'role' => $role,
            'roleLabel' => match($role) {
                'guest' => 'Guest',
                'requestor' => 'Requestor',
                'custodian' => 'Custodian',
                'admin' => 'Administrator',
                default => 'User',
            },
            'showCreateRequest' => $role === 'requestor',
            'showLoginCTA' => $role === 'guest',
            'showUserManagement' => $role === 'admin',
            'showFinalApproval' => $role === 'admin',
            'showUsageReports' => $role === 'admin',
            'showExport' => $role === 'admin',
            'showInventory' => $role === 'custodian',
            'showAuditLogs' => $role === 'admin',
            'showHowToRequest' => $role === 'guest',
            'showViewOnlyCalendar' => $role === 'guest',
            'showStatsCards' => $role === 'requestor',
            'showVerificationQueue' => $role === 'custodian',
        ];

        if ($role === 'requestor' && $user) {
            $personalRequests = FacilityRequest::with(['requestVenues', 'requestEquipment', 'reservationSchedule'])
                ->where('requested_by_id', $user->id)
                ->orderByDesc('created_at')
                ->get();

            $data['stats'] = [
                'upcoming' => $personalRequests->filter(function ($request) {
                    $schedule = $request->reservationSchedule;
                    $start = $schedule ? $schedule->start_datetime : $request->start_date;
                    return $start && optional($start)->toDateString() >= now()->toDateString();
                })->count(),
                'pending' => $personalRequests->where('status', 'pending')->count(),
                'approved' => $personalRequests->where('status', 'approved')->count(),
            ];
            $data['personalRequests'] = $personalRequests;
        }

        if ($role === 'custodian' && $user) {
            $data['custodianType'] = $user->custodianType() ?? 'venue';
            $data['verificationQueue'] = $this->getRequestsForCustodian($user);
            $data['inventory'] = Equipment::where('custodian_id', $user->id)->get();
            $data['verificationSummary'] = [
                'pending' => $data['verificationQueue']->where('status', 'pending')->count(),
                'approved' => $data['verificationQueue']->where('status', 'approved')->count(),
                'rejected' => $data['verificationQueue']->where('status', 'rejected')->count(),
            ];
        }

        if ($role === 'admin') {
            $data['finalApprovalQueue'] = FacilityRequest::where('status', 'pending')
                ->where('venue_status', 'approved')
                ->where('equipment_status', 'approved')
                ->orderByDesc('created_at')
                ->get();
            $data['finalSummary'] = [
                'total' => $data['finalApprovalQueue']->count(),
                'pending' => $data['finalApprovalQueue']->where('status', 'pending')->count(),
            ];
        }

        if ($role === 'admin') {
            $data['userCount'] = User::count();
            $data['totalRequests'] = FacilityRequest::count();
            $data['latestRequests'] = FacilityRequest::orderByDesc('created_at')->limit(5)->get();
        }

        return $data;
    }

    private function getRequestsForCustodian($user)
    {
        if (! $user) {
            return collect([]);
        }

        if ($user->isCustodianVenue()) {
            $venueNames = $user->venues()->pluck('name');
            if ($venueNames->isEmpty()) {
                return collect([]);
            }

            return FacilityRequest::where(function ($query) use ($venueNames) {
                foreach ($venueNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($name));
                }
            })->orderByDesc('created_at')->get();
        }

        if ($user->isCustodianEquipment()) {
            $equipmentNames = Equipment::where('custodian_id', $user->id)->pluck('name');
            if ($equipmentNames->isEmpty()) {
                return collect([]);
            }

            return FacilityRequest::where(function ($query) use ($equipmentNames) {
                foreach ($equipmentNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($name));
                }
            })->orderByDesc('created_at')->get();
        }

        return collect([]);
    }

    public function approveRequest(Request $request, $id)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $user = auth()->user();
        $type = $request->get('type'); // 'venue', 'equipment', or null for full approval

        $this->authorize('approve', $facilityRequest);
        abort_unless($user->isAdmin() || in_array($type, ['venue', 'equipment'], true), 403);

        if (! $user->isAdmin()) {
            $mayApproveVenue = $type === 'venue' && $user->isCustodianVenue()
                && collect($user->venues()->pluck('name'))->map(fn ($name) => mb_strtolower($name))
                    ->intersect(collect($facilityRequest->getVenueNames())->map(fn ($name) => mb_strtolower($name)))->isNotEmpty();
            $mayApproveEquipment = $type === 'equipment' && $user->isCustodianEquipment()
                && ! empty($facilityRequest->getAssignedEquipmentForCustodian($user->id));

            abort_unless($mayApproveVenue || $mayApproveEquipment, 403);
        }

        if ($user->isAdmin()) {
            if ($type === 'venue') {
                $facilityRequest->venue_status = 'approved';
            } elseif ($type === 'equipment') {
                $facilityRequest->equipment_status = 'approved';
            } else {
                $facilityRequest->status = 'approved';
                $facilityRequest->approved_by_id = $user->id;
                $facilityRequest->approved_by = $user->name;
                $facilityRequest->approved_date = now();
                $facilityRequest->venue_status = 'approved';
                $facilityRequest->equipment_status = 'approved';
            }
        } elseif ($user->isCustodianVenue() && ($type === 'venue' || !$type)) {
            $facilityRequest->venue_status = 'approved';
        } elseif ($user->isCustodianEquipment() && ($type === 'equipment' || !$type)) {
            $facilityRequest->equipment_status = 'approved';
        }

        $facilityRequest->save();

        return response()->json(['message' => 'Request approved successfully']);
    }

    public function checkConflicts(Request $request)
    {
        $request->validate([
            'venues' => 'required|array',
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'end_time' => 'required|date_format:H:i',
            'exclude_request_id' => 'nullable|integer'
        ]);

        $venues = $request->venues;
        $excludeId = $request->exclude_request_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date ?? $request->start_date;
        $scheduleRange = \App\Models\FacilityRequest::normalizeScheduleRange($startDate, $request->start_time, $endDate, $request->end_time);
        $requestedStart = $scheduleRange['start'];
        $requestedEnd = $scheduleRange['end'];

        $conflicts = [];

        // Check each venue for conflicts
        foreach ($venues as $venue) {
            $venueConflicts = FacilityRequest::where(fn ($query) => $query->matchesVenue($venue))
                ->where(function($query) {
                    $query->where(function($approvedQuery) {
                        $approvedQuery->where('status', 'approved')
                                      ->where('equipment_returned_status', '!=', 'returned');
                    })
                    ->orWhere(function($pendingQuery) {
                        $pendingQuery->where('status', 'pending')
                                     ->where('venue_status', '!=', 'rejected')
                                     ->where('equipment_status', '!=', 'rejected');
                    });
                })
                ->when($excludeId, function($query) use ($excludeId) {
                    return $query->where('id', '!=', $excludeId);
                })
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->with('user')
                ->get()
                ->filter(function($conflict) use ($requestedStart, $requestedEnd) {
                    return $conflict->overlapsTimeRange($requestedStart, $requestedEnd);
                });

            if ($venueConflicts->count() > 0) {
                $conflicts[$venue] = $venueConflicts->map(function($conflict) {
                    return [
                        'id' => $conflict->id,
                        'control_number' => $conflict->control_number,
                        'activity' => $conflict->name_of_activity,
                        'requestor' => $conflict->user ? $conflict->user->name : 'Unknown',
                        'status' => $conflict->status,
                        'venue_status' => $conflict->venue_status,
                           'priority' => $conflict->priority ?? 'regular',
                           'control_number' => $conflict->control_number ?? null,
                        'start_date' => $conflict->start_date->format('M d, Y'),
                        'end_date' => $conflict->end_date ? $conflict->end_date->format('M d, Y') : null,
                        'time' => $conflict->start_time,
                    ];
                });
            }
        }

        return response()->json([
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
            'message' => count($conflicts) > 0
                ? 'Conflicts detected with existing reservations'
                : 'No conflicts found'
        ]);
    }
}
