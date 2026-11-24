{{-- Additional Part 2: Activity Proposal tracking (independent of the reservation status). --}}
@php
    $proposalService = app(\App\Services\ActivityProposals::class);
    $proposalSummary = $proposalService->summary($request);
    $proposalVersions = $proposalService->versions($request);
    $deadlineChanges = \App\Models\ActivityProposalDeadlineChange::query()->where('facility_request_id', $request->id)->with('changedBy')->latest()->get();
    $viewer = auth()->user();
    $isOwner = $viewer && (int) $viewer->id === (int) $request->requested_by_id;
    $isSupply = $viewer && $viewer->isSupplyOffice();
    $docStatus = $proposalSummary['document'];
    $canUpload = $isOwner && in_array($request->status, ['pending', 'approved', 'needs_reschedule'], true)
        && in_array($docStatus, ['not_submitted', 'draft_submitted', 'requires_correction'], true);
    $latest = $proposalSummary['latest'];
    $docTone = match ($docStatus) {
        'verified' => 'bg-emerald-100 text-emerald-800',
        'for_verification' => 'bg-sky-100 text-sky-800',
        'requires_correction' => 'bg-amber-100 text-amber-900',
        'draft_submitted' => 'bg-slate-100 text-slate-700',
        default => 'bg-rose-100 text-rose-800',
    };
    $complianceTone = match ($proposalSummary['compliance']) {
        'overdue' => 'bg-rose-100 text-rose-800',
        'within_deadline' => 'bg-emerald-100 text-emerald-800',
        default => 'bg-slate-100 text-slate-600',
    };
    $suggestedDeadline = $proposalService->suggestedDeadline($request);
    $field = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm';
    $followUps = $request->histories()->whereIn('action', ['proposal_followup', 'proposal_overdue'])->latest('occurred_at')->get();
