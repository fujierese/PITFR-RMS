@php
    $venues = implode(', ', $request->getVenueNames()) ?: 'N/A';
    $equipmentItems = $request->getEquipmentItems();
    $proposalFilename = $request->proposal_file ?? 'N/A';

    $venueStatusLabel = match($request->venue_status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
    $venueApprover = $request->getStageApproverName('venue') ?: 'Pending';
    $venueApprovedOn = $request->getStageApprovalDate('venue') ? $request->getStageApprovalDate('venue')->format('M j, Y') : 'Pending';

    $equipmentStatusLabel = match($request->equipment_status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
    $equipmentApprover = $request->getStageApproverName('equipment') ?: 'Pending';
    $equipmentApprovedOn = $request->getStageApprovalDate('equipment') ? $request->getStageApprovalDate('equipment')->format('M j, Y') : 'Pending';

    $finalStatusLabel = match($request->status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
    $finalApprover = $request->getStageApproverName('final') ?: 'Pending';
    $finalApprovedOn = $request->getStageApprovalDate('final') ? $request->getStageApprovalDate('final')->format('M j, Y') : 'Pending';

    $scheduleDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M j, Y') : 'N/A';
    if (!empty($request->end_date) && $request->end_date !== $request->start_date) {
        $scheduleDate .= ' - ' . \Carbon\Carbon::parse($request->end_date)->format('M j, Y');
    }
    $scheduleTime = !empty($request->start_time) ? \Carbon\Carbon::parse($request->start_time)->format('g:i A') : 'N/A';
    if (!empty($request->end_time)) {
        $scheduleTime .= ' - ' . \Carbon\Carbon::parse($request->end_time)->format('g:i A');
    }
@endphp

<div class="document-sheet">
    <header class="document-header">
        <div class="header-left">
            <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo" class="header-logo">
        </div>
        <div class="header-center">
            <p class="header-subtitle">Palompon Institute of Technology</p>
            <p class="header-title">Quality Management System</p>
            <h1 class="document-title">Request for the Use of Facility / Equipment</h1>
        </div>
        <div class="document-meta">
            <div><strong>Ref. Code:</strong> PIT-PSMO-F-05-3.7-08</div>
            <div><strong>Rev. No.:</strong> 00</div>
            <div class="iso-box">
                <strong>TUV NORD</strong>
                <span>ISO 9001:2015 Certified</span>
            </div>
        </div>
    </header>

    <section class="document-section">
        <div class="section-header">
            <span>Section I. Requestor Information</span>
        </div>
        <div class="field-grid">
            <div class="field">
                <label>Department / Requisitioning Office</label>
                <div class="value">{{ $request->department ?? 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Requestor</label>
                <div class="value">{{ $request->requested_by ?? 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Position</label>
                <div class="value">{{ $request->requester?->position ?? 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Control Number</label>
                <div class="value">{{ $request->control_number }}</div>
            </div>
            <div class="field">
                <label>Date Requested</label>
                <div class="value">{{ $request->created_at ? \Carbon\Carbon::parse($request->created_at)->format('M j, Y') : 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Proposal Filename</label>
                <div class="value">{{ $proposalFilename }}</div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <div class="section-header">
            <span>Section II. Activity Details</span>
        </div>
        <div class="field-grid">
            <div class="field field-full">
                <label>Activity / Purpose</label>
                <div class="value">{{ $request->name_of_activity ?? 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Expected No. of Participants</label>
                <div class="value">{{ $request->expected_participants ?? 'N/A' }}</div>
            </div>
            <div class="field">
                <label>Venue / Facility</label>
                <div class="value">{{ $venues }}</div>
            </div>
            <div class="field">
                <label>Date(s)</label>
                <div class="value">{{ $scheduleDate }}</div>
            </div>
            <div class="field">
                <label>Time</label>
                <div class="value">{{ $scheduleTime }}</div>
            </div>
            <div class="field">
                <label>Department</label>
                <div class="value">{{ $request->department ?? 'N/A' }}</div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <div class="section-header">
            <span>Section III. Equipment Request</span>
        </div>
        <table class="equipment-table">
            <thead>
                <tr>
                    <th>Equipment / Item</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @if(count($equipmentItems) > 0)
                    @foreach($equipmentItems as $item)
                        <tr>
                            <td>{{ $item }}</td>
                            <td>{{ !empty($request->getEquipmentQuantities()[$item]) ? $request->getEquipmentQuantities()[$item] : 1 }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="2">No equipment requested.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="document-section">
        <div class="section-header">
            <span>Section IV. Approval Status</span>
        </div>
        <div class="approval-grid">
            <div class="approval-card">
                <p class="approval-label">Venue Approval</p>
                <p class="approval-value">{{ $venueStatusLabel }}</p>
                <p class="approval-detail">{{ $venueApprover }}</p>
                <p class="approval-detail">{{ $venueApprovedOn }}</p>
            </div>
            <div class="approval-card">
                <p class="approval-label">Equipment Approval</p>
                <p class="approval-value">{{ $equipmentStatusLabel }}</p>
                <p class="approval-detail">{{ $equipmentApprover }}</p>
                <p class="approval-detail">{{ $equipmentApprovedOn }}</p>
            </div>
            <div class="approval-card">
                <p class="approval-label">Final Approval</p>
                <p class="approval-value">{{ $finalStatusLabel }}</p>
                <p class="approval-detail">{{ $finalApprover }}</p>
                <p class="approval-detail">{{ $finalApprovedOn }}</p>
            </div>
        </div>
    </section>

    <section class="document-section document-signature">
        <div class="signature-grid">
            <div class="signature-block">
                <p class="signature-title">Requested by</p>
                <div class="signature-line"></div>
                <p class="signature-name">{{ $request->requested_by ?? '____________________' }}</p>
            </div>
            <div class="signature-block">
                <p class="signature-title">Approved by</p>
                <div class="signature-line"></div>
                <p class="signature-name">{{ $finalApprover === 'Pending' ? 'Pending' : $finalApprover }}</p>
            </div>
        </div>
    </section>
</div>
