<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Http\Controllers\Concerns\ManagesAccountSettings;
use App\Models\FacilityRequest;
use App\Models\Venue;
use App\Notifications\NewFacilityRequestNotification;
use App\Notifications\RequestStatusChanged;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IgpController extends Controller
{
    use ManagesAccountSettings;

    /**
     * B.2: IGP dashboard. IGP reviews outsider inquiries, forwards them to the venue
     * custodian and coordinates requirements after Supply Office approval. IGP cannot
     * approve, reject, endorse or finalize a reservation, or collect payments.
     */
    public function index(Request $request)
    {
        // Every outsider request, including ones created before the inquiry workflow.
        $outsiderRequests = FacilityRequest::query()
            ->with(['requester', 'reservationSchedule', 'requestVenues'])
            ->upcomingReservation() // reservations that already took place are in Reservation History
            ->where(function ($query): void {
                // IGP 500 fix: the live database may not have the outsider_stage column yet
                // (its migration has not run there); outsider requesters are listed either way.
                if (\Illuminate\Support\Facades\Schema::hasColumn('facility_requests', 'outsider_stage')) {
                    $query->whereNotNull('outsider_stage');
                }
                $query->orWhereHas('requester', fn ($requester) => $requester->where('requestor_type', 'outsider'));
            })
            ->where(function ($query): void {
                // Finished requests stay listed for 60 days.
                $query->whereNotIn('status', ['approved', 'completed', 'rejected', 'cancelled'])
                    ->orWhere('updated_at', '>=', now()->subDays(60));
            })
            ->orderBy('start_date')
            ->get();
        // Additional Enhancements, search: the same rule, limited to outsider requests IGP handles.
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $outsiderRequests = $outsiderRequests
                ->filter(fn (FacilityRequest $item): bool => \App\Support\RequestSearch::matches($item, $search))
                ->values();
        }
        $inStages = fn (array $stages) => $outsiderRequests
            ->filter(fn (FacilityRequest $item): bool => in_array($item->inferredOutsiderStage(), $stages, true))
            ->values();

        $sections = [
            'review' => [
                'title' => 'New inquiries to review',
                'hint' => 'Check the details, then forward the inquiry to the venue custodian.',
                'requests' => $inStages(['submitted', 'igp_review'])->sortBy('created_at')->values(),
            ],
            'coordinate' => [
                'title' => 'Approved: inform the client of requirements',
                'hint' => 'The Supply Office approved these. Tell the client the payment and other institutional requirements.',
                'requests' => $inStages(['approved']),
            ],
            'coordinating' => [
                'title' => 'For IGP coordination',
                'hint' => 'Waiting for the client to complete the requirements. Report back once they have, so the Supply Office can confirm.',
                'requests' => $inStages(['igp_coordination']),
            ],
            'in_review' => [
                'title' => 'With the venue custodian or Supply Office',
                'hint' => 'No action needed from IGP right now.',
                'requests' => $inStages(['custodian_review', 'endorsed', 'not_endorsed']),
            ],
            'decided' => [
                'title' => 'Final decisions (last 60 days)',
                'hint' => 'Confirmed, rejected or cancelled by the Supply Office.',
                'requests' => $inStages(['confirmed', 'rejected', 'cancelled'])->sortByDesc('updated_at')->take(50)->values(),
            ],
        ];

        return view('igp.index', compact('sections', 'search'));
    }

    /** B.2: forward a reviewed inquiry to the designated venue custodian. */
    public function forward(Request $request, FacilityRequest $facilityRequest)
    {
        $validated = $request->validate([
            'igp_review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $igp = $request->user();

        $facilityRequest = DB::transaction(function () use ($facilityRequest, $validated, $igp): FacilityRequest {
            $locked = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless(
                in_array($locked->outsider_stage, ['submitted', 'igp_review'], true) && $locked->status === 'pending',
                409,
                'This inquiry is no longer waiting for IGP review.'
            );
            $notes = trim((string) ($validated['igp_review_notes'] ?? ''));
            $locked->forceFill([
                'outsider_stage' => 'custodian_review',
                'igp_review_notes' => $notes !== '' ? $notes : null,
                'igp_forwarded_by_id' => $igp->getKey(),
                'igp_forwarded_at' => now(),
            ])->save();
            $locked->addHistory(
                'igp_forwarded_to_custodian',
                'IGP (' . $igp->name . ') reviewed the inquiry and forwarded it to the venue custodian.' . ($notes !== '' ? ' Notes: ' . $notes : ''),
                $igp->getKey()
            );

            return $locked;
        });

        $custodianIds = Venue::query()->whereIn('name', $facilityRequest->getVenueNames())->pluck('custodian_id')->filter()->unique();
        foreach (User::query()->whereIn('id', $custodianIds)->get() as $custodian) {
            $custodian->notify(new NewFacilityRequestNotification($facilityRequest, $igp->name . ' - IGP'));
        }
        $facilityRequest->requester?->notify(new RequestStatusChanged($facilityRequest, 'outsider_inquiry_forwarded', '', $igp->name . ' - IGP'));

        return back()->with('success', 'Inquiry forwarded to the venue custodian.');
    }

    /** B.5: after Supply Office approval, IGP tells the client the requirements. */
    public function startCoordination(Request $request, FacilityRequest $facilityRequest)
    {
        $validated = $request->validate([
            'igp_coordination_notes' => ['required', 'string', 'max:3000'],
        ], [
            'igp_coordination_notes.required' => 'Write the payment and other requirements the client must complete.',
        ]);
        $igp = $request->user();

        $facilityRequest = DB::transaction(function () use ($facilityRequest, $validated, $igp): FacilityRequest {
            $locked = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless($locked->outsider_stage === 'approved' && $locked->status === 'pending', 409, 'This inquiry is not waiting for IGP coordination.');
            $locked->forceFill([
                'outsider_stage' => 'igp_coordination',
                'igp_coordination_notes' => trim($validated['igp_coordination_notes']),
                'igp_coordination_started_at' => now(),
            ])->save();
            $locked->addHistory(
                'igp_coordination_started',
                'IGP (' . $igp->name . ') informed the client of the requirements: ' . trim($validated['igp_coordination_notes']),
                $igp->getKey()
            );

            return $locked;
        });

        $facilityRequest->requester?->notify(new RequestStatusChanged($facilityRequest, 'outsider_requirements_sent', $facilityRequest->igp_coordination_notes, $igp->name . ' - IGP'));

        return back()->with('success', 'Requirements sent to the client. The inquiry is now For IGP Coordination.');
    }

    /** B.5: IGP reports that the client says the requirements are complete. */
    public function reportRequirements(Request $request, FacilityRequest $facilityRequest)
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ], [
            'notes.required' => 'Describe what the client completed (for example, the official receipt number issued by the Cashier).',
        ]);
        $igp = $request->user();

        DB::transaction(function () use ($facilityRequest, $validated, $igp): void {
            $locked = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless($locked->outsider_stage === 'igp_coordination' && $locked->status === 'pending', 409, 'This inquiry is not in IGP coordination.');
            $locked->forceFill(['igp_requirements_reported_at' => now()])->save();
            $locked->addHistory(
                'igp_requirements_reported',
                'IGP (' . $igp->name . ') reported the client\'s requirements as completed: ' . trim($validated['notes']) . ' The Supply Office verifies and confirms the reservation.',
                $igp->getKey()
            );
        });

        Notification::send(
            User::query()->withAnyRole(['admin', 'supply_office'])->get(),
            new RequestStatusChanged($facilityRequest->fresh(), 'outsider_requirements_reported', trim($validated['notes']), $igp->name . ' - IGP')
        );

        return back()->with('success', 'The Supply Office has been told the requirements are complete.');
    }

    public function settings()
    {
        return view('igp.settings', [
            'user' => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'surname' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->name = User::formatFullName(
            $validated['surname'] ?? $user->surname,
            $validated['first_name'] ?? $user->first_name,
            $validated['middle_name'] ?? $user->middle_name,
            $validated['suffix'] ?? $user->suffix,
        );
        $user->save();

        return redirect()->route('igp.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        $user->tokens()->delete();
        $this->notifyPasswordChanged($user);

        return redirect()->route('igp.settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        return $this->saveNotificationPreferences($request, 'igp.settings');
    }

    public function updateSignature(Request $request)
    {
        return $this->saveSignature($request, 'igp.settings');
    }
}
