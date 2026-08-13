<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIT Facility/Equipment Request - {{ $request->control_number }}</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #475569;
            --line: #111827;
            --panel: #f8fafc;
            --soft: #f1f5f9;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.25;
        }

        body {
            display: flex;
            justify-content: center;
            padding: 0;
        }

        @page {
            size: auto;
            margin: 6mm 7mm;
        }

        .print-shell {
            width: 100%;
            max-width: 100%;
            padding: 0;
            background: #fff;
        }

        .sheet-stack {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 4mm;
        }

        .copy-form {
            width: 100%;
            border: 2px solid var(--line);
            background: #fff;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        .copy-separator {
            width: 100%;
            border-top: 2px solid var(--line);
            height: 0;
            margin: 0;
            opacity: 0.9;
        }

        .form-body {
            display: flex;
            flex-direction: column;
        }

        .header-row {
            display: grid;
            grid-template-columns: 92px 1fr 190px;
            border-bottom: 2px solid var(--line);
            min-height: 116px;
        }

        .logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 2px solid var(--line);
            background: var(--soft);
            padding: 0.5rem;
        }

        .logo-box img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 9999px;
            background: #fff;
        }

        .title-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 2px solid var(--line);
            padding: 0.6rem 0.8rem;
            text-align: center;
        }

        .title-box .small-tag {
            display: block;
            font-size: 0.66rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            margin-bottom: 0.2rem;
        }

        .title-box .institution {
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 0 0 0.2rem;
        }

        .title-box .qms {
            display: block;
            font-size: 0.66rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 700;
            margin: 0.15rem 0 0;
        }

        .title-box .form-name {
            margin: 0.5rem 0 0;
            font-size: 0.92rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            line-height: 1.2;
        }

        .reference-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0.55rem 0.6rem;
            background: var(--panel);
            font-size: 0.66rem;
        }

        .reference-box .meta-line {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            border-bottom: 1px solid var(--line);
            padding-bottom: 0.3rem;
            margin-bottom: 0.3rem;
        }

        .reference-box strong { font-weight: 700; }

        .reference-box .iso {
            margin-top: 0.3rem;
            border: 1px solid var(--line);
            padding: 0.35rem 0.4rem;
            text-align: center;
            background: #fff;
            line-height: 1.3;
        }

        .reference-box .iso strong {
            display: block;
            font-size: 0.68rem;
            margin-bottom: 0.15rem;
        }

        .label-row {
            display: grid;
            grid-template-columns: 1fr auto;
            border-bottom: 2px solid var(--line);
            min-height: 52px;
        }

        .label-row > div {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.52rem 0.7rem;
            min-width: 0;
        }

        .label-row > div:first-child {
            border-right: 2px solid var(--line);
        }

        .field-label {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            white-space: nowrap;
        }

        .field-value {
            flex: 1;
            min-width: 0;
            border-bottom: 1px solid var(--line);
            padding: 0.18rem 0.2rem 0.08rem;
            font-size: 0.76rem;
            word-break: break-word;
            min-height: 1.1rem;
            text-align: left;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .meta-grid > div {
            padding: 0.44rem 0.7rem;
            display: flex;
            align-items: center;
            min-height: 54px;
        }

        .meta-grid > div:first-child {
            border-right: 2px solid var(--line);
        }

        .meta-grid .field-value { border-bottom: none; }

        .purpose-row {
            border-bottom: 2px solid var(--line);
            padding: 0.55rem 0.7rem;
        }

        .purpose-row .field-label {
            display: block;
            margin-bottom: 0.2rem;
        }

        .purpose-row .field-value {
            border-bottom: 1px solid var(--line);
            min-height: 1.45rem;
        }

        .schedule-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .schedule-row > div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.48rem 0.7rem;
            min-height: 54px;
        }

        .schedule-row > div:first-child {
            border-right: 2px solid var(--line);
        }

        .inclusive-row {
            border-bottom: 2px solid var(--line);
            padding: 0.48rem 0.7rem;
            min-height: 54px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-box {
            border-bottom: 2px solid var(--line);
            padding: 0.7rem;
        }

        .section-box .title {
            display: block;
            font-size: 0.66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin-bottom: 0.45rem;
        }

        .check-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.42rem 0.75rem;
            font-size: 0.66rem;
        }

        .check-item {
            display: flex;
            align-items: flex-start;
            gap: 0.42rem;
            min-height: 1.2rem;
        }

        .check-item input {
            margin-top: 0.1rem;
            width: 0.75rem;
            height: 0.75rem;
            accent-color: #111827;
        }

        .check-item span { line-height: 1.2; }

        .other-specify {
            margin-top: 0.5rem;
            padding-left: 1.1rem;
            font-size: 0.62rem;
            display: block;
        }

        .other-specify .field-value {
            min-height: 1rem;
            font-size: 0.62rem;
            padding-top: 0.14rem;
        }

        .approval-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .approval-panel {
            padding: 0.7rem;
            min-height: 120px;
        }

        .approval-panel:first-child {
            border-right: 2px solid var(--line);
        }

        .approval-panel .title {
            display: block;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin-bottom: 0.45rem;
        }

        .approval-list {
            font-size: 0.62rem;
            line-height: 1.5;
            color: var(--muted);
        }

        .approval-list strong {
            font-weight: 700;
            color: var(--ink);
        }

        .signature-block {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 120px;
            padding: 0.8rem 0.7rem 0.6rem;
        }

        .signature-block .field-label {
            margin-bottom: 0.35rem;
            display: block;
        }

        .signature-line {
            border-bottom: 1px solid var(--line);
            height: 2.2rem;
            width: 100%;
            margin-bottom: 0.25rem;
        }

        .signature-name {
            font-size: 0.76rem;
            font-weight: 700;
            min-height: 1.1rem;
        }

        .footer-bar {
            font-size: 0.6rem;
            letter-spacing: 0.08em;
            text-align: right;
            padding: 0.35rem 0.55rem;
            color: var(--muted);
            background: var(--panel);
            border-top: 1px solid var(--line);
        }

        @media (max-width: 680px) {
            .header-row { grid-template-columns: 82px 1fr; }
            .reference-box {
                grid-column: 1 / -1;
                border-top: 2px solid var(--line);
            }
            .label-row, .meta-grid, .schedule-row, .approval-row { grid-template-columns: 1fr; }
            .label-row > div:first-child, .meta-grid > div:first-child, .schedule-row > div:first-child, .approval-panel:first-child {
                border-right: none;
                border-bottom: 2px solid var(--line);
            }
            .check-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media print {
            html, body {
                width: 100%;
                height: auto;
                margin: 0;
                padding: 0;
                background: #fff;
            }

            body {
                display: block;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-shell {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .sheet-stack {
                width: 100%;
                max-width: none;
                gap: 4mm;
                margin: 0;
            }

            .copy-form {
                max-width: none;
                margin: 0;
                box-shadow: none;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    @php
        $venueNames = $request->getVenueNames();
        $venues = implode(', ', $venueNames) ?: 'N/A';
        $selectedEquipment = $request->getEquipmentItems();
        $equipmentQuantities = $request->getEquipmentQuantities();
        $finalApprover = $request->getStageApproverName('final') ?: 'Pending';
        $finalApprovalDate = $request->getStageApprovalDate('final') ? $request->getStageApprovalDate('final')->format('M j, Y') : 'Pending';

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M j, Y') : 'N/A';
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->format('M j, Y') : null;
        $inclusiveDates = $endDate && $endDate !== $startDate ? $startDate . ' - ' . $endDate : $startDate;

        $startTime = $request->start_time ? \Carbon\Carbon::parse($request->start_time)->format('g:i A') : 'N/A';
        $endTime = $request->end_time ? \Carbon\Carbon::parse($request->end_time)->format('g:i A') : null;
        $normalizedDuration = strtolower((string) ($request->reservation_duration ?? ''));
        $wholeDayRequested = in_array($normalizedDuration, ['whole_day', 'whole-day', 'whole day'], true)
            || ((string) ($request->start_time ?? '') === '08:00' && (string) ($request->end_time ?? '') === '00:00');
        $scheduleTime = $endTime ? $startTime . ' - ' . $endTime : $startTime;
        $displayScheduleTime = $wholeDayRequested ? 'WHOLE DAY (8:00 AM – 12:00 AM)' : $scheduleTime;

        $requesterName = $request->requested_by ?? $request->requester?->name ?? 'N/A';
        $requesterPosition = $request->requested_by_position ?? $request->requester?->position ?? 'N/A';
        $venueApprover = $request->getStageApproverName('venue') ?: 'Pending';
        $equipmentApprover = $request->getStageApproverName('equipment') ?: 'Pending';
        $statusLabel = match ($request->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'needs_reschedule' => 'Needs Reschedule',
            default => 'Pending',
        };
    @endphp

    <div class="print-shell">
        <div class="sheet-stack">
            @foreach ([1, 2] as $copy)
                <div class="copy-form" aria-label="Official facility request form">
                    <div class="form-body">
                        <div class="header-row">
                            <div class="logo-box">
                                <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo">
                            </div>
                            <div class="title-box">
                                <div>
                                    <span class="small-tag">Quality Form</span>
                                    <p class="institution">Palompon Institute of Technology</p>
                                    <span class="qms">Quality Management System</span>
                                    <p class="form-name">REQUEST FOR THE USE OF FACILITY/EQUIPMENT</p>
                                </div>
                            </div>
                            <div class="reference-box">
                                <div class="meta-line"><span>Ref. Code:</span> <strong>PIT-PSMO-F-05-3.7-08</strong></div>
                                <div class="meta-line"><span>Rev. No:</span> <strong>00</strong></div>
                                <div class="iso">
                                    <strong>TUV NORD</strong>
                                    <span>ISO 9001:2015 Certified</span>
                                </div>
                            </div>
                        </div>

                        <div class="label-row">
                            <div>
                                <span class="field-label">Dept. / Requisitioning Office:</span>
                                <span class="field-value">{{ $request->department ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="field-label">Control Number:</span>
                                <span class="field-value">{{ $request->control_number }}</span>
                            </div>
                        </div>

                        <div class="meta-grid">
                            <div>
                                <span class="field-label">Date Requested:</span>
                                <span class="field-value">{{ $request->date_requested ? \Carbon\Carbon::parse($request->date_requested)->format('M j, Y') : 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="field-label">Status:</span>
                                <span class="field-value">{{ $statusLabel }}</span>
                            </div>
                        </div>

                        <div class="purpose-row">
                            <span class="field-label">Activity / Purpose:</span>
                            <span class="field-value">{{ $request->name_of_activity ?? 'N/A' }}</span>
                        </div>

                        <div class="schedule-row">
                            <div>
                                <span class="field-label">Expected No. of Participants:</span>
                                <span class="field-value">{{ $request->expected_participants ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="field-label">Time:</span>
                                <span class="field-value">{{ $displayScheduleTime }}</span>
                            </div>
                        </div>

                        <div class="inclusive-row">
                            <span class="field-label">Inclusive Dates:</span>
                            <span class="field-value">{{ $inclusiveDates }}</span>
                        </div>

                        <div class="section-box">
                            <span class="title">Facility / Venue</span>
                            <div class="check-grid">
                                @php
                                    $venueOptions = ['Conference Hall & Interaction Center (CHIC)', 'Gymnasium', 'Balay Alumni', 'Oval Grounds', 'Covered Court', 'Volleyball Court', 'Others (specify)'];
                                    $selectedVenueNames = array_map('strtolower', $venueNames);
                                @endphp
                                @foreach ($venueOptions as $venueOption)
                                    <label class="check-item">
                                        <input type="checkbox" {{ in_array(strtolower($venueOption), $selectedVenueNames, true) ? 'checked' : '' }} disabled>
                                        <span>{{ $venueOption }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if (!empty($request->other_venue))
                                <div class="other-specify">
                                    <span class="field-label">Other venue:</span>
                                    <span class="field-value">{{ $request->other_venue }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="section-box">
                            <span class="title">Equipment</span>
                            <div class="check-grid">
                                @php
                                    $equipmentOptions = ['Sound System', 'Microphones', 'Canopies', 'Industrial Fans', 'Iwata Cooler Fans', 'Tables', 'Monobloc chairs', 'Others (specify)'];
                                    $selectedEquipmentNames = array_map('strtolower', $selectedEquipment);
                                @endphp
                                @foreach ($equipmentOptions as $equipmentOption)
                                    <label class="check-item">
                                        <input type="checkbox" {{ in_array(strtolower($equipmentOption), $selectedEquipmentNames, true) ? 'checked' : '' }} disabled>
                                        <span>{{ $equipmentOption }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if (!empty($request->other_equipment))
                                <div class="other-specify">
                                    <span class="field-label">Other equipment:</span>
                                    <span class="field-value">{{ $request->other_equipment }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="signature-block">
                            <span class="field-label">Requisitioner</span>
                            <div class="signature-line"></div>
                            <div class="signature-name">{{ $requesterName }}</div>
                            <div class="signature-name" style="font-weight: 600; margin-top: 0.3rem;">{{ $requesterPosition }}</div>
                            <div style="font-size: 0.52rem; letter-spacing: 0.12em; text-transform: uppercase; margin-top: 0.4rem; color: var(--muted);">Signature over printed name</div>
                        </div>

                        <div class="approval-row">
                            <div class="approval-panel">
                                <span class="title">Recommending Approval (Venue)</span>
                                <div class="approval-list">
                                    <div>{{ $venueApprover }}</div>
                                </div>
                            </div>
                            <div class="approval-panel">
                                <span class="title">Approved By</span>
                                <div class="approval-list">
                                    <div><strong>{{ $finalApprover }}</strong></div>
                                    <div>{{ $finalApprovalDate }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="approval-row">
                            <div class="approval-panel">
                                <span class="title">Recommending Approval (Equipment)</span>
                                <div class="approval-list">
                                    <div>{{ $equipmentApprover }}</div>
                                </div>
                            </div>
                            <div class="approval-panel">
                                <span class="title">Status</span>
                                <div class="approval-list">
                                    <div><strong>{{ $statusLabel }}</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="footer-bar">
                            QAD-QMS Manual 1.0 &nbsp;&nbsp; Page 1 of 1 &nbsp;&nbsp; Internal use only
                        </div>
                    </div>
                </div>
                @if ($copy === 1)
                    <div class="copy-separator" aria-hidden="true"></div>
                @endif
            @endforeach
        </div>
    </div>
</body>
</html>
