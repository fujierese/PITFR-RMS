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
        'cancelled' => 'slate',
        'needs_reschedule' => 'amber',
        default => 'amber',
    };

    $requestStatusLabel = match($request->status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'needs_reschedule' => 'Needs Reschedule',
        default => ucfirst(str_replace('_', ' ', (string) ($request->status ?? 'pending'))),
    };
    $reservationLifecycleState = $request->getReservationLifecycleState();
    if (in_array($request->status, ['approved', 'completed'], true)) {
        $requestStatusLabel = $request->getReservationLifecycleLabel();
        $overallStatusTone = match ($reservationLifecycleState) {
            'equipment_return_pending' => 'amber',
            'ongoing', 'completed' => 'sky',
            default => 'emerald',
        };
    }
    $reservationLifecycleStepLabel = in_array($request->status, ['approved', 'completed'], true)
        ? $request->getReservationLifecycleLabel()
        : 'Reservation';

    $venueNames = $request->getVenueNames();
    $equipmentItems = $request->getEquipmentItems();
    $venueIncludedEquipmentNames = [];
    foreach ($venueNames as $venueName) {
        $venueIncludedEquipmentNames = array_merge(
            $venueIncludedEquipmentNames,
            \App\Services\VenueEquipmentPolicy::getDefaultEquipment($venueName)
        );
    }
    $normalizedVenueIncludedEquipmentNames = array_map(
        static fn (string $name): string => mb_strtolower(trim($name)),
        array_unique($venueIncludedEquipmentNames)
    );
    $additionalEquipmentItems = array_values(array_filter(
        $equipmentItems,
        static fn (string $name): bool => !in_array(
            mb_strtolower(trim($name)),
            $normalizedVenueIncludedEquipmentNames,
            true
        )
    ));
    $hasAdditionalEquipment = $additionalEquipmentItems !== [];
    $additionalEquipmentQuantities = $request->getAdditionalEquipmentQuantities();
    $returnOutstandingQuantities = $request->getEquipmentReturnOutstandingQuantities();
    $returnFormItems = [];
    if ($currentUser && $currentUser->isCustodianEquipment()) {
        $custodianId = (string) $currentUser->id;
        $custodianRecords = $request->equipment_returned_items ?? [];
        $custodianReturnRecord = $custodianRecords[$custodianId] ?? [];
        foreach ($request->getAssignedEquipmentForCustodian((int) $currentUser->id) as $itemName => $assignedQuantity) {
            if (! array_key_exists($itemName, $additionalEquipmentQuantities)) {
                continue;
            }

            $otherAccounted = 0;
            foreach ($custodianRecords as $otherCustodianId => $record) {
                if ((string) $otherCustodianId === $custodianId || ! is_array($record)) {
                    continue;
                }
                $otherAccounted += (int) ($record['equipment'][$itemName] ?? 0)
                    + (int) ($record['missing'][$itemName] ?? 0);
            }

            $returned = (int) ($custodianReturnRecord['equipment'][$itemName] ?? 0);
            $damaged = (int) ($custodianReturnRecord['damaged'][$itemName] ?? 0);
            $missing = (int) ($custodianReturnRecord['missing'][$itemName] ?? 0);
            $maximumAccountable = max(0, (int) $assignedQuantity - $otherAccounted);
            if (max(0, $maximumAccountable - $returned - $missing) === 0) {
                continue;
            }

            $returnFormItems[$itemName] = [
                'returned' => $returned,
                'damaged' => $damaged,
                'missing' => $missing,
                'max_accountable' => $maximumAccountable,
            ];
        }
    }
    $canRecordEquipmentReturn = $currentUser
        && $currentUser->isCustodianEquipment()
        && $request->status === 'approved'
        && $request->equipment_status === 'approved'
        && $reservationLifecycleState === 'equipment_return_pending';
    $canVenueCustodianReschedule = $currentUser
        && $currentUser->isCustodianVenue()
        && $request->status === 'pending'
        && in_array($request->venue_status, ['pending', 'needs_reschedule'], true)
        && in_array($request->supply_office_review_status, ['pending', 'unavailable'], true)
        && !$pendingRevision;
    $assignedVenueNames = $currentUser && $currentUser->isCustodianVenue()
        ? $currentUser->venues()->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))
        : collect();
    $canConfirmVenueAvailability = $currentUser
        && $currentUser->isCustodianVenue()
        && $request->status === 'pending'
        && $request->venue_status === 'pending'
        && $request->supply_office_review_status === 'pending'
        && collect($request->getVenueNames())
            ->map(fn ($name) => mb_strtolower(trim($name)))
            ->intersect($assignedVenueNames)
            ->isNotEmpty();
    $canApproveAssignedEquipment = $currentUser
        && $currentUser->isCustodianEquipment()
        && $request->status === 'pending'
        && $request->supply_office_review_status === 'confirmed'
        && $request->getCustodianEquipmentStatus((int) $currentUser->id) === 'pending'
        && $request->getAssignedEquipmentForCustodian((int) $currentUser->id) !== [];
    $supplyOfficeReviewStatus = $request->supply_office_review_status;
    $supplyOfficeReviewComplete = $supplyOfficeReviewStatus === 'confirmed'
        || ($supplyOfficeReviewStatus === null
            && ($request->venue_status !== 'pending' || $request->equipment_status !== 'pending'));

    $workflowSteps = [
        ['label' => 'Submitted', 'key' => 'submitted'],
        ['label' => 'Venue Availability Confirmation', 'key' => 'venue'],
        ['label' => 'Supply Office Availability Confirmation', 'key' => 'supply_office'],
    ];
    if ($hasAdditionalEquipment) {
        $workflowSteps[] = ['label' => 'Equipment Review', 'key' => 'equipment'];
    }
    $workflowSteps[] = ['label' => 'Final Approval', 'key' => 'approval'];
    $workflowSteps[] = ['label' => $reservationLifecycleStepLabel, 'key' => 'lifecycle'];

    $workflowStatuses = [
        'submitted' => 'approved',
        'supply_office' => $supplyOfficeReviewStatus === 'confirmed'
            ? 'approved'
            : ($supplyOfficeReviewStatus === 'unavailable' ? 'rejected' : 'pending'),
        'venue' => $request->venue_status === 'needs_reschedule'
            ? 'rejected'
            : $request->venue_status,
        'equipment' => $request->equipment_status,
        'approval' => $request->status === 'approved' || $request->status === 'completed'
            ? 'approved'
            : ($request->status === 'rejected' && $request->venue_status !== 'rejected' && $request->equipment_status !== 'rejected' ? 'rejected' : 'pending'),
        'lifecycle' => $reservationLifecycleState === 'completed' ? 'approved' : 'pending',
    ];

    $currentWorkflowKey = match (true) {
        $request->status === 'cancelled' => null,
        in_array($request->status, ['approved', 'completed'], true) => 'lifecycle',
        $request->status === 'rejected' && $request->venue_status === 'rejected' => 'venue',
        $request->status === 'rejected' && $hasAdditionalEquipment && $request->equipment_status === 'rejected' => 'equipment',
        $request->status === 'rejected' => 'approval',
        $request->venue_status !== 'approved' => 'venue',
        !$supplyOfficeReviewComplete => 'supply_office',
        $hasAdditionalEquipment && $request->equipment_status !== 'approved' => 'equipment',
        default => 'approval',
    };
    $workflowStageLabel = match (true) {
        $request->status === 'cancelled' => 'Cancelled',
        $request->venue_status === 'needs_reschedule' => 'Venue Unavailable — Supply Office Coordination',
        $request->status === 'needs_reschedule' && $request->supply_office_review_status === 'unavailable' => 'Availability Unavailable — Reschedule Needed',
        $request->status === 'rejected' && $request->venue_status === 'rejected' => 'Rejected at Venue Review',
        $request->status === 'rejected' && $hasAdditionalEquipment && $request->equipment_status === 'rejected' => 'Rejected at Equipment Review',
        $request->status === 'rejected' => 'Rejected at Final Approval',
        $request->status === 'approved' || $request->status === 'completed' => $request->getReservationLifecycleLabel(),
        default => collect($workflowSteps)->firstWhere('key', $currentWorkflowKey)['label'] ?? 'Submitted',
    };
    $requestDateLabel = $request->date_requested ? \Carbon\Carbon::parse($request->date_requested)->format('M j, Y') : 'N/A';
    $lastUpdatedLabel = $request->updated_at ? \Carbon\Carbon::parse($request->updated_at)->format('M j, Y \a\t g:i A') : null;
    $proposalFilename = $request->activity_proposal_file ?: $request->proposal_file;
    $isOutsiderRequest = $request->requester?->isOutsider() ?? false;
    $requestDocuments = [];
    if ($proposalFilename) {
        $requestDocuments[] = [
            'type' => 'proposal',
            'label' => 'Activity Proposal',
            'filename' => $proposalFilename,
            'is_pdf' => strtolower(pathinfo((string) $proposalFilename, PATHINFO_EXTENSION)) === 'pdf',
        ];
    }
    if ($request->igp_receipt_file) {
        $requestDocuments[] = [
            'type' => 'receipt',
            'label' => 'Payment Receipt',
            'filename' => $request->igp_receipt_file,
            'is_pdf' => strtolower(pathinfo((string) $request->igp_receipt_file, PATHINFO_EXTENSION)) === 'pdf',
        ];
    }
    $venueScheduleSummaries = $request->getVenueScheduleSummaries();
    $venueIncludedEquipment = [];
    foreach ($venueNames as $venueName) {
        $defaultEquipment = \App\Services\VenueEquipmentPolicy::getDefaultEquipment($venueName);
        if ($defaultEquipment !== []) {
            $venueIncludedEquipment[$venueName] = $defaultEquipment;
        }
    }
    $equipmentQuantities = $request->getEquipmentQuantities();
    $requestPriorityLabel = match (strtolower((string) ($request->priority ?? 'regular'))) {
        'institutional' => 'Institutional',
        'regular' => 'Regular',
        default => ucfirst(str_replace('_', ' ', (string) ($request->priority ?? 'regular'))),
    };
    $approvalTone = 'amber';
    $approverTone = 'amber';
    $currentApprover = 'Venue Custodian';
    $nextStep = 'Venue Review';
    $approvalTitle = 'Pending Venue Custodian Approval';
    $approvalBody = 'The venue custodian is still reviewing this reservation request.';
    $approvalTimestamp = null;
    $approvalActorName = null;
    $approvalActorRole = null;
    $approvalStatusLabel = 'Reservation approval status';

    $formatApprovalDate = function ($timestamp) {
        return $timestamp ? \Carbon\Carbon::parse($timestamp)->format('M j, Y, g:i A') : null;
    };

    if ($request->status === 'cancelled') {
        $approvalTitle = 'Reservation cancelled';
        $approvalBody = 'This reservation request was cancelled';
        $cancellation = $request->histories()->where('action', 'cancelled')->latest('occurred_at')->first();
        $cancellationActor = $cancellation?->user?->name ?? 'the requestor';
        $cancellationDate = $formatApprovalDate($cancellation?->occurred_at);
        $approvalBody .= ' by ' . $cancellationActor
            . ($cancellationDate ? ' on ' . $cancellationDate : '')
            . '. No further approval is required.';
        $approvalTone = 'slate';
        $approvalStatusLabel = 'Reservation status';
        $currentApprover = null;
        $nextStep = null;
    } elseif ($request->status === 'approved') {
        $approvalTitle = 'Reservation approved';
        $approvalTone = 'emerald';
        $currentApprover = 'Supply Office';
        $nextStep = null;
        $latestApproval = $request->histories()->whereIn('action', ['final_approved', 'approved'])->latest('occurred_at')->first();
        $approvalActor = $latestApproval?->user ?? $request->approvedBy()->first();
        $approvalActorName = $approvalActor?->name ?? ($request->approved_by ?: 'Supply Office');
        $approvalActorRole = $approvalActor?->role_label ?? 'Supply Office';
        $approvalTimestamp = $latestApproval?->occurred_at ?? $request->approved_date;
        $approvalBody = 'This reservation request was approved by ' . $approvalActorName . ', ' . $approvalActorRole . ', on ' . $formatApprovalDate($approvalTimestamp) . '.';
    } elseif ($request->status === 'rejected') {
        $approvalTitle = 'Reservation rejected';
        $approvalTone = 'rose';
        $nextStep = null;
        $latestRejection = $request->histories()
            ->whereIn('action', ['venue_status_rejected', 'equipment_status_rejected', 'final_rejected', 'rejected'])
            ->latest('occurred_at')
            ->first();
        $rejectedStage = match ($latestRejection?->action) {
            'venue_status_rejected' => 'venue',
            'equipment_status_rejected' => 'equipment',
            'final_rejected', 'rejected' => 'final',
            default => $request->venue_status === 'rejected'
                ? 'venue'
                : ($request->equipment_status === 'rejected' ? 'equipment' : 'final'),
        };
        $approvalActor = $latestRejection?->user;
        $approvalActorName = $approvalActor?->name ?? match ($rejectedStage) {
            'venue' => 'Venue Custodian',
            'equipment' => 'Equipment Custodian',
            default => 'Supply Office',
        };
        $approvalActorRole = $approvalActor?->role_label ?? match ($rejectedStage) {
            'venue' => 'Venue Custodian',
            'equipment' => 'Equipment Custodian',
            default => 'Supply Office',
        };
        $currentApprover = match ($rejectedStage) {
            'venue' => 'Venue Custodian',
            'equipment' => 'Equipment Custodian',
            default => 'Supply Office',
        };
        $approvalTimestamp = $latestRejection?->occurred_at ?? $request->updated_at;
        $rejectionStageLabel = match ($rejectedStage) {
            'venue' => 'Venue Review',
            'equipment' => 'Equipment Review',
            default => 'Final Approval',
        };
        $approvalTitle = 'Reservation rejected at ' . $rejectionStageLabel;
        $approvalBody = 'This reservation request was rejected by ' . $approvalActorName
            . ' (' . $approvalActorRole . ') on ' . $formatApprovalDate($approvalTimestamp) . '.';
    } elseif ($request->venue_status === 'needs_reschedule') {
        $approvalTitle = 'Venue unavailable — schedule coordination needed';
        $approvalBody = 'The venue custodian could not confirm the requested schedule. The Supply Office will coordinate an available alternative with you. No custodian approvals have started.';
        $approvalTone = 'rose';
        $currentApprover = 'Supply Office';
        $nextStep = 'Schedule Coordination';
    } elseif (
        $request->status === 'pending'
        && $request->venue_status === 'pending'
    ) {
        $approvalTitle = 'Pending Venue Availability Confirmation';
        $approvalBody = 'The venue custodian is checking whether the selected venue is available for your requested date and time.';
        $approvalTone = 'amber';
        $currentApprover = 'Venue Custodian';
        $nextStep = 'Venue Availability Check';
    } elseif (
        $request->status === 'pending'
        && $request->supply_office_review_status === 'pending'
    ) {
        $approvalTitle = 'Pending Supply Office Availability Confirmation';
        $approvalBody = !empty($additionalEquipmentItems)
            ? 'The venue custodian confirmed the venue. The Supply Office is rechecking the venue and requested equipment before custodian approvals begin.'
            : 'The venue custodian confirmed the venue. The Supply Office is performing the final availability check before final approval.';
        $approvalTone = 'amber';
        $currentApprover = 'Supply Office';
        $nextStep = 'Supply Office Availability Check';
    } elseif (
        $request->status === 'pending'
        && $request->supply_office_review_status === 'unavailable'
    ) {
        $approvalTitle = 'Schedule change needed';
        $approvalBody = $request->supply_office_review_notes ?: 'The Supply Office found the requested schedule unavailable. Coordinate a new schedule before custodians can review.';
        $approvalTone = 'rose';
        $currentApprover = 'Supply Office';
        $nextStep = 'Schedule Coordination';
    } elseif ($request->venue_status === 'approved' && $request->equipment_status === 'approved') {
        $approvalTitle = 'Pending Final Approval';
        $approvalBody = 'The request has passed venue and equipment review and is awaiting final approval.';
        $approvalTone = 'amber';
        $currentApprover = 'Supply Office';
        $nextStep = 'Final Approval';
    } elseif ($request->venue_status === 'approved' && $request->supply_office_review_status === 'confirmed') {
        $approvalTitle = $hasAdditionalEquipment ? 'Pending Equipment Custodian Approval' : 'Pending Final Approval';
        $approvalBody = $hasAdditionalEquipment
            ? 'The venue custodian and Supply Office confirmed availability. The assigned equipment custodian is now reviewing this request.'
            : 'Venue availability is confirmed and the request is awaiting final approval.';
        $approvalTone = 'amber';
        $currentApprover = $hasAdditionalEquipment ? 'Equipment Custodian' : 'Supply Office';
        $nextStep = $hasAdditionalEquipment ? 'Equipment Review' : 'Final Approval';
    } elseif ($request->equipment_status === 'approved') {
        $approvalTitle = 'Pending Venue Custodian Approval';
        $approvalBody = 'The equipment has been approved and the venue custodian is still reviewing this request.';
        $approvalTone = 'amber';
        $currentApprover = 'Venue Custodian';
        $nextStep = 'Venue Review';
    } elseif ($request->venue_status === 'rejected' || $request->equipment_status === 'rejected') {
        $approvalTitle = 'Reservation changes requested';
        $approvalBody = 'A custodian requested changes before this reservation can proceed.';
        $approvalTone = 'rose';
        $currentApprover = 'Custodian';
        $nextStep = 'Respond to revision';
    }

    $approverTone = $approvalTone;
    $hasUrgentConflict = (bool) ($request->is_emergency && $request->venue_status === 'approved' && $request->status === 'pending');
    $pendingChangeRequest = $request->requestChangeRequests()->where('status', 'pending')->latest()->first();
    $hasCustodianApproval = $request->status === 'pending' && ($request->venue_status === 'approved' || $request->equipment_status === 'approved');
    $pendingCustodianNames = $request->supply_office_review_status === 'confirmed'
        ? collect($custodialEndorsements ?? [])
            ->where('status', 'pending')
            ->pluck('name')
            ->unique()
            ->implode(', ')
        : '';
