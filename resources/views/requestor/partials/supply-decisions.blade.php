{{-- Additional Part 2: Supply Office decisions offered only when they apply. --}}
@php
    $d = $supplyDecisions;
    $field = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm';
    $label = 'block text-sm font-medium text-slate-700';
@endphp
<div class="space-y-3" data-supply-decisions>
    @if($d['conflicts']->isNotEmpty())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-950" data-conflict-panel>
            <p class="font-semibold">Conflict to resolve</p>
            @foreach($d['conflicts'] as $conflict)
                @php $other = $conflict->otherSide($request); @endphp
                <p class="mt-1">
                    @if((int) $conflict->urgent_request_id === (int) $request->id)
                        This approved urgent reservation overlaps
                    @else
                        Approved urgent reservation
                    @endif
                    <a href="{{ route('request.show', $other?->id) }}" class="font-semibold underline">{{ $other?->control_number }}</a>
                    @if((int) $conflict->urgent_request_id !== (int) $request->id) needs this booking @else at @endif
                    {{ $conflict->venue_name }}.
                </p>
            @endforeach
            <p class="mt-2 text-xs">Decide which reservation is better suited to the venue. Adjust this one below, or open the other reservation and adjust it there. Nothing changes automatically.</p>
        </div>
    @endif

    @if($d['revision'] !== [])
        <details class="rounded-2xl border border-slate-200 bg-white p-4" @if($errors->has('decision') && old('decision') === 'revision') open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Request Revision</summary>
            <ul class="mt-2 list-disc pl-5 text-xs text-slate-600">
                @foreach($d['revision'] as $item)<li>{{ $item }}</li>@endforeach
            </ul>
            <form method="POST" action="{{ route('request.supply.decision', $request->id) }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="decision" value="revision">
                <label class="{{ $label }}">What needs correcting
                    <input name="reason" required maxlength="255" value="{{ old('decision') === 'revision' ? old('reason') : ($d['revision'][0] ?? '') }}" class="{{ $field }}">
                </label>
                <label class="{{ $label }}">Instructions for the requestor
                    <textarea name="explanation" rows="2" required minlength="10" maxlength="2000" class="{{ $field }}">{{ old('decision') === 'revision' ? old('explanation') : '' }}</textarea>
                </label>
                <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Send for Revision</button>
            </form>
        </details>
    @endif

    @if($d['alternative_venues'] !== [])
        <details class="rounded-2xl border border-sky-200 bg-white p-4" @if(old('decision') === 'alternative_venue') open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-sky-900">Assign Alternative Venue</summary>
            <p class="mt-1 text-xs text-slate-600">{{ $d['venue_concern'] }} These venues are free for the same schedule and fit the participants.</p>
            <form method="POST" action="{{ route('request.supply.decision', $request->id) }}" class="mt-3 space-y-3" data-swal-confirm data-swal-title="Assign this venue?" data-swal-text="The requestor and custodians will be notified." data-swal-confirm-text="Assign venue">
                @csrf
                <input type="hidden" name="decision" value="alternative_venue">
                <label class="{{ $label }}">Alternative venue
                    <select name="venue" required class="{{ $field }}">
                        @foreach($d['alternative_venues'] as $venueOption)
                            <option value="{{ $venueOption }}" @selected(old('venue') === $venueOption)>{{ $venueOption }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="{{ $label }}">Reason
                    <input name="reason" required maxlength="255" value="{{ old('decision') === 'alternative_venue' ? old('reason') : '' }}" placeholder="e.g. The court is needed for an institute-wide activity" class="{{ $field }}">
                </label>
                <label class="{{ $label }}">Explanation for the requestor
                    <textarea name="explanation" rows="2" required minlength="10" maxlength="2000" class="{{ $field }}">{{ old('decision') === 'alternative_venue' ? old('explanation') : '' }}</textarea>
                </label>
                <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Assign Venue</button>
            </form>
        </details>
    @endif

    @if($d['conflicts']->isNotEmpty() && $d['schedules'] !== [])
        <details class="rounded-2xl border border-amber-200 bg-white p-4" @if(old('decision') === 'reschedule') open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-amber-900">Reschedule</summary>
            <p class="mt-1 text-xs text-slate-600">Free dates for the same venue and times.</p>
            <form method="POST" action="{{ route('request.supply.decision', $request->id) }}" class="mt-3 space-y-3" data-swal-confirm data-swal-title="Move the reservation to this date?" data-swal-text="The requestor and custodians will be notified." data-swal-confirm-text="Reschedule">
                @csrf
                <input type="hidden" name="decision" value="reschedule">
                <label class="{{ $label }}">New date
                    <select name="start_date" required class="{{ $field }}">
                        @foreach($d['schedules'] as $option)
                            <option value="{{ $option['start_date'] }}" @selected(old('start_date') === $option['start_date'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="{{ $label }}">Reason
                    <input name="reason" required maxlength="255" value="{{ old('decision') === 'reschedule' ? old('reason') : '' }}" class="{{ $field }}">
                </label>
                <label class="{{ $label }}">Explanation for the requestor
                    <textarea name="explanation" rows="2" required minlength="10" maxlength="2000" class="{{ $field }}">{{ old('decision') === 'reschedule' ? old('explanation') : '' }}</textarea>
                </label>
                <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Reschedule</button>
            </form>
        </details>
    @endif

    @if($d['decline'])
        <details class="rounded-2xl border border-red-200 bg-white p-4" @if($errors->has('decline_reason') || $errors->has('notes')) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-red-800">Decline Reservation</summary>
            <form method="POST" action="{{ route('request.supply.decline', $request->id) }}" class="decline-form mt-3 space-y-3" data-swal-confirm data-swal-title="Decline this reservation?" data-swal-text="This is a final decision. The requestor will see your reason and explanation." data-swal-confirm-text="Decline" data-swal-confirm-color="#dc2626">
                @csrf
                <label class="{{ $label }}">Reason
                    <select name="decline_reason" required class="{{ $field }}">
                        <option value="">Choose a reason</option>
                        @foreach(\App\Services\SupplyOfficeDecisions::DECLINE_REASONS as $key => $text)
                            <option value="{{ $key }}" @selected(old('decline_reason') === $key)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('decline_reason')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="{{ $label }}">Written explanation
                    <textarea name="notes" rows="3" required minlength="10" maxlength="2000" class="{{ $field }}">{{ old('notes') }}</textarea>
                    @error('notes')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Decline Reservation</button>
            </form>
        </details>
    @endif
</div>