@endphp
<section id="activity-proposal" style="margin-bottom: 24px !important;" class="scroll-mt-24 rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="activity-proposal-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="activity-proposal-title" class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Activity Proposal</h2>
            <p class="mt-1 text-xs text-slate-500">Separate from the reservation approval.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="rounded-full px-3 py-1 {{ $docTone }}" data-proposal-status="{{ $docStatus }}">{{ $proposalSummary['document_label'] }}</span>
            <span class="rounded-full px-3 py-1 {{ $complianceTone }}" data-proposal-compliance="{{ $proposalSummary['compliance'] }}">{{ $proposalSummary['compliance_label'] }}</span>
        </div>
    </div>

    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <div class="rounded-2xl bg-slate-50 p-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submission deadline</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $request->proposal_deadline ? $request->proposal_deadline->format('M j, Y') : 'Not set yet' }}</dd>
        </div>
        <div class="rounded-2xl bg-slate-50 p-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest version</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $latest ? 'Version ' . $latest->version_number . ' · ' . $latest->typeLabel() : 'None' }}</dd>
        </div>
        <div class="rounded-2xl bg-slate-50 p-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last submitted</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $latest?->submitted_at ? $latest->submitted_at->format('M j, Y g:i A') : '—' }}</dd>
        </div>
    </dl>

    @if($latest && $latest->remarks && in_array($latest->review_status, ['requires_correction', 'verified'], true))
        <div class="mt-3 rounded-2xl border {{ $latest->review_status === 'requires_correction' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950' }} p-3 text-sm">
            <p class="font-semibold">Supply Office remarks</p>
            <p class="mt-1 whitespace-pre-line">{{ $latest->remarks }}</p>
        </div>
    @endif

    @if($canUpload)
        <form method="POST" action="{{ route('request.activity-proposal.upload', $request->id) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 sm:grid-cols-2">
            @csrf
            <p class="sm:col-span-2 text-sm font-semibold text-emerald-950">
                {{ $docStatus === 'requires_correction' ? 'Upload the corrected Final Copy' : ($docStatus === 'draft_submitted' ? 'Upload the Final Copy' : 'Upload your activity proposal') }}
            </p>
            <label class="text-sm font-medium text-slate-700">File (PDF, JPG or PNG, up to 10 MB)
                <input type="file" name="proposal_file" accept=".pdf,.jpg,.jpeg,.png" required class="{{ $field }}">
                @error('proposal_file')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <fieldset class="text-sm">
                <legend class="font-medium text-slate-700">Submission type</legend>
                <div class="mt-1 flex flex-wrap gap-3">
                    @if($docStatus !== 'requires_correction')
                        <label class="flex items-center gap-2"><input type="radio" name="submission_type" value="draft" required> Draft</label>
                    @endif
                    <label class="flex items-center gap-2"><input type="radio" name="submission_type" value="final" required @checked($docStatus === 'requires_correction')> Final Copy</label>
                </div>
                @error('submission_type')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </fieldset>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Upload</button>
            </div>
        </form>
    @endif

    @if($isSupply)
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @if($latest && $latest->isFinal() && $latest->review_status === 'pending')
                <form method="POST" action="{{ route('request.activity-proposal.review', $request->id) }}" class="space-y-3 rounded-2xl border border-sky-200 bg-sky-50/50 p-4">
                    @csrf
                    <p class="text-sm font-semibold text-sky-950">Verify Final Copy (version {{ $latest->version_number }})</p>
                    <label class="block text-sm font-medium text-slate-700">Remarks <span class="font-normal text-slate-500">(required for corrections)</span>
                        <textarea name="remarks" rows="2" maxlength="2000" class="{{ $field }}">{{ old('remarks') }}</textarea>
                        @error('remarks')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Correction deadline <span class="font-normal text-slate-500">(optional)</span>
                        <input type="date" name="correction_deadline" min="{{ now()->toDateString() }}" class="{{ $field }}">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="result" value="verified" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark Verified</button>
                        <button type="submit" name="result" value="requires_correction" class="rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">Requires Correction</button>
                    </div>
                </form>
            @elseif($latest && ! $latest->isFinal())
                <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">The latest version is a draft. Only a Final Copy can be verified.</p>
            @endif
            @if($docStatus !== 'verified')
                <form method="POST" action="{{ route('request.activity-proposal.deadline', $request->id) }}" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                    @csrf
                    <p class="text-sm font-semibold text-slate-900">{{ $request->proposal_deadline ? 'Change or extend the deadline' : 'Set the submission deadline' }}</p>
                    <label class="block text-sm font-medium text-slate-700">Deadline
                        <input type="date" name="deadline" required min="{{ now()->toDateString() }}" value="{{ old('deadline', $request->proposal_deadline?->toDateString() ?? $suggestedDeadline?->toDateString()) }}" class="{{ $field }}">
                        @if(! $request->proposal_deadline && $suggestedDeadline)<span class="mt-1 block text-xs text-slate-500">Suggested: 3 working days before the activity.</span>@endif
                        @error('deadline')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    @if($request->proposal_deadline)
                        <label class="block text-sm font-medium text-slate-700">Reason for the change
                            <input name="reason" required maxlength="1000" value="{{ old('reason') }}" class="{{ $field }}">
                            @error('reason')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Save Deadline</button>
                </form>
            @endif
            @if($proposalSummary['compliance'] === 'overdue' || $docStatus === 'requires_correction')
                <form method="POST" action="{{ route('request.activity-proposal.follow-up', $request->id) }}" class="space-y-3 rounded-2xl border border-rose-200 bg-rose-50/40 p-4 lg:col-span-2">
                    @csrf
                    <p class="text-sm font-semibold text-rose-950">Follow-up note</p>
                    <p class="text-xs text-rose-900">Contact: {{ $request->requester?->name }} · {{ $request->requester?->email ?? $request->requester?->username }}{{ $request->requester?->contact_number ? ' · ' . $request->requester->contact_number : '' }}. The reservation is not changed automatically.</p>
                    <textarea name="remarks" rows="2" required minlength="3" maxlength="2000" class="{{ $field }}" placeholder="e.g. Called the requestor; final copy promised by Friday"></textarea>
                    <button type="submit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Record Follow-up</button>
                </form>
            @endif
        </div>
    @endif

    @if($proposalVersions->isNotEmpty())
        <div class="mt-5">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submission history</h3>
            <ol class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                @foreach($proposalVersions as $version)
                    <li class="flex flex-col gap-1 p-3 text-sm sm:flex-row sm:items-center sm:justify-between" data-proposal-version="{{ $version->version_number }}">
                        <div>
                            <p class="font-semibold text-slate-900">Version {{ $version->version_number }} · {{ $version->typeLabel() }} <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $version->reviewLabel() }}</span></p>
                            <p class="text-xs text-slate-500">
                                {{ $version->submitted_at?->format('M j, Y g:i A') }} by {{ $version->uploadedBy?->name ?? 'Requestor' }}
                                @if($version->reviewed_at) · reviewed {{ $version->reviewed_at->format('M j, Y g:i A') }} by {{ $version->reviewedBy?->name }} @endif
                                @if($version->imported) · uploaded before version tracking @endif
                            </p>
                            @if($version->remarks)<p class="mt-1 text-xs text-slate-600">Remarks: {{ $version->remarks }}</p>@endif
                        </div>
                        <a href="{{ route('request.activity-proposal.file', ['id' => $request->id, 'version' => $version->id]) }}" target="_blank" rel="noopener" class="shrink-0 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if($deadlineChanges->isNotEmpty() || $followUps->isNotEmpty())
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-500">Deadline and follow-up history</summary>
            <ul class="mt-2 space-y-1 text-xs text-slate-600">
                @foreach($deadlineChanges as $change)
                    <li>{{ $change->created_at->format('M j, Y g:i A') }} — {{ $change->previous_deadline ? 'Changed from ' . $change->previous_deadline->format('M j, Y') . ' to ' : 'Set to ' }}{{ $change->new_deadline->format('M j, Y') }}{{ $change->changedBy ? ' by ' . $change->changedBy->name : ' (default)' }}{{ $change->reason ? '. Reason: ' . $change->reason : '' }}</li>
                @endforeach
                @if($isSupply)
                    @foreach($followUps as $note)
                        <li>{{ \Carbon\Carbon::parse($note->occurred_at)->format('M j, Y g:i A') }} — {{ $note->detail }}</li>
                    @endforeach
                @endif
            </ul>
        </details>
    @endif
</section>