@endphp

@section('content')

<div class="request-details-stack">

    {{-- Header --}}
    <div style="margin-bottom: 24px !important;" class="rounded-[32px] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ $dashboardRoute }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
                @can('print', $request)
                    <button type="button" data-print-url="{{ route('request.print', $request->id) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" onclick="window.open(this.dataset.printUrl, '_blank', 'width=1200,height=900')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-3a2 2 0 00-2-2h-2M7 17H5a2 2 0 01-2-2v-3a2 2 0 012-2h2m10 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v3m10 0H7"/></svg>
                        @if($request->status === 'approved')
                            Print Final Request
                        @else
                            Preview{{ $request->status === 'pending' ? ' (Pending)' : ' Request' }}
                        @endif
                    </button>
                @endcan
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
                    {{ $overallStatusTone === 'emerald' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : ($overallStatusTone === 'rose' ? 'bg-rose-100 text-rose-700 ring-rose-200' : ($overallStatusTone === 'sky' ? 'bg-sky-100 text-sky-700 ring-sky-200' : ($overallStatusTone === 'slate' ? 'bg-slate-200 text-slate-700 ring-slate-300' : 'bg-amber-100 text-amber-700 ring-amber-200'))) }}">
                    {{ $requestStatusLabel }}
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
            </div>
            @if($request->status === 'cancelled')
                <p class="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">This request was cancelled, so the approval workflow has ended.</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $hasAdditionalEquipment ? 'lg:grid-cols-6' : 'lg:grid-cols-5' }}">
                    @foreach($workflowSteps as $index => $step)
                        @php
                            $stepStatus = $workflowStatuses[$step['key']] ?? 'pending';
                            $isCurrent = $step['key'] === $currentWorkflowKey;
                            $stepIcon = $stepStatus === 'rejected' ? '✕' : ($stepStatus === 'approved' ? '✓' : ($isCurrent ? '◉' : '🕐'));
                            $stepTone = $stepStatus === 'rejected' ? 'rose' : ($stepStatus === 'approved' ? 'emerald' : ($isCurrent ? 'amber' : 'slate'));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center shadow-sm">
                            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full text-lg {{ $stepTone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : ($stepTone === 'rose' ? 'bg-rose-100 text-rose-700' : ($stepTone === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500')) }}" aria-label="{{ $stepStatus }}">
                                {{ $stepIcon }}
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $step['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if($hasAdditionalEquipment)
            <section class="mt-5 rounded-[24px] border border-amber-200 bg-amber-50 p-4 sm:p-5" aria-labelledby="equipment-return-progress-title">
                <h2 id="equipment-return-progress-title" class="text-base font-semibold text-amber-950">Equipment return progress</h2>
                <p class="mt-1 text-sm text-amber-900">Returned and missing quantities are both recorded as accounted for. Damaged items count as returned but are excluded from available inventory.</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-800">
                    @foreach($additionalEquipmentQuantities as $itemName => $quantity)
                        @php $outstanding = (int) ($returnOutstandingQuantities[$itemName] ?? 0); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-100 bg-white px-3 py-2">
                            <span class="font-medium">{{ $itemName }}</span>
                            <span>{{ $outstanding === 0 ? 'Accounted for' : $outstanding . ' of ' . $quantity . ' still unaccounted for' }}</span>
                        </li>
                    @endforeach
                </ul>

                @if($canRecordEquipmentReturn && $returnFormItems !== [])
                    @can('returnEquipment', $request)
                        <form method="POST" action="{{ route('custodian.return', $request->id) }}" class="mt-5 rounded-2xl border border-amber-200 bg-white p-4">
                            @csrf
                            <h3 class="font-semibold text-slate-900">Record equipment return</h3>
                            <p class="mt-1 text-sm text-slate-600">Enter cumulative quantities for the equipment assigned to you. You can submit a partial return and update it later.</p>
                            @if(isset($errors) && $errors->has('return'))
                                <p class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first('return') }}</p>
                            @endif
                            <div class="mt-4 space-y-4">
                                @foreach($returnFormItems as $itemName => $item)
                                    @php
                                        $maxReturned = max($item['returned'], $item['max_accountable'] - $item['missing']);
                                        $maxMissing = max($item['missing'], $item['max_accountable'] - $item['returned']);
                                    @endphp
                                    <fieldset class="rounded-xl border border-slate-200 p-3">
                                        <legend class="px-1 text-sm font-semibold text-slate-800">{{ $itemName }}</legend>
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <label class="text-xs font-medium text-slate-600">
                                                Returned (cumulative)
                                                <input type="number" name="equipment[{{ $itemName }}]" min="{{ $item['returned'] }}" max="{{ $maxReturned }}" value="{{ old('equipment.' . $itemName, $item['returned']) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                            </label>
                                            <label class="text-xs font-medium text-slate-600">
                                                Damaged (included above)
                                                <input type="number" name="damaged_quantity[{{ $itemName }}]" min="{{ $item['damaged'] }}" max="{{ $maxReturned }}" value="{{ old('damaged_quantity.' . $itemName, $item['damaged']) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                            </label>
                                            <label class="text-xs font-medium text-slate-600">
                                                Missing (cumulative)
                                                <input type="number" name="missing_quantity[{{ $itemName }}]" min="{{ $item['missing'] }}" max="{{ $maxMissing }}" value="{{ old('missing_quantity.' . $itemName, $item['missing']) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                            </label>
                                        </div>
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <label class="text-xs font-medium text-slate-600">
                                                Damage remarks
                                                <input type="text" name="damage_remarks[{{ $itemName }}]" value="{{ old('damage_remarks.' . $itemName) }}" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                            </label>
                                            <label class="text-xs font-medium text-slate-600">
                                                Missing-item remarks
                                                <input type="text" name="missing_remarks[{{ $itemName }}]" value="{{ old('missing_remarks.' . $itemName) }}" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                            </label>
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                            <label class="mt-4 block text-xs font-medium text-slate-600">
                                Notes
                                <textarea name="notes" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('notes') }}</textarea>
                            </label>
                            <button type="submit" class="mt-4 inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">Save return record</button>
                        </form>
                    @endcan
                @elseif($canRecordEquipmentReturn)
                    <p class="mt-3 text-sm text-amber-900">The remaining equipment is assigned to another custodian.</p>
                @endif
            </section>
        @endif
    </div>

    @if($pendingRevision && $currentUser && in_array((int) $currentUser->id, array_map('intval', $pendingRevision->affected_user_ids ?? [$request->requested_by_id]), true))
        @php
            $revisionResponses = $pendingRevision->responses ?? [];
            $currentRevisionResponse = $revisionResponses[(string) $currentUser->id] ?? 'pending';
            $currentUserMustRespond = $currentRevisionResponse === 'pending'
                && ! ($pendingRevision->memorandum_priority
                    && (int) $currentUser->id === (int) $request->requested_by_id
                    && ! $currentUser->isCustodian());
        @endphp
        <section style="margin-bottom: 24px !important;" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm" aria-labelledby="schedule-proposal-title">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <h2 id="schedule-proposal-title" class="text-lg font-semibold text-amber-950">Schedule change proposed</h2>
                    <p class="mt-1 text-sm text-amber-900">The Supply Office proposed a reservation change. The approved booking remains unchanged until required responses are received.</p>
                </div>
                <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{{ $currentUserMustRespond ? 'Your response needed' : 'Notice' }}</span>
            </div>
            <dl class="mt-4 grid gap-3 rounded-2xl border border-amber-200 bg-white p-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-slate-500">Proposed date</dt>
                    <dd class="mt-1 text-slate-900">{{ $pendingRevision->new_start_date?->format('M j, Y') }}@if($pendingRevision->new_end_date && $pendingRevision->new_end_date->toDateString() !== $pendingRevision->new_start_date?->toDateString()) – {{ $pendingRevision->new_end_date->format('M j, Y') }}@endif</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Proposed time</dt>
                    <dd class="mt-1 text-slate-900">{{ $pendingRevision->new_start_time }} – {{ $pendingRevision->new_end_time }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Venue</dt>
                    <dd class="mt-1 text-slate-900">{{ implode(', ', $pendingRevision->new_venue ?? []) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Reason from Supply Office</dt>
                    <dd class="mt-1 text-slate-900">{{ $pendingRevision->revision_reason }}</dd>
                </div>
                @if($pendingRevision->response_deadline_at)
                    <div>
                        <dt class="font-medium text-slate-500">Respond by</dt>
                        <dd class="mt-1 text-slate-900">{{ $pendingRevision->response_deadline_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
            </dl>
            @if($pendingRevision->memorandum_priority && (int) $currentUser->id === (int) $request->requested_by_id && ! $currentUser->isCustodian())
                <p class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">This transfer is required by a memorandum. You are being informed of the destination and reason; requestor consent is not required.</p>
            @endif
            @if($currentUserMustRespond)
            @if($pendingRevision->conflict_detected && $pendingRevision->override_conflict)
                <p class="mt-3 rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">The proposed schedule has a resource conflict that the Supply Office chose to override. Please consider this before accepting.</p>
            @endif
            <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <form method="POST" action="{{ route('request.revision.respond', [$request, $pendingRevision]) }}" data-swal-confirm data-swal-title="Decline this schedule proposal?" data-swal-text="Your existing reservation schedule will remain unchanged." data-swal-confirm-text="Yes, decline" data-swal-confirm-color="#dc2626">
                    @csrf
                    <input type="hidden" name="response" value="decline">
                    <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:w-auto">{{ $currentUser->isCustodian() && (int) $currentUser->id !== (int) $request->requested_by_id ? 'Reject proposal' : 'Decline proposal' }}</button>
                </form>
                <form method="POST" action="{{ route('request.revision.respond', [$request, $pendingRevision]) }}" data-swal-confirm data-swal-title="Confirm this schedule proposal?" data-swal-text="Your confirmation will be recorded. The booking changes only after all required responses." data-swal-confirm-text="Confirm schedule" data-swal-confirm-color="#059669">
                    @csrf
                    <input type="hidden" name="response" value="accept">
                    <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 sm:w-auto">{{ $currentUser->isCustodian() && (int) $currentUser->id !== (int) $request->requested_by_id ? 'Confirm as custodian' : 'Confirm proposed schedule' }}</button>
                </form>
            </div>
            @else
                <p class="mt-3 text-sm text-slate-600">Your response has been recorded or is not required. The approved booking remains unchanged until required custodian confirmations are complete.</p>
            @endif
        </section>
    @endif

    {{-- Role-Based Actions --}}
    <div style="display: grid; gap: 16px; margin-bottom: 24px !important;">
        @if(auth()->check() && auth()->user()->isRequestee() && auth()->id() === $request->requested_by_id && $hasCustodianApproval)
            <div class="bg-amber-50 border border-amber-200 rounded-3xl p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Request Change</h2>
                        <p class="text-sm text-slate-600">A custodian has already approved part of this request. Submit a change request before editing; direct editing is locked until a reviewer allows revision.</p>
                    </div>
                    @if($pendingChangeRequest)
                        <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Change Pending Review</span>
                    @endif
                </div>
                @if($pendingChangeRequest)
                    <p class="mt-3 rounded-2xl border border-amber-200 bg-white/70 p-3 text-sm text-slate-700"><span class="font-semibold">Reason:</span> {{ $pendingChangeRequest->reason }}</p>
                @else
                    <form method="POST" action="{{ route('request.change.submit', $request->id) }}" class="mt-4 space-y-3">
                        @csrf
                        <label for="change-request-reason" class="block text-sm font-semibold text-slate-700">Why do you need to change this request?</label>
                        <textarea id="change-request-reason" name="reason" rows="3" minlength="10" maxlength="1000" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Example: I want to change the venue from Gymnasium to CHIC because CHIC is available on the selected date."></textarea>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">Submit Change Request</button>
                    </form>
                @endif
            </div>
        @endif

        @if(auth()->check() && (auth()->user()->isCustodian() || auth()->user()->isAdmin()) && $pendingChangeRequest)
            <div class="bg-white border border-cyan-200 rounded-3xl p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Change Request Pending</h2>
                        <p class="text-sm text-slate-600">The requestor wants to change this request. Approving will open revision and reset the custodian review after the requestor saves changes.</p>
                        <p class="mt-3 rounded-2xl bg-slate-50 p-3 text-sm text-slate-700"><span class="font-semibold">Reason:</span> {{ $pendingChangeRequest->reason }}</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('request.change.approve', $request->id) }}" data-swal-confirm data-swal-title="Allow this request change?" data-swal-text="The request will move to Needs Revision so the requestor can edit it." data-swal-confirm-text="Yes, allow change" data-swal-confirm-color="#059669">
                        @csrf
                        <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">Allow Change</button>
                    </form>
                    <form method="POST" action="{{ route('request.change.reject', $request->id) }}" data-swal-confirm data-swal-title="Reject this change request?" data-swal-text="The existing request approvals will remain unchanged." data-swal-confirm-text="Yes, reject change" data-swal-confirm-color="#dc2626">
                        @csrf
                        <button type="submit" class="w-full rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white">Reject Change</button>
                    </form>
                </div>
            </div>
        @endif

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

        @if(auth()->check() && auth()->user()->isRequestee() && auth()->id() === $request->requested_by_id && in_array($request->status, ['pending', 'approved'], true) && $request->start_date?->toDateString() > now()->toDateString())
            <div class="bg-white border border-emerald-200 rounded-3xl p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Requestor Actions</h2>
                        <p class="text-sm text-slate-500">
                            @if($request->status === 'approved')
                                Request cancellation by the previous calendar day. Supply Office confirmation is required, and the booking remains active until confirmed.
                            @else
                                Cancel this pending request before it advances through approval.
                            @endif
                        </p>
                    </div>
                    @if($request->cancellation_status === 'pending')
                        <span class="rounded-xl bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-900">Cancellation awaiting Supply Office confirmation</span>
                    @else
                    <form method="POST" action="{{ route('request.cancel', $request->id) }}" class="flex-shrink-0" data-swal-confirm data-swal-title="{{ $request->status === 'approved' ? 'Request cancellation?' : 'Cancel this request?' }}" data-swal-text="{{ $request->status === 'approved' ? 'The Supply Office must confirm. Your approved reservation remains active until then.' : 'This action will cancel the pending request.' }}" data-swal-confirm-text="{{ $request->status === 'approved' ? 'Request cancellation' : 'Yes, cancel it' }}" data-swal-confirm-color="#dc2626">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-red-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-red-700">
                            {{ $request->status === 'approved' ? 'Request cancellation' : 'Cancel Request' }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        @endif

        @if(auth()->check() && auth()->user()->isCustodian())
            @if(auth()->user()->isCustodianVenue() && $request->status !== 'cancelled')
                @php
                    $availabilityGroup = 'custodian-venue-' . $request->id;
                @endphp
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sky-100 bg-sky-50/70 px-4 py-3">
                    <div>
                        <p class="text-sm text-slate-600">Review and validate every assigned venue schedule before taking action. The system will recheck availability when you submit.</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($request->getVenueNames() as $venueName)
                                @if($assignedVenueNames->contains(mb_strtolower(trim($venueName))))
                                    @php
                                        $venueSchedule = $request->getVenueSchedule($venueName);
                                    @endphp
                                    <x-venue-calendar-button
                                        :venue="$venueName"
                                        :date="$venueSchedule['start_date'] ?? now()->toDateString()"
                                        :end-date="$venueSchedule['end_date'] ?? now()->toDateString()"
                                        :start-time="$venueSchedule['start_time'] ?? null"
                                        :end-time="$venueSchedule['end_time'] ?? null"
                                        :availability-check-url="route('calendar.request-availability-check', $request)"
                                        :availability-group="$availabilityGroup"
                                        label="Check venue calendar"
                                    />
                                @endif
                            @endforeach
                        </div>
                        <p data-availability-summary="{{ $availabilityGroup }}" class="mt-2 hidden text-xs font-medium text-slate-600" aria-live="polite"></p>
                    </div>
                </div>
            @endif
            @if($request->status === 'cancelled')
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Custodian Verification</h2>
                            <p class="text-sm text-slate-500">This request was cancelled by the requester and no custodial action is required.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                            Cancelled
                        </span>
                    </div>
                </div>
            @elseif($pendingRevision)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <h2 class="font-semibold">Schedule proposal awaiting confirmation</h2>
                    <p class="mt-1">Venue availability actions are paused while the requestor and affected custodians respond to the proposed schedule.</p>
                </div>
            @elseif(!$hasEndorsed && ($canConfirmVenueAvailability || $canApproveAssignedEquipment || $canVenueCustodianReschedule))
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">{{ $canConfirmVenueAvailability || $canVenueCustodianReschedule ? ($request->venue_status === 'needs_reschedule' ? 'Venue Schedule Coordination' : 'Venue Availability Check') : 'Equipment Custodian Approval' }}</h2>
                                <p class="text-sm text-slate-500">{{ $canConfirmVenueAvailability || $canVenueCustodianReschedule ? ($request->venue_status === 'needs_reschedule' ? 'The current schedule was marked unavailable. Propose a new date and time for the requestor and affected custodians to confirm.' : 'Check the venue calendar, then confirm availability. This is not an approval; the Supply Office will confirm availability next.') : 'Review your assigned equipment and approve what you can provide, or reject it with a clear reason.' }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                Action Required
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @if($canConfirmVenueAvailability || $canApproveAssignedEquipment)
                                <form method="POST" action="{{ route('request.custodian.verify', $request->id) }}" data-availability-actions="{{ $canConfirmVenueAvailability ? $availabilityGroup : '' }}" data-availability-confirm="{{ $canConfirmVenueAvailability ? $availabilityGroup : '' }}" class="{{ $canConfirmVenueAvailability ? 'hidden' : '' }}" data-swal-confirm data-swal-title="{{ $canConfirmVenueAvailability ? 'Confirm venue availability?' : 'Approve assigned equipment?' }}" data-swal-text="{{ $canConfirmVenueAvailability ? 'The request will move to the Supply Office for another availability check. It is not approved yet.' : 'Your equipment decision will be recorded. Final approval still requires Supply Office review.' }}" data-swal-confirm-text="{{ $canConfirmVenueAvailability ? 'Confirm venue availability' : 'Approve equipment' }}" data-swal-confirm-color="#059669">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-2xl bg-emerald-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-700">
                                        {{ $canConfirmVenueAvailability ? 'Confirm Venue Availability' : 'Approve Assigned Equipment' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('request.custodian.reject', $request->id) }}" data-availability-actions="{{ $canConfirmVenueAvailability ? $availabilityGroup : '' }}" class="{{ $canConfirmVenueAvailability ? 'hidden' : '' }}" data-swal-confirm data-swal-title="{{ $canConfirmVenueAvailability ? 'Mark this venue unavailable?' : 'Reject this equipment request?' }}" data-swal-text="{{ $canConfirmVenueAvailability ? 'The Supply Office will be asked to coordinate an available schedule with the requestor.' : 'This will reject the equipment request and notify the requestor.' }}" data-swal-confirm-text="{{ $canConfirmVenueAvailability ? 'Mark unavailable' : 'Reject request' }}" data-swal-confirm-color="#dc2626">
                                    @csrf
                                    @if($canConfirmVenueAvailability)
                                        <label class="sr-only" for="venue-unavailable-notes">Reason the venue is unavailable</label>
                                        <textarea id="venue-unavailable-notes" name="notes" rows="2" required maxlength="1000" placeholder="Explain why this venue is unavailable" class="mb-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:col-span-2"></textarea>
                                    @endif
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-2xl bg-red-600 text-white px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-red-700">
                                        {{ $canConfirmVenueAvailability ? 'Mark Venue Unavailable' : 'Reject' }}
                                    </button>
                                </form>
                            @endif
                            @if($canVenueCustodianReschedule)
                                <button type="button" id="open-venue-reschedule-form" data-availability-actions="{{ $canConfirmVenueAvailability ? $availabilityGroup : '' }}" class="{{ $canConfirmVenueAvailability ? 'hidden ' : '' }}sm:col-span-2 w-full inline-flex items-center justify-center rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100">
                                    Propose Reschedule
                                </button>
                            @endif
                        </div>

                        @if($canVenueCustodianReschedule)
                            <form id="venue-reschedule-form" method="POST" action="{{ route('custodian.requests.revise') }}" class="hidden rounded-2xl border border-amber-200 bg-amber-50/60 p-4 space-y-4" data-swal-confirm data-swal-title="Send this reschedule proposal?" data-swal-text="The requestor and affected custodians must confirm the new schedule. The current request will remain unchanged until everyone responds." data-swal-confirm-text="Send proposal" data-swal-confirm-color="#d97706">
                                @csrf
                                <input type="hidden" name="facility_request_id" value="{{ $request->id }}">
                                @foreach($request->getVenueNames() as $venueName)
                                    <input type="hidden" name="venue[]" value="{{ $venueName }}">
                                @endforeach
                                @foreach($request->getEquipmentItems() as $equipmentItem)
                                    <input type="hidden" name="equipment[]" value="{{ $equipmentItem }}">
                                    <input type="hidden" name="equipment_quantities[{{ $equipmentItem }}]" value="{{ $request->getEquipmentQuantities()[$equipmentItem] ?? 1 }}">
                                @endforeach
                                <div>
                                    <h3 class="font-semibold text-amber-950">Propose another schedule</h3>
                                    <p class="mt-1 text-sm text-amber-900">Choose a date and time that appears open on the calendar. The system will recheck availability before sending the proposal.</p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="text-sm font-medium text-slate-700">New start date
                                        <input type="date" name="start_date" min="{{ now()->toDateString() }}" value="{{ $request->start_date?->toDateString() }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-slate-700">New end date
                                        <input type="date" name="end_date" min="{{ now()->toDateString() }}" value="{{ ($request->end_date ?? $request->start_date)?->toDateString() }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-slate-700">New start time
                                        <input type="time" name="start_time" value="{{ $request->start_time }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-slate-700">New end time
                                        <input type="time" name="end_time" value="{{ $request->end_time }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                    </label>
                                </div>
                                <label class="block text-sm font-medium text-slate-700">Reason for rescheduling
                                    <textarea name="revision_reason" minlength="10" maxlength="1000" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2" placeholder="Explain why this venue schedule needs to change."></textarea>
                                </label>
                                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                    <button type="button" id="cancel-venue-reschedule-form" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                                    <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Send reschedule proposal</button>
                                </div>
                            </form>
                        @endif

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
                                <p class="text-sm text-slate-500">
                                    @if(auth()->user()->isCustodianEquipment() && $request->supply_office_review_status !== 'confirmed' && $request->status === 'pending')
                                        Waiting for venue availability confirmation and Supply Office confirmation. Equipment approval is not open yet.
                                    @else
                                        Your custody decision has already been recorded.
                                    @endif
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                {{ auth()->user()->isCustodianEquipment() && $request->supply_office_review_status !== 'confirmed' && $request->status === 'pending' ? 'Waiting for earlier steps' : 'Step Complete' }}
                            </span>
                        </div>

                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-700">Your endorsement status</p>
                            <p class="mt-2 text-sm text-slate-700">
                                @if(auth()->user()->isCustodianEquipment() && $request->supply_office_review_status !== 'confirmed' && $request->status === 'pending')
                                    Venue availability → Supply Office confirmation → Your equipment review
                                @else
                                    Completed
                                @endif
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

                    @if(in_array($request->status, ['pending', 'approved'], true))
                        @if($request->status === 'approved')
                            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 mb-4">
                                <p class="text-sm font-semibold text-emerald-900">Approval Complete</p>
                                <p class="text-xs text-emerald-700">Only the Supply Office can propose changes to this approved reservation.</p>
                            </div>
                        @endif
                        <div class="grid gap-3 sm:grid-cols-2">
                            @if($request->status === 'pending')
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
                            @endif
                            <button type="button" id="open-reschedule-form" class="sm:col-span-2 lg:col-span-1 w-full inline-flex items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-amber-100">
                                {{ $request->status === 'approved' ? 'Propose Approved-Booking Change' : 'Reschedule Reservation' }}
                            </button>
                        </div>

                        <form id="reschedule-form" method="POST" action="{{ route('supply-office.requests.revise') }}" data-request-control-number="{{ $request->control_number }}" class="hidden rounded-2xl border border-amber-200 bg-amber-50/60 p-4 space-y-4">
                            @csrf
                            <div>
                                <h3 class="font-semibold text-amber-950">Propose a schedule change</h3>
                                <p class="mt-1 text-sm text-amber-900">The existing booking remains active until all affected custodians and users confirm. Proposals expire after 24 hours if unanswered.</p>
                            </div>
                            <input type="hidden" name="facility_request_id" value="{{ $request->id }}">
                            <label class="block text-sm font-medium text-slate-700">Proposed venue(s)
                                <select name="venue[]" multiple required class="mt-1 min-h-32 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                    @foreach($venueOptions as $venueOption)
                                        <option value="{{ $venueOption }}" @selected(in_array($venueOption, $venueNames, true))>{{ $venueOption }}</option>
                                    @endforeach
                                </select>
                                <span class="mt-1 block text-xs text-slate-500">Use Ctrl or Command to select multiple venues.</span>
                            </label>
                            @foreach($equipmentItems as $equipmentItem)
                                <input type="hidden" name="equipment[]" value="{{ $equipmentItem }}">
                                <input type="hidden" name="equipment_quantities[{{ $equipmentItem }}]" value="{{ $equipmentQuantities[$equipmentItem] ?? 1 }}">
                            @endforeach
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-sm font-medium text-slate-700">New start date
                                    <input type="date" name="start_date" value="{{ $request->start_date?->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-slate-700">New end date
                                    <input type="date" name="end_date" value="{{ ($request->end_date ?? $request->start_date)?->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-slate-700">New start time
                                    <input type="time" name="start_time" value="{{ $request->start_time }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-slate-700">New end time
                                    <input type="time" name="end_time" value="{{ $request->end_time }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                </label>
                            </div>
                            <label class="block text-sm font-medium text-slate-700">Reason for rescheduling
                                <textarea name="revision_reason" minlength="10" maxlength="1000" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2" placeholder="Explain why this reservation needs to be rescheduled."></textarea>
                            </label>
                            <div class="rounded-xl border border-amber-200 bg-white p-3">
                                <label class="flex items-start gap-2 text-sm font-medium text-slate-800">
                                    <input type="checkbox" name="memorandum_priority" value="1" class="mt-0.5 rounded border-slate-300">
                                    <span>Required transfer due to a CHED, OP, or OVP memorandum</span>
                                </label>
                                <select name="memorandum_office" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    <option value="">Select the issuing office if applicable</option>
                                    <option value="CHED">CHED</option>
                                    <option value="OP">Office of the President (OP)</option>
                                    <option value="OVP">Office of the Vice President (OVP)</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">The requestor will be informed but cannot veto a memorandum-priority transfer. Affected custodians must still confirm.</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                <button type="button" id="cancel-reschedule-form" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                                <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Send schedule proposal</button>
                            </div>
                            <p id="reschedule-feedback" class="hidden rounded-xl px-3 py-2 text-sm" role="status" aria-live="polite"></p>
                        </form>
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

                    <div style="margin-top: 16px;" class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-700">Custodial Endorsement Summary</p>
                        <div class="mt-3 space-y-2">
                            @forelse($custodialEndorsements as $endorsement)
                                @php
                                    $status = $endorsement['status'] ?? 'pending';
                                    $badge = match($status) {
                                        'approved' => 'bg-emerald-100 text-emerald-700',
                                        'revision_requested' => 'bg-orange-100 text-orange-700',
                                        'rejected' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <div class="flex items-center justify-between rounded-xl bg-white border border-slate-200 px-3 py-2">
                                    <span class="text-sm text-slate-700">{{ $endorsement['name'] }} <span class="text-slate-500">({{ $endorsement['resource_type'] }})</span></span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">No assigned custodians are available for this request.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Overall Status</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $requestStatusLabel }}</p>
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

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
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
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Contact Number</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->requester?->contact_number ?: 'Not provided' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Request Priority</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $requestPriorityLabel }}</p>
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
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ implode(', ', $venueNames) ?: '—' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Venue Schedules</p>
                    <div class="mt-2 space-y-2">
                        @forelse($venueScheduleSummaries as $schedule)
                            <p class="text-sm text-slate-700">
                                <span class="font-semibold text-slate-900">{{ $schedule['venue'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $schedule['label'] }} · {{ $schedule['duration'] }}</span>
                            </p>
                        @empty
                            <p class="text-sm text-slate-500">Not specified</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Expected Participants</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $request->expected_participants }}</p>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
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
                        <p class="mt-2 text-sm text-slate-700">{{ $request->purpose ?: $request->notes ?: 'No purpose details provided.' }}</p>
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

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 011.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Equipment
            </div>
            @php
                $includedEquipmentCount = array_sum(array_map('count', $venueIncludedEquipment));
                $additionalEquipmentCount = count($additionalEquipmentItems);
            @endphp
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600">
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 ring-1 ring-emerald-200">{{ $includedEquipmentCount }} included</span>
                <span class="rounded-full bg-violet-50 px-2.5 py-1 text-violet-700 ring-1 ring-violet-200">{{ $additionalEquipmentCount }} additional</span>
            </div>
        </div>

        <div class="mt-5 space-y-6">
            @if(!empty($venueIncludedEquipment))
                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Included with venue</span>
                        <p class="text-xs text-slate-500">These items are covered by the selected venue and are not a separate request.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($venueIncludedEquipment as $venueName => $venueEquipment)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ $venueName }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($venueEquipment as $equipmentName)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ $equipmentName }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($additionalEquipmentItems))
                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-violet-700">Additional requested</span>
                        <p class="text-xs text-slate-500">Items requested beyond what is already provided by the venue.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($additionalEquipmentItems as $e)
                            <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <div class="rounded-full bg-violet-100 p-2 text-violet-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1h2a2 2 0 012 2v2h1a2 2 0 012 2v2a2 2 0 01-2 2h-1v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2H9a2 2 0 01-2-2v-2a2 2 0 012-2h1V7a2 2 0 012-2h2z"/></svg>
                                        </div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $e }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">Qty {{ !empty($equipmentQuantities[$e]) ? $equipmentQuantities[$e] : 1 }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif(empty($venueIncludedEquipment))
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                    No additional equipment requested.
                </div>
            @else
                <p class="text-sm text-slate-500">No additional equipment requested.</p>
            @endif
        </div>
    </div>

    @if(!empty($requestDocuments))
        <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-2xl bg-blue-100 p-3 text-blue-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0013.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">{{ $isOutsiderRequest ? 'Request Documents' : 'Document Attachment' }}</p>
                        <div class="mt-3 space-y-3">
                            @foreach($requestDocuments as $document)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $document['label'] }}</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900 break-all">{{ $document['filename'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach($requestDocuments as $document)
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-proposal-url="{{ route('request.proposal', ['id' => $request->id, 'document' => $document['type']]) }}" data-proposal-pdf="{{ $document['is_pdf'] ? 'true' : 'false' }}" onclick="openProposalPreview(this)"
                                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Preview {{ $document['label'] }}
                            </button>
                            <a href="{{ route('request.proposal.download', ['id' => $request->id, 'document' => $document['type']]) }}"
                                class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Download {{ $document['label'] }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div id="proposal-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/75 p-4" role="dialog" aria-modal="true" aria-labelledby="proposal-preview-title">
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="proposal-preview-title" class="text-lg font-semibold text-slate-900">Document Preview</h2>
                <button type="button" onclick="closeProposalPreview()" class="rounded-full px-3 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close preview">&times;</button>
            </div>
            <div id="proposal-preview-content" class="min-h-[60vh] overflow-auto bg-slate-100 p-4"></div>
        </div>
    </div>

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Approval Information
        </div>
        <div class="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-start gap-3">
                <div class="rounded-2xl {{ $approverTone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : ($approverTone === 'rose' ? 'bg-rose-100 text-rose-700' : ($approverTone === 'slate' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-700')) }} p-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $approvalStatusLabel }}</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $approvalTitle }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $approvalBody }}</p>
                    @if($request->status === 'pending' && $pendingCustodianNames !== '')
                        <p class="mt-2 text-sm text-slate-700"><span class="font-semibold">Assigned reviewer(s):</span> {{ $pendingCustodianNames }}</p>
                    @endif
                    @if(auth()->user()->isRequestor() && $request->status === 'approved')
                        <p class="mt-3 text-sm font-semibold text-emerald-700">Your approved request is ready for pickup from the Supply Office.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 24px !important;" class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">
            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Activity History
        </div>
        <div class="mt-5 space-y-4">
            @forelse($request->histories()->orderByDesc('occurred_at')->get() as $history)
                <div class="flex gap-4 rounded-[24px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white {{ str_contains($history->action, 'approved') || str_contains($history->action, 'custodian_endorsed') ? 'bg-emerald-500' : (str_contains($history->action, 'rejected') ? 'bg-rose-500' : (str_contains($history->action, 'returned') ? 'bg-sky-500' : 'bg-slate-400')) }}">
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
                        @if($history->user)
                            <p class="mt-1 text-xs font-medium text-slate-500">Recorded by {{ $history->user->name }}</p>
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

    function openProposalPreview(button) {
        const modal = document.getElementById('proposal-preview-modal');
        const content = document.getElementById('proposal-preview-content');
        const url = button.dataset.proposalUrl;
        content.innerHTML = button.dataset.proposalPdf === 'true'
            ? '<iframe src="' + url + '" title="Activity proposal document" class="h-[70vh] w-full border-0"></iframe>'
            : '<img src="' + url + '" alt="Activity proposal" class="mx-auto max-h-[70vh] max-w-full object-contain">';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeProposalPreview() {
        const modal = document.getElementById('proposal-preview-modal');
        const content = document.getElementById('proposal-preview-content');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        content.replaceChildren();
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeProposalPreview();
        }
    });

    const rescheduleForm = document.getElementById('reschedule-form');
    const openRescheduleButton = document.getElementById('open-reschedule-form');
    const cancelRescheduleButton = document.getElementById('cancel-reschedule-form');
    const rescheduleFeedback = document.getElementById('reschedule-feedback');
    const venueRescheduleForm = document.getElementById('venue-reschedule-form');
    const openVenueRescheduleButton = document.getElementById('open-venue-reschedule-form');
    const cancelVenueRescheduleButton = document.getElementById('cancel-venue-reschedule-form');

    openRescheduleButton?.addEventListener('click', () => {
        rescheduleForm?.classList.remove('hidden');
        openRescheduleButton.classList.add('hidden');
    });

    cancelRescheduleButton?.addEventListener('click', () => {
        rescheduleForm?.reset();
        rescheduleForm?.classList.add('hidden');
        openRescheduleButton?.classList.remove('hidden');
        rescheduleFeedback?.classList.add('hidden');
    });

    openVenueRescheduleButton?.addEventListener('click', () => {
        venueRescheduleForm?.classList.remove('hidden');
        openVenueRescheduleButton.classList.add('hidden');
        venueRescheduleForm?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    cancelVenueRescheduleButton?.addEventListener('click', () => {
        venueRescheduleForm?.reset();
        venueRescheduleForm?.classList.add('hidden');
        openVenueRescheduleButton?.classList.remove('hidden');
    });

    rescheduleForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (rescheduleForm.dataset.submitting === '1') {
            return;
        }

        const submitButton = rescheduleForm.querySelector('button[type="submit"]');
        const selectedVenues = Array.from(rescheduleForm.querySelector('[name="venue[]"]').selectedOptions)
            .map((option) => option.textContent.trim());
        const confirmation = await Swal.fire({
            title: 'Send this schedule proposal?',
            text: [
                `Request: ${rescheduleForm.dataset.requestControlNumber}`,
                `Venue${selectedVenues.length === 1 ? '' : 's'}: ${selectedVenues.join(', ')}`,
                `Schedule: ${rescheduleForm.querySelector('[name="start_date"]').value} to ${rescheduleForm.querySelector('[name="end_date"]').value}`,
                `Time: ${rescheduleForm.querySelector('[name="start_time"]').value}–${rescheduleForm.querySelector('[name="end_time"]').value}`,
                'The current booking stays active until all required people confirm.'
            ].join('\n'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, send proposal',
            cancelButtonText: 'Review details',
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#64748b',
        });
        if (!confirmation.isConfirmed) {
            return;
        }

        rescheduleForm.dataset.submitting = '1';
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';
        rescheduleFeedback.className = 'hidden rounded-xl px-3 py-2 text-sm';

        try {
            const response = await fetch(rescheduleForm.action, {
                method: 'POST',
                body: new FormData(rescheduleForm),
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                }
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || payload.message || 'Unable to send the schedule proposal. Please review the details and try again.');
            }

            const successMessage = payload.message || 'The schedule proposal has been sent to the requestor and affected custodians.';
            rescheduleFeedback.textContent = successMessage;
            rescheduleFeedback.className = 'rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800';
            submitButton.textContent = 'Proposal sent';
            await Swal.fire({
                title: 'Proposal sent',
                text: successMessage,
                icon: 'success',
                confirmButtonText: 'View updated request',
                confirmButtonColor: '#059669',
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
            window.location.reload();
        } catch (error) {
            rescheduleFeedback.textContent = error instanceof Error ? error.message : 'Unable to send the schedule proposal. Please try again.';
            rescheduleFeedback.className = 'rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800';
            submitButton.disabled = false;
            submitButton.textContent = 'Send schedule proposal';
            rescheduleForm.dataset.submitting = '0';
            rescheduleFeedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

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