@extends('layouts.app')
@section('title', 'Request Details - ' . $request->control_number)

@php
    $currentUser = auth()->user();
    $dashboardRoute = route('requestor.index', ['tab' => 'requests']);
    if ($currentUser && $currentUser->isFacilityAdministrator()) {
        $dashboardRoute = route('supply-office.index');
    } elseif ($currentUser && $currentUser->isCustodian()) {
        $dashboardRoute = route('custodian.index');
    }

    $overallStatusTone = match($request->status) {
        'approved' => 'emerald',
        'rejected' => 'rose',
        'completed' => 'sky',
        default => 'amber',
    };

    $workflowSteps = [
        ['label' => 'Submitted', 'key' => 'submitted'],
        ['label' => 'Venue Review', 'key' => 'venue'],
        ['label' => 'Equipment Review', 'key' => 'equipment'],
        ['label' => 'Final Approval', 'key' => 'approval'],
        ['label' => 'Completed', 'key' => 'completed'],
    ];

    $currentWorkflowIndex = 0;
    if ($request->status === 'rejected') {
        $currentWorkflowIndex = 3;
    } elseif ($request->status === 'approved' || $request->status === 'completed') {
        $currentWorkflowIndex = 4;
    } elseif ($request->venue_status === 'approved' && $request->equipment_status === 'approved') {
        $currentWorkflowIndex = 3;
    } elseif ($request->venue_status === 'approved' || $request->equipment_status === 'approved') {
        $currentWorkflowIndex = 2;
    } elseif ($request->venue_status === 'rejected' || $request->equipment_status === 'rejected') {
        $currentWorkflowIndex = 1;
    } else {
        $currentWorkflowIndex = 1;
    }

    $workflowStageLabel = $workflowSteps[$currentWorkflowIndex]['label'] ?? 'Submitted';
    $requestDateLabel = $request->date_requested ? \Carbon\Carbon::parse($request->date_requested)->format('M j, Y') : 'N/A';
    $lastUpdatedLabel = $request->updated_at ? \Carbon\Carbon::parse($request->updated_at)->format('M j, Y \a\t g:i A') : null;
    $scheduledStart = $request->reservationSchedule?->start_datetime ?? \Carbon\Carbon::parse($request->start_date . ' ' . ($request->start_time ?? '00:00'));
    $scheduledEnd = $request->reservationSchedule?->end_datetime ?? \Carbon\Carbon::parse(($request->end_date ?? $request->start_date) . ' ' . ($request->end_time ?? $request->start_time ?? '00:00'));

    $approvalTone = 'amber';
    $approverTone = 'amber';
    $currentApprover = 'CHIC Custodian';
    $nextStep = 'Venue Review';
    $approvalMessage = 'Waiting for review';
    $approverMessage = 'Waiting for review';
    $approvalTimestamp = null;

    if ($request->status === 'approved') {
        $approvalMessage = 'Approved by Administrator';
        $approvalTone = 'emerald';
        $currentApprover = 'Administrator';
        $nextStep = null;
        $approvalTimestamp = $request->approved_date ? \Carbon\Carbon::parse($request->approved_date)->format('M j, Y \a\t g:i A') : null;
    } elseif ($request->status === 'rejected') {
        $approvalMessage = 'Declined by Administrator';
        $approvalTone = 'rose';
        $currentApprover = 'Administrator';
        $nextStep = null;
        $approvalTimestamp = $request->updated_at ? \Carbon\Carbon::parse($request->updated_at)->format('M j, Y \a\t g:i A') : null;
    } elseif ($request->venue_status === 'approved' && $request->equipment_status === 'approved') {
        $approvalMessage = 'Waiting for final approval';
        $approvalTone = 'amber';
        $currentApprover = 'Administrator';
        $nextStep = 'Final Approval';
    } elseif ($request->venue_status === 'approved') {
        $approvalMessage = 'Venue approved, awaiting equipment review';
        $approvalTone = 'amber';
        $currentApprover = 'Equipment Custodian';
        $nextStep = 'Equipment Review';
    } elseif ($request->equipment_status === 'approved') {
        $approvalMessage = 'Equipment approved, awaiting venue review';
        $approvalTone = 'amber';
        $currentApprover = 'Venue Custodian';
        $nextStep = 'Venue Review';
    } elseif ($request->venue_status === 'rejected' || $request->equipment_status === 'rejected') {
        $approvalMessage = 'Revision requested by custodian';
        $approvalTone = 'rose';
        $currentApprover = 'Custodian';
        $nextStep = 'Respond to revision';
    }

    $approverTone = $approvalTone;
    $approverMessage = $approvalMessage;
    $hasUrgentConflict = (bool) ($request->is_emergency && $request->venue_status === 'approved' && $request->status === 'pending');
