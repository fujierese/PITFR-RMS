<?php

namespace App\Support;

use App\Models\FacilityRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4: the IGP Coordinator's four Reservation Management sections, shared by the
 * dashboard and the sidebar so both always agree.
 *
 *   new       New Inquiries         submitted / under IGP review (IGP acts: forward)
 *   approved  Approved Requests     Supply Office approved; IGP informs the outsider
 *   ongoing   Ongoing Coordination  IGP coordinating requirements; reports completion
 *   closed    Completed / Closed    confirmed, rejected, cancelled, expired (by actual status)
 *
 * Inquiries with the Venue Custodian or Supply Office are shown read-only under New
 * Inquiries ("Forwarded for review") and are never counted as IGP work.
 */
class IgpInquiries
{
    public const SECTIONS = [
        'new' => [
            'label' => 'New Inquiries',
            'hint' => 'Review the inquiry details, check that the required information is provided, then forward it to the venue custodian for availability and capacity review.',
        ],
        'approved' => [
            'label' => 'Approved Requests',
            'hint' => 'Approved by the Supply Office. Inform the outsider, communicate the institutional requirements (payment is arranged outside PITFR), and start coordination.',
        ],
        'ongoing' => [
            'label' => 'Ongoing Coordination',
            'hint' => 'Follow up on outstanding requirements, monitor compliance, and report completed requirements to the Supply Office for final confirmation.',
        ],
        'closed' => [
            'label' => 'Completed / Closed',
            'hint' => 'Confirmed reservations and rejected, cancelled or expired inquiries, based on their actual workflow status (last 60 days; older ones are in Reservation History).',
        ],
    ];

    /** @return Collection<int, FacilityRequest> every outsider request IGP may see */
    public static function all(): Collection
    {
        $hasStage = Schema::hasColumn('facility_requests', 'outsider_stage');

        return FacilityRequest::query()
            ->with(['requester', 'reservationSchedule', 'requestVenues'])
            ->upcomingReservation()
            ->where(function ($query) use ($hasStage): void {
                if ($hasStage) {
                    $query->whereNotNull('outsider_stage');
                }
                $query->orWhereHas('requester', fn ($requester) => $requester->where('requestor_type', 'outsider'));
            })
            ->where(function ($query): void {
                // Finished requests stay listed for 60 days.
                $query->whereNotIn('status', ['approved', 'completed', 'rejected', 'cancelled', 'expired'])
                    ->orWhere('updated_at', '>=', now()->subDays(60));
            })
            ->orderBy('start_date')
            ->get();
    }

    /** The section an inquiry belongs to; 'in_review' = with the custodian / Supply Office. */
    public static function sectionOf(FacilityRequest $request): string
    {
        // The actual workflow status decides "closed", not IGP's communication.
        if (in_array($request->status, ['approved', 'completed', 'rejected', 'cancelled', 'expired'], true)) {
            return 'closed';
        }

        return match ($request->inferredOutsiderStage()) {
            'submitted', 'igp_review' => 'new',
            'approved' => 'approved',
            'igp_coordination' => 'ongoing',
            'confirmed', 'rejected', 'cancelled' => 'closed',
            default => 'in_review', // custodian_review, endorsed, not_endorsed
        };
    }

    /** True when the inquiry is waiting for IGP (not for a custodian or the Supply Office). */
    public static function needsIgp(FacilityRequest $request): bool
    {
        return match (self::sectionOf($request)) {
            'new', 'approved' => true,
            'ongoing' => $request->igp_requirements_reported_at === null,
            default => false,
        };
    }

    /** One status label used on the dashboard, sidebar sections and inquiry details. */
    public static function statusLabel(FacilityRequest $request): string
    {
        if ($request->status === 'expired') {
            return 'Expired';
        }
        $stage = $request->inferredOutsiderStage();
        if ($stage === 'igp_coordination' && $request->igp_requirements_reported_at) {
            return 'Requirements Reported — Awaiting Supply Office Confirmation';
        }

        return FacilityRequest::OUTSIDER_STAGES[$stage] ?? 'Outsider Request';
    }

    /** @return array<string, Collection<int, FacilityRequest>> section => inquiries */
    public static function grouped(?Collection $requests = null): array
    {
        $requests ??= self::all();
        $groups = array_fill_keys(array_merge(array_keys(self::SECTIONS), ['in_review']), collect());
        foreach ($requests as $request) {
            $groups[self::sectionOf($request)]->push($request);
        }
        $groups['new'] = $groups['new']->sortBy('created_at')->values();
        $groups['closed'] = $groups['closed']->sortByDesc('updated_at')->take(50)->values();

        return $groups;
    }

    /** @return array<string, int> IGP-actionable counts per section (closed: total shown). */
    public static function counts(?array $groups = null): array
    {
        $groups ??= self::grouped();
        $counts = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $counts[$key] = $key === 'closed'
                ? $groups['closed']->count()
                : $groups[$key]->filter(fn (FacilityRequest $r) => self::needsIgp($r))->count();
        }

        return $counts;
    }
}
