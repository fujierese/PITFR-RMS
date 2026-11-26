<?php

namespace App\Http\Controllers;

use App\Models\FacilityRequest;
use App\Services\CapacityExceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Part 2: the Venue Custodian decides capacity exceptions for their own venues; the
 * requestor answers a suggested alternative venue. Permissions are enforced here and in
 * CapacityExceptions, not only by hiding buttons.
 */
class CapacityExceptionController extends Controller
{
    public function __construct(private readonly CapacityExceptions $capacity)
    {
    }

    public function decide(Request $request, int $id)
    {
        abort_unless(CapacityExceptions::enabled(), 404);
        $facilityRequest = FacilityRequest::findOrFail($id);
        $user = Auth::user();
        abort_unless($user && $user->isCustodianVenue(), 403);

        $validated = $request->validate([
            'venue' => ['required', 'string', 'max:191'],
            'decision' => ['required', 'in:approved,not_approved'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required_if:decision,not_approved', 'nullable', 'string', 'min:5', 'max:1000'],
            'next_step' => ['required_if:decision,not_approved', 'nullable', 'in:requestor_choose,suggest_venue'],
            'suggested_venue' => ['required_if:next_step,suggest_venue', 'nullable', 'string', 'max:191'],
            'recommendation' => ['required_if:next_step,suggest_venue', 'nullable', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required_if' => 'Explain why the venue cannot hold the requested number of participants.',
            'next_step.required_if' => 'Choose what happens next: the requestor chooses another venue, or you suggest one.',
            'suggested_venue.required_if' => 'Choose the venue you recommend.',
            'recommendation.required_if' => 'Write your recommendation for the requestor.',
        ]);
        if (! in_array($validated['venue'], $this->capacity->decidableVenuesFor($facilityRequest, $user), true)) {
            abort(403, 'You can only decide capacity exceptions for your own venues that are still waiting.');
        }

        $this->capacity->decide($facilityRequest, $validated['venue'], $user, $validated);

        return redirect()->to(route('request.show', $facilityRequest->id) . '#capacity-exception')->with('success', $validated['decision'] === 'approved'
            ? 'Capacity exception approved for ' . $validated['venue'] . '. You can now confirm the venue.'
            : 'Capacity exception not approved. The requestor was notified.');
    }

    public function accept(Request $request, int $id)
    {
        abort_unless(CapacityExceptions::enabled(), 404);
        $facilityRequest = FacilityRequest::findOrFail($id);
        $user = Auth::user();
        abort_unless($user && (int) $facilityRequest->requested_by_id === (int) $user->id, 403);
        $validated = $request->validate(['venue' => ['required', 'string', 'max:191']]);

        $updated = $this->capacity->acceptSuggestion($facilityRequest, $validated['venue'], $user);

        return redirect()->route('request.show', $updated->id)->with('success', 'Venue changed. The new venue\'s custodian will now verify it.');
    }
}
