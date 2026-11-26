{{--
    Part 2: Capacity Exception Requested. The Venue Custodian of each venue decides; the
    requestor answers a "not approved" decision (choose another venue, or accept the
    custodian's suggestion); everyone else sees the status. Vars: $request.
--}}
@php
    $capacityService = app(\App\Services\CapacityExceptions::class);
    $viewer = auth()->user();
    $exceptionVenues = $request->capacityExceptionVenues();
    $decidable = $viewer ? $capacityService->decidableVenuesFor($request, $viewer) : [];
    $isOwner = $viewer && (int) $request->requested_by_id === (int) $viewer->id;
    $field = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm';
@endphp
@if($exceptionVenues !== [] || collect((array) ($request->capacity_exception_decisions ?? []))->contains(fn ($d) => ($d['decision'] ?? null) === 'not_approved' && ($d['requestor_response'] ?? null) === null))
<section id="capacity-exception" class="mb-6 scroll-mt-24 rounded-3xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6" data-capacity-exception>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-semibold text-slate-900">Capacity Exception Requested</h2>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match ($request->capacity_exception_status) { 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-rose-100 text-rose-800', default => 'bg-amber-100 text-amber-800' } }}">
            {{ match ($request->capacity_exception_status) { 'approved' => 'Approved by venue custodian', 'rejected' => 'Not approved', default => 'Waiting for venue custodian' } }}
        </span>
    </div>
    <p class="mt-1 text-sm text-slate-600">The venue custodian decides whether the venue can hold more participants than its registered capacity. This is not a reservation approval.</p>

    <div class="mt-4 space-y-4">
        @foreach((array) ($request->capacity_exception_decisions ?? []) + array_fill_keys(array_keys($exceptionVenues), null) as $venueName => $ignored)
            @php
                $venueName = (string) $venueName;
                $decision = $request->capacityDecision($venueName);
                $inRequest = array_key_exists($venueName, $exceptionVenues);
                if (! $inRequest && ! (($decision['decision'] ?? null) === 'not_approved' && ($decision['requestor_response'] ?? null) === null)) {
                    continue;
                }
                $participants = $decision['participants'] ?? $request->participantsForVenue($venueName);
                $capacity = $decision['capacity'] ?? \App\Models\Venue::where('name', $venueName)->value('capacity');
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-capacity-venue="{{ $venueName }}">
                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-slate-500">Venue:</dt> <dd class="inline font-semibold text-slate-900">{{ $venueName }}</dd></div>
                    <div><dt class="inline text-slate-500">Requested participants:</dt> <dd class="inline font-semibold text-slate-900">{{ $participants }}</dd></div>
                    <div><dt class="inline text-slate-500">Maximum capacity:</dt> <dd class="inline font-semibold text-slate-900">{{ $capacity ?? 'Not set' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="inline text-slate-500">Requestor's reason:</dt> <dd class="inline text-slate-800">{{ $exceptionVenues[$venueName] ?? ((array) ($request->capacity_exceptions ?? []))[$venueName] ?? '—' }}</dd></div>
                </dl>

                @if($decision)
                    <div class="mt-3 rounded-xl border px-3 py-2 text-sm {{ $decision['decision'] === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' }}">
                        <p class="font-semibold">
                            {{ $decision['decision'] === 'approved' ? 'Capacity exception approved' : 'Capacity exception not approved' }}
                            by {{ $decision['by_name'] ?? 'the venue custodian' }} on {{ \Carbon\Carbon::parse($decision['at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                        </p>
                        @if($decision['remarks'] ?? null)<p class="mt-1">Remarks: {{ $decision['remarks'] }}</p>@endif
                        @if($decision['reason'] ?? null)<p class="mt-1">Reason: {{ $decision['reason'] }}</p>@endif
                        @if($decision['suggested_venue'] ?? null)
                            <p class="mt-1">Suggested alternative venue: <span class="font-semibold">{{ $decision['suggested_venue'] }}</span>. {{ $decision['recommendation'] }}</p>
                        @endif
                        @if(($decision['requestor_response'] ?? null) === 'accepted')
                            <p class="mt-1 font-medium">The requestor accepted the suggested venue.</p>
                        @elseif(($decision['requestor_response'] ?? null) === 'chose_other')
                            <p class="mt-1 font-medium">The requestor chose another venue.</p>
                        @endif
                    </div>

                    {{-- Requestor: answer a "not approved" decision. --}}
                    @if($isOwner && $decision['decision'] === 'not_approved' && ($decision['requestor_response'] ?? null) === null && $request->status === 'pending')
                        <div class="mt-3 rounded-xl border border-amber-200 bg-white p-3 text-sm" data-capacity-requestor-actions>
                            <p class="font-semibold text-slate-900">Choose how to continue</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if($decision['suggested_venue'] ?? null)
                                    <form method="POST" action="{{ route('request.capacity-exception.accept', $request->id) }}" data-swal-confirm data-swal-title="Use {{ $decision['suggested_venue'] }} instead?" data-swal-text="{{ $venueName }} will be replaced by {{ $decision['suggested_venue'] }}. Its custodian will verify it.">
                                        @csrf
                                        <input type="hidden" name="venue" value="{{ $venueName }}">
                                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Accept {{ $decision['suggested_venue'] }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('requestor.edit', $request->id) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Choose another venue</a>
                            </div>
                            @php $fits = $capacityService->alternativeVenues($request, $venueName); @endphp
                            @if($fits !== [])
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Available venues that fit {{ $participants }} participants at this time</p>
                                <ul class="mt-1 flex flex-wrap gap-2">
                                    @foreach($fits as $fit)
                                        <li class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $fit['name'] }}{{ $fit['capacity'] ? ' · ' . $fit['capacity'] : '' }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-3 text-xs text-slate-600">No other venue that fits is free at this time. You can change the schedule or participants in the request.</p>
                            @endif
                        </div>
                    @endif
                @elseif(in_array($venueName, $decidable, true))
                    {{-- Venue Custodian: the decision for their own venue. --}}
                    @php $alternatives = $capacityService->alternativeVenues($request, $venueName); $key = md5($venueName); @endphp
                    <div class="mt-3 grid gap-3 lg:grid-cols-2" data-capacity-decision-forms>
                        <form method="POST" action="{{ route('request.capacity-exception.decide', $request->id) }}" class="space-y-2 rounded-xl border border-emerald-200 bg-white p-3">
                            @csrf
                            <input type="hidden" name="venue" value="{{ $venueName }}">
                            <input type="hidden" name="decision" value="approved">
                            <p class="text-sm font-semibold text-emerald-900">Approve Capacity Exception</p>
                            <p class="text-xs text-slate-600">The venue can hold {{ $participants }} participants despite its registered capacity.</p>
                            <label class="block text-xs font-medium text-slate-700">Remarks (optional)
                                <textarea name="remarks" rows="2" maxlength="1000" class="{{ $field }}"></textarea>
                            </label>
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve Capacity Exception</button>
                        </form>
                        <form method="POST" action="{{ route('request.capacity-exception.decide', $request->id) }}" class="space-y-2 rounded-xl border border-rose-200 bg-white p-3" data-capacity-reject-form>
                            @csrf
                            <input type="hidden" name="venue" value="{{ $venueName }}">
                            <input type="hidden" name="decision" value="not_approved">
                            <p class="text-sm font-semibold text-rose-900">Do Not Approve Capacity Exception</p>
                            <label class="block text-xs font-medium text-slate-700">Reason (shown to the requestor) <span class="text-red-600">*</span>
                                <textarea name="reason" rows="2" required minlength="5" maxlength="1000" class="{{ $field }}" placeholder="Why the venue cannot hold this number of participants"></textarea>
                            </label>
                            <fieldset class="space-y-1 text-sm text-slate-700">
                                <legend class="text-xs font-medium text-slate-700">Next step <span class="text-red-600">*</span></legend>
                                <label class="flex items-center gap-2"><input type="radio" name="next_step" value="requestor_choose" checked data-next-step> Let the requestor choose another venue</label>
                                <label class="flex items-center gap-2 {{ $alternatives === [] ? 'opacity-50' : '' }}"><input type="radio" name="next_step" value="suggest_venue" @disabled($alternatives === []) data-next-step> Suggest an alternative venue</label>
                                @if($alternatives === [])<p class="text-xs text-slate-500">No other venue that fits is free at this time.</p>@endif
                            </fieldset>
                            <div class="hidden space-y-2" data-suggest-fields>
                                <label class="block text-xs font-medium text-slate-700">Alternative venue
                                    <select name="suggested_venue" class="{{ $field }}" disabled>
                                        <option value="">Choose a venue</option>
                                        @foreach($alternatives as $alternative)
                                            <option value="{{ $alternative['name'] }}">{{ $alternative['name'] }}{{ $alternative['capacity'] ? ' (capacity ' . $alternative['capacity'] . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-xs font-medium text-slate-700">Recommendation for the requestor
                                    <textarea name="recommendation" rows="2" maxlength="1000" class="{{ $field }}" disabled></textarea>
                                </label>
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Do Not Approve</button>
                        </form>
                    </div>
                @else
                    <p class="mt-3 text-sm text-amber-800">Waiting for the custodian of {{ $venueName }} to decide.</p>
                @endif
            </article>
        @endforeach
    </div>
</section>
@once
    <script>
        document.querySelectorAll('[data-capacity-reject-form]').forEach((form) => {
            const fields = form.querySelector('[data-suggest-fields]');
            const sync = () => {
                const suggest = form.querySelector('[data-next-step]:checked')?.value === 'suggest_venue';
                fields.classList.toggle('hidden', !suggest);
                fields.querySelectorAll('select, textarea').forEach((input) => { input.disabled = !suggest; input.required = suggest; });
            };
            form.querySelectorAll('[data-next-step]').forEach((radio) => radio.addEventListener('change', sync));
            sync();
        });
    </script>
@endonce
@endif