@endphp

@section('content')

    {{-- Header --}}
    <div class="rounded-[32px] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ $dashboardRoute }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
                <button type="button" data-print-url="{{ route('request.print', $request->id) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" onclick="window.open(this.dataset.printUrl, '_blank', 'width=1200,height=900')">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-3a2 2 0 00-2-2h-2M7 17H5a2 2 0 01-2-2v-3a2 2 0 012-2h2m10 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v3m10 0H7"/></svg>
                    Print Request
                </button>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-600">Request tracking</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Request Details</h1>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">
                    {{ $request->control_number }}
                </span>
                @if($hasUrgentConflict)
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700 ring-1 ring-red-200">
                        🔴 URGENT CONFLICT
                    </span>
                @endif
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset
                    {{ $overallStatusTone === 'emerald' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : ($overallStatusTone === 'rose' ? 'bg-rose-100 text-rose-700 ring-rose-200' : ($overallStatusTone === 'sky' ? 'bg-sky-100 text-sky-700 ring-sky-200' : 'bg-amber-100 text-amber-700 ring-amber-200')) }}">
                    {{ ucfirst($request->status) }}
                </span>
            </div>
        </div>

        @if($hasUrgentConflict)
            <div class="mt-6 rounded-[24px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:p-5">
                <div class="font-semibold">This urgent Institute request overlaps an existing approved reservation.</div>
                <div class="mt-2">If approved, the existing reservation may need administrative rescheduling. The system will not automatically cancel or replace the original reservation.</div>
            </div>
        @endif

        <div class="mt-6 rounded-[24px] border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Workflow progress</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700">Current stage: {{ $workflowStageLabel }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    <span class="rounded-full bg-emerald-500 px-2.5 py-1 text-white">Completed</span>
                    <span class="rounded-full bg-amber-400 px-2.5 py-1 text-white">Current</span>
                    <span class="rounded-full bg-slate-300 px-2.5 py-1 text-white">Upcoming</span>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach($workflowSteps as $index => $step)
                    @php
                        $isCompleted = $index < $currentWorkflowIndex;
                        $isCurrent = $index === $currentWorkflowIndex;
                        $stepTone = $isCompleted ? 'emerald' : ($isCurrent ? 'amber' : 'slate');
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center shadow-sm">
                        <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full {{ $stepTone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : ($stepTone === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="mt-2 text-sm font-semibold text-slate-800">{{ $step['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Role-Based Actions --}}
    <div class="grid gap-4 mb-6">
        @if(auth()->check() && auth()->user()->isRequestee() && auth()->id() === $request->requested_by_id && ($request->status === 'needs_reschedule' || $request->venue_status === 'needs_reschedule' || $request->equipment_status === 'needs_reschedule'))
            <div class="bg-amber-50 border border-amber-200 rounded-3xl p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Reschedule Needed</h2>
                        <p class="text-sm text-slate-600">This request was moved to needs-reschedule status after an override. Update the scheduling details so it can move back through review.</p>
                    </div>
                    <a href="{{ route('requestor.edit', $request->id) }}" class="inline-flex items-center gap-2 rounded-2xl bg-amber-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-amber-700">
                        Reschedule Request
                    </a>
                </div>
            </div>
        @endif

        @if(auth()->check() && auth()->user()->isRequestee() && auth()->id() === $request->requested_by_id && $request->status === 'pending')
            <div class="bg-white border border-emerald-200 rounded-3xl p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Requestor Actions</h2>
                        <p class="text-sm text-slate-500">View the full form and cancel your pending request before it enters the final approval stage.</p>
                    </div>
                    <form method="POST" action="{{ route('request.cancel', $request->id) }}" class="flex-shrink-0" data-swal-confirm data-swal-title="Cancel this request?" data-swal-text="This action will remove the pending request from the workflow." data-swal-confirm-text="Yes, cancel it" data-swal-confirm-color="#dc2626">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-red-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-red-700">
                            Cancel Request
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if(auth()->check() && auth()->user()->isCustodian())
            @if(!$hasEndorsed)
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Custodian Verification</h2>
                                <p class="text-sm text-slate-500">Verify request details and forward the request for final approval, or ask for a revision if changes are needed.</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                Action Required
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <form method="POST" action="{{ route('request.custodian.verify', $request->id) }}" data-swal-confirm data-swal-title="Verify and endorse this request?" data-swal-text="This will forward the request for final approval." data-swal-confirm-text="Yes, endorse it" data-swal-confirm-color="#059669">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center rounded-2xl bg-emerald-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-700">
                                    Verify & Endorse
                                </button>
                            </form>

                            <form id="custodian-revision-form" method="POST" action="{{ route('request.custodian.revision', $request->id) }}">
                                @csrf
                                <input type="hidden" name="notes" id="custodian-revision-notes">
                                <button type="button" id="revision-action-button" onclick="requestRevision()"
                                        class="w-full inline-flex items-center justify-center rounded-2xl bg-orange-500 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-orange-600">
                                    Request Revision
                                </button>
                            </form>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @if(auth()->user()->isCustodianVenue())
                                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                    <p class="text-sm font-semibold text-slate-700">Requested Venue(s)</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @forelse($request->getVenueNames() as $venue)
                                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs">{{ $venue }}</span>
                                        @empty
                                            <p class="text-xs text-slate-500">No venue requested.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            @if(auth()->user()->isCustodianEquipment() && !empty($currentCustodianEquipment))
                                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                    <p class="text-sm font-semibold text-slate-700">Assigned Equipment</p>
                                    <div class="mt-3 space-y-2">
                                        @foreach($currentCustodianEquipment as $itemName => $qty)
                                            <div class="flex items-center justify-between rounded-xl bg-white border border-slate-200 px-3 py-2">
                                                <span class="text-sm text-slate-700">{{ $itemName }}</span>
                                                <span class="text-xs font-semibold text-slate-600">Qty {{ $qty }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Custodian Verification</h2>
                                <p class="text-sm text-slate-500">Your custodial endorsement has already been recorded.</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                Verification Complete
                            </span>
                        </div>

                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-700">Your endorsement status</p>
                            <p class="mt-2 text-sm text-emerald-700">
                                Completed
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if(auth()->check() && auth()->user()->isAdmin())
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                <div class="flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Administrator Review</h2>
                        @if($request->status === 'pending')
                            <p class="text-sm text-slate-500">Review custodial endorsements and complete the final approval or decline the request.</p>
                        @elseif($request->status === 'approved')
                            <p class="text-sm text-emerald-600 font-medium">✓ Request has been approved and finalized.</p>
                        @elseif($request->status === 'rejected')
                            <p class="text-sm text-red-600 font-medium">✗ This request has been declined.</p>
                        @endif
                    </div>

                    @if($request->status === 'pending')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <form method="POST" action="{{ route('request.supply.final-approval', $request->id) }}" class="approval-form">
                                @csrf
                                <button type="button" onclick="handleFinalApproval(event)"
                                        class="w-full inline-flex items-center justify-center rounded-2xl bg-emerald-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-700">
                                    Final Approval
                                </button>
                            </form>

                            <form method="POST" action="{{ route('request.supply.decline', $request->id) }}" class="decline-form">
                                @csrf
                                <button type="button" onclick="handleDecline(event)"
                                        class="w-full inline-flex items-center justify-center rounded-2xl bg-red-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-red-700">
                                    Decline
                                </button>
                            </form>
                        </div>
                    @elseif($request->status === 'approved')
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 mb-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-emerald-900">Approval Complete</p>
                                    <p class="text-xs text-emerald-700">This request was approved on {{ $request->approved_date ? \Carbon\Carbon::parse($request->approved_date)->format('M j, Y \a\t g:i A') : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <button type="button" onclick="generateApprovalSlip()"
                                class="w-full inline-flex items-center justify-center rounded-2xl bg-blue-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-blue-700">
                            Generate Approval Slip
                        </button>
                    @elseif($request->status === 'rejected')
                        <div class="rounded-2xl bg-red-50 border border-red-200 p-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l-2-2m0 0l-2-2m2 2l2-2m-2 2l-2 2"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-red-900">Request Declined</p>
                                    <p class="text-xs text-red-700">This request was declined and cannot be resubmitted.</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-700">Custodial Endorsement Summary</p>
                        <div class="mt-3 space-y-2">
                            @forelse($assignedCustodians as $custodian)
                                @php
                                    $status = $custodianStatuses[$custodian->id] ?? 'pending';
                                    $badge = match($status) {
                                        'approved' => 'bg-emerald-100 text-emerald-700',
                                        'revision_requested' => 'bg-orange-100 text-orange-700',
                                        'rejected' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <div class="flex items-center justify-between rounded-xl bg-white border border-slate-200 px-3 py-2">
                                    <span class="text-sm text-slate-700">{{ $custodian->name }}</span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">No custodial endorsement data available.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Overall Status</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ ucfirst($request->status) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Current Workflow Stage</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $workflowStageLabel }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Request Date</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $requestDateLabel }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Last Updated</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $lastUpdatedLabel ?? 'Not updated yet' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Request Summary
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Control Number</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->control_number }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Requestor</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->user?->name ?? $request->requester?->name ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $request->requester?->position ?? 'Position not provided' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Department</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->department }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Organization</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->requester?->office_or_organization ?: '—' }}</p>
                    </div>
                </div>
            </div>
            <div class="space-y-4 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Activity Name</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->name_of_activity }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Venue</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reservation Schedule</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $scheduledStart->setTimezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }} @if($scheduledEnd && $scheduledEnd->ne($scheduledStart)) — {{ $scheduledEnd->setTimezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }} @endif</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Expected Participants</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->expected_participants }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Activity Details
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Requested By</p>
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->requester?->name ?? '—' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $request->requester?->position ?: 'Position not provided' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Purpose</p>
                <p class="mt-2 text-sm text-slate-700">{{ $request->notes ?: 'No purpose details provided.' }}</p>
            </div>
            <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Special Instructions</p>
                <div class="mt-2 space-y-2 text-sm text-slate-700">
                    @if($request->venue_notes)
                        <p><span class="font-semibold text-slate-900">Venue Notes:</span> {{ $request->venue_notes }}</p>
                    @endif
                    @if($request->equipment_notes)
                        <p><span class="font-semibold text-slate-900">Equipment Notes:</span> {{ $request->equipment_notes }}</p>
                    @endif
                    @if($request->other_venue)
                        <p><span class="font-semibold text-slate-900">Other Venue:</span> {{ $request->other_venue }}</p>
                    @endif
                    @if($request->is_emergency && $request->emergency_justification)
                        <p><span class="font-semibold text-amber-700">Urgent Processing Reason:</span> {{ $request->emergency_justification }}</p>
                    @endif
                    @if(!$request->venue_notes && !$request->equipment_notes && !$request->other_venue && !($request->is_emergency && $request->emergency_justification))
                        <p>No special instructions provided.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Equipment
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @if(!empty($request->getEquipmentItems()))
                @foreach($request->getEquipmentItems() as $e)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <div class="rounded-full bg-violet-100 p-2 text-violet-700">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1h2a2 2 0 012 2v2h1a2 2 0 012 2v2a2 2 0 01-2 2h-1v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2H9a2 2 0 01-2-2v-2a2 2 0 012-2h1V7a2 2 0 012-2h2z"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ $e }}</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">Qty {{ !empty($request->getEquipmentQuantities()[$e]) ? $request->getEquipmentQuantities()[$e] : 1 }}</span>
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-sm text-slate-500">No equipment requested.</p>
            @endif
        </div>
    </div>

    @if($request->proposal_file)
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-2xl bg-blue-100 p-3 text-blue-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0013.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Proposal</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ $request->proposal_file }}</p>
                        <p class="mt-1 text-sm text-slate-500">Upload status available through the existing proposal actions.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('request.proposal', ['id' => $request->id]) }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Preview
                    </a>
                    <a href="{{ route('request.proposal.download', ['id' => $request->id]) }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Approval Information
        </div>
        <div class="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-start gap-3">
                <div class="rounded-2xl {{ $approverTone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : ($approverTone === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }} p-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Current approver</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $approverMessage }}</p>
                    <p class="mt-1 text-sm text-slate-600">The request will continue through the existing approval workflow without changing the backend process.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Activity History
        </div>
        <div class="mt-5 space-y-4">
            @forelse($request->histories()->orderByDesc('occurred_at')->get() as $history)
                <div class="flex gap-4 rounded-[24px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white {{ str_contains($history->action, 'approved') ? 'bg-emerald-500' : (str_contains($history->action, 'rejected') ? 'bg-rose-500' : (str_contains($history->action, 'returned') ? 'bg-sky-500' : 'bg-slate-400')) }}">
                        {{ strtoupper(substr($history->action, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-semibold text-slate-800 capitalize">{{ ucfirst(str_replace('_', ' ', $history->action)) }}</p>
                            <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($history->occurred_at)->format('M j, Y g:i A') }}</p>
                        </div>
                        @if($history->detail)
                            <p class="mt-1 text-sm text-slate-600">{{ $history->detail }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="mt-4 text-lg font-semibold text-slate-900">No activity history yet.</p>
                    <p class="mt-2 text-sm text-slate-600">This request has not recorded any workflow activity yet.</p>
                </div>
            @endforelse
        </div>
    </div>

</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('print') === '1') {
            window.print();
        }
    });

    async function requestRevision() {
        const revisionButton = document.getElementById('revision-action-button');
        const { isConfirmed, value: notes } = await Swal.fire({
            title: 'Request revision',
            text: 'Please enter the revision details for the requester:',
            input: 'textarea',
            inputPlaceholder: 'Describe the requested changes or missing information...',
            showCancelButton: true,
            confirmButtonText: 'Submit Revision',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false,
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Please enter revision details.';
                }
            }
        });

        if (!isConfirmed || !notes || !notes.trim()) {
            return;
        }

        if (revisionButton) {
            revisionButton.disabled = true;
            revisionButton.classList.add('opacity-80', 'cursor-not-allowed');
            revisionButton.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Returning...</span></span>';
        }

        document.getElementById('custodian-revision-notes').value = notes.trim();
        document.getElementById('custodian-revision-form').submit();
    }

    function generateApprovalSlip() {
        Swal.fire({
            title: 'Approval slip',
            text: 'Approval Slip generation is not yet implemented. This placeholder represents the print/export workflow for the finalized approval.',
            icon: 'info',
            confirmButtonColor: '#2563eb'
        });
    }

    async function handleFinalApproval(event) {
        event.preventDefault();
        const form = event.target.closest('.approval-form');
        const button = event.target;

        const result = await Swal.fire({
            title: 'Confirm Final Approval',
            text: 'Are you sure you want to grant final approval for this request? This will finalize the facility and equipment release.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve Request',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        });

        if (result.isConfirmed) {
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...';

            try {
                form.submit();

                // Show success after brief delay for form submission
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Request approved successfully!',
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        form.submit();
                    });
                }, 500);
            } catch (error) {
                button.disabled = false;
                button.innerHTML = 'Final Approval';
                await Swal.fire({
                    title: 'Error',
                    text: 'An error occurred: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#059669'
                });
            }
        }
    }

    async function handleDecline(event) {
        event.preventDefault();
        const form = event.target.closest('.decline-form');
        const button = event.target;

        const result = await Swal.fire({
            title: 'Decline Request',
            text: 'Are you sure you want to decline this request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Decline',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        });

        if (result.isConfirmed) {
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...';

            try {
                form.submit();

                // Show success after brief delay for form submission
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Request declined successfully!',
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        form.submit();
                    });
                }, 500);
            } catch (error) {
                button.disabled = false;
                button.innerHTML = 'Decline';
                await Swal.fire({
                    title: 'Error',
                    text: 'An error occurred: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#059669'
                });
            }
        }
    }
</script>

@endsection