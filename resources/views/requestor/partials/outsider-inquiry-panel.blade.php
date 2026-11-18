{{-- B: outsider reservation inquiry status, details and the actions each role may take. --}}
@php
    $viewer = auth()->user();
    $stage = $request->outsider_stage;
    $stageOrder = ['submitted', 'igp_review', 'custodian_review', 'endorsed', 'approved', 'igp_coordination', 'confirmed'];
    $stageIndex = array_search($stage === 'not_endorsed' ? 'endorsed' : $stage, $stageOrder, true);
    $steps = [
        ['Submitted', 0],
        ['IGP review', 1],
        ['Custodian review', 2],
        ['Supply Office review', 3],
        ['Approved', 4],
        ['IGP coordination', 5],
        ['Confirmed', 6],
    ];
    $isClosed = in_array($stage, ['rejected', 'cancelled'], true);
    $isViewerIgp = $viewer?->isIgp();
    $isViewerSupplyOffice = $viewer?->isAdmin();
@endphp
<section aria-labelledby="outsider-inquiry-heading" class="mb-6 rounded-[28px] border border-sky-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-sky-700">Outsider reservation inquiry</p>
            <h2 id="outsider-inquiry-heading" class="mt-1 text-lg font-semibold text-slate-900">{{ $request->outsiderStageLabel() }}</h2>
            @if($request->status === 'needs_reschedule')
                <p class="mt-1 text-sm text-amber-800">The Supply Office asked for an alternative venue or schedule.</p>
            @endif
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isClosed ? 'bg-red-100 text-red-800' : ($stage === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800') }}">
            {{ $request->outsiderStageLabel() }}
        </span>
    </div>

    @unless($isClosed)
        <ol class="mt-4 grid gap-2 text-xs sm:grid-cols-7" aria-label="Inquiry progress">
            @foreach($steps as [$label, $index])
                @php $state = $stageIndex === false ? 'todo' : ($index < $stageIndex ? 'done' : ($index === $stageIndex ? 'current' : 'todo')); @endphp
                <li class="rounded-xl border px-2 py-2 text-center font-medium {{ $state === 'done' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($state === 'current' ? 'border-sky-300 bg-sky-50 text-sky-900' : 'border-slate-200 bg-slate-50 text-slate-500') }}" @if($state === 'current') aria-current="step" @endif>
                    {{ $label }}
                </li>
            @endforeach
        </ol>
    @endunless

    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Organization / Client</dt>
            <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $request->organization_name ?: ($request->requester?->office_or_organization ?: 'Individual') }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Contact person</dt>
            <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $request->contact_person ?: ($request->requester?->name ?? '—') }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Contact number</dt>
            <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $request->contact_number ?: ($request->requester?->contact_number ?: '—') }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Email address</dt>
            <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $request->contact_email ?: ($request->requester?->username ?? '—') }}</dd>
        </div>
    </dl>

    @if($request->additional_remarks)
        <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><span class="font-semibold text-slate-900">Client's remarks:</span> {{ $request->additional_remarks }}</p>
    @endif
    @if($request->igp_review_notes)
        <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><span class="font-semibold text-slate-900">IGP review notes:</span> {{ $request->igp_review_notes }}</p>
    @endif
    @if($request->igp_coordination_notes)
        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">
            <p class="font-semibold">Requirements from IGP</p>
            <p class="mt-1 whitespace-pre-line">{{ $request->igp_coordination_notes }}</p>
            <p class="mt-2 text-xs text-sky-800">Payment is handled through the institution's official payment procedure, outside PITFR. Authorized personnel issue the official receipt.</p>
        </div>
    @endif
    @if($request->outsider_hold_until && in_array($stage, \App\Models\FacilityRequest::OUTSIDER_HOLD_STAGES, true))
        <p class="mt-4 rounded-xl border px-3 py-2 text-sm {{ $request->hasActiveOutsiderHold() ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-800' }}">
            @if($request->hasActiveOutsiderHold())
                Schedule held until {{ $request->outsider_hold_until->format('F j, Y') }} while the requirements are completed.
            @else
                The requirements deadline ({{ $request->outsider_hold_until->format('F j, Y') }}) has passed; the schedule is no longer held.
            @endif
        </p>
    @endif
    @if($request->igp_requirements_reported_at)
        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">IGP reported the requirements complete on {{ $request->igp_requirements_reported_at->format('F j, Y g:i A') }}.</p>
    @endif

    {{-- IGP actions: review and coordination only; IGP cannot approve, reject or endorse. --}}
    @if($isViewerIgp && $request->status === 'pending')
        @if(in_array($stage, ['submitted', 'igp_review'], true))
            <form method="POST" action="{{ route('igp.forward', $request) }}" class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4" data-swal-confirm data-swal-title="Forward to the venue custodian?" data-swal-text="The venue custodian will check availability and endorse or not endorse the inquiry." data-swal-icon="question" data-swal-confirm-text="Forward">
                @csrf
                <label for="igp_review_notes" class="block text-sm font-semibold text-slate-800">Review notes for the venue custodian <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="igp_review_notes" name="igp_review_notes" rows="3" maxlength="2000" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('igp_review_notes') }}</textarea>
                <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Forward to venue custodian</button>
            </form>
        @elseif($stage === 'approved')
            <form method="POST" action="{{ route('igp.coordinate', $request) }}" class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4" data-swal-confirm data-swal-title="Send the requirements to the client?" data-swal-icon="question" data-swal-confirm-text="Send requirements">
                @csrf
                <label for="igp_coordination_notes" class="block text-sm font-semibold text-slate-800">Payment and other requirements for the client</label>
                <p class="text-xs text-slate-500">Describe the fee, where and how to pay through the official procedure, the deadline, and any other documents. The client is notified.</p>
                <textarea id="igp_coordination_notes" name="igp_coordination_notes" rows="4" maxlength="3000" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('igp_coordination_notes') }}</textarea>
                @error('igp_coordination_notes')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Send requirements to client</button>
            </form>
        @elseif($stage === 'igp_coordination')
            <form method="POST" action="{{ route('igp.requirements', $request) }}" class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4" data-swal-confirm data-swal-title="Tell the Supply Office the requirements are complete?" data-swal-icon="question" data-swal-confirm-text="Notify Supply Office">
                @csrf
                <label for="requirements_notes" class="block text-sm font-semibold text-slate-800">What the client completed</label>
                <p class="text-xs text-slate-500">For example, the official receipt number issued by the Cashier. The Supply Office verifies this and confirms the reservation.</p>
                <textarea id="requirements_notes" name="notes" rows="3" maxlength="2000" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Report requirements complete</button>
            </form>
        @endif
    @endif

    {{-- Supply Office: cancel an approval whose requirements were not met, releasing the schedule. --}}
    @if($isViewerSupplyOffice && $request->status === 'pending' && in_array($stage, \App\Models\FacilityRequest::OUTSIDER_HOLD_STAGES, true))
        <form method="POST" action="{{ route('supply-office.outsider.cancel-approval', $request) }}" class="mt-5 space-y-3 rounded-2xl border border-red-200 bg-red-50 p-4" data-swal-confirm data-swal-title="Cancel this approval?" data-swal-text="The schedule is released and the client and IGP are notified." data-swal-confirm-text="Cancel approval" data-swal-confirm-color="#b91c1c">
            @csrf
            <label for="cancel_approval_reason" class="block text-sm font-semibold text-red-900">Cancel approval and release the schedule</label>
            <textarea id="cancel_approval_reason" name="reason" rows="2" maxlength="1000" required placeholder="For example: requirements were not completed by the deadline." class="w-full rounded-xl border border-red-300 bg-white px-3 py-2 text-sm"></textarea>
            @error('reason')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Cancel approval</button>
        </form>
    @endif
</section>
