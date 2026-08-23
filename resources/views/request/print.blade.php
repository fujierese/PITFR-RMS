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
            --panel: #ffffff;
            --soft: #ffffff;
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
            gap: 3mm;
        }

        .copy-form {
            width: 100%;
            border: 2px solid var(--line);
            background: #fff;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        .print-settings-helper {
            position: relative;
            width: 100%;
            display: block;
            margin: 0 0 10px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .print-settings-box {
            max-width: 440px;
            margin: 0 auto;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #111827;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            border-radius: 6px;
        }

        .print-settings-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
            text-align: center;
        }

        .print-settings-row {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            font-size: 11px;
            line-height: 1.6;
            gap: 8px;
        }

        .print-settings-row span:first-child {
            text-align: left;
        }

        .print-settings-row span:nth-child(2) {
            text-align: center;
        }

        .print-settings-row span:last-child {
            text-align: right;
            font-weight: 700;
        }

        .print-settings-note {
            margin-top: 8px;
            font-size: 10px;
            line-height: 1.5;
            text-align: center;
            color: #374151;
        }

        .copy-separator {
            width: 100%;
            border-top: 1px solid var(--line);
            height: 0;
            margin: 0;
            opacity: 1;
        }

        .copy-label {
            display: none;
        }

        .form-body {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .header-row {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr) 180px;
            border-bottom: 2px solid var(--line);
            min-height: 94px;
        }

        .logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid var(--line);
            background: #fff;
            padding: 0.5rem;
        }

        .logo-box img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 9999px;
            background: #fff;
        }

        .title-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid var(--line);
            padding: 0.5rem 0.7rem;
            text-align: center;
        }

        .title-box .small-tag {
            display: block;
            font-size: 0.56rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin-bottom: 0.18rem;
        }

        .title-box .institution {
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 0 0 0.15rem;
        }

        .title-box .qms {
            display: block;
            font-size: 0.54rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
            margin: 0.12rem 0 0;
        }

        .title-box .form-name {
            margin: 0.38rem 0 0;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.15;
        }

        .reference-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0.42rem 0.5rem;
            background: #fff;
            font-size: 0.6rem;
        }

        .reference-box .meta-line {
            display: flex;
            justify-content: space-between;
            gap: 0.4rem;
            border-bottom: 1px solid var(--line);
            padding-bottom: 0.2rem;
            margin-bottom: 0.2rem;
        }

        .reference-box strong { font-weight: 700; }

        .reference-box .iso {
            margin-top: 0.2rem;
            border: 1px solid var(--line);
            padding: 0.25rem 0.3rem;
            text-align: center;
            background: #fff;
            line-height: 1.2;
        }

        .reference-box .iso strong {
            display: block;
            font-size: 0.6rem;
            margin-bottom: 0.1rem;
        }

        .label-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 260px;
            border-bottom: 2px solid var(--line);
            min-height: 48px;
        }

        .label-row > div {
            display: flex;
            align-items: flex-start;
            gap: 0.35rem;
            padding: 0.42rem 0.7rem;
            min-width: 0;
        }

        .label-row > div:first-child {
            border-right: 1px solid var(--line);
        }

        .field-label {
            display: inline-block;
            margin-top: 0.08rem;
            font-size: 0.54rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            white-space: nowrap;
            line-height: 1.3;
        }

        .field-value {
            display: block;
            flex: 1;
            min-width: 0;
            padding: 0.18rem 0.12rem 0.12rem;
            font-size: 0.7rem;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: anywhere;
            min-height: 1.1rem;
            text-align: left;
            margin-top: 0.04rem;
        }

        .label-row .field-value,
        .meta-grid .field-value,
        .schedule-row .field-value {
            border-bottom: none;
        }

        .purpose-row .field-value {
            border-bottom: none;
        }

        .other-specify .field-value {
            border-bottom: 1px solid var(--line);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .meta-grid > div {
            padding: 0.38rem 0.7rem;
            display: flex;
            align-items: flex-start;
            min-height: 46px;
        }

        .meta-grid > div:first-child {
            border-right: 1px solid var(--line);
        }

        .purpose-row {
            padding: 0.48rem 0.7rem;
            border-bottom: 2px solid var(--line);
        }

        .purpose-row .field-label {
            display: block;
            margin-bottom: 0.18rem;
            line-height: 1.3;
        }

        .purpose-row .field-value {
            min-height: 1.2rem;
            padding: 0.18rem 0.12rem 0.12rem;
            line-height: 1.25;
            border-bottom: 1px solid var(--line);
        }

        .schedule-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .schedule-row > div {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            padding: 0.42rem 0.7rem;
            min-height: 46px;
        }

        .schedule-row > div:first-child {
            border-right: 1px solid var(--line);
        }

        .inclusive-row {
            padding: 0.42rem 0.7rem;
            min-height: 46px;
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            border-bottom: 2px solid var(--line);
        }

        .section-box {
            border-bottom: 2px solid var(--line);
            padding: 0.54rem 0.7rem 0.45rem;
        }

        .section-box .title {
            display: block;
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 0.35rem;
        }

        .check-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.2rem 0.55rem;
            font-size: 0.6rem;
            align-items: start;
        }

        .check-item {
            display: flex;
            align-items: flex-start;
            gap: 0.38rem;
            min-height: 1.05rem;
        }

        .check-item input {
            margin-top: 0.08rem;
            width: 0.7rem;
            height: 0.7rem;
            accent-color: #111827;
        }

        .check-item span { line-height: 1.15; }

        .other-specify {
            margin-top: 0.35rem;
            padding-left: 1rem;
            font-size: 0.56rem;
            display: block;
        }

        .other-specify .field-value {
            min-height: 0.9rem;
            font-size: 0.56rem;
            padding: 0.08rem 0.12rem 0.1rem;
            line-height: 1.2;
            border-bottom: 1px solid var(--line);
        }

        .approval-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid var(--line);
        }

        .approval-panel {
            padding: 0.58rem 0.7rem;
            min-height: 86px;
        }

        .approval-panel:first-child {
            border-right: 1px solid var(--line);
        }

        .approval-panel .title {
            display: block;
            font-size: 0.54rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.32rem;
        }

        .approval-list {
            font-size: 0.56rem;
            line-height: 1.4;
            color: var(--muted);
            padding-top: 0.08rem;
        }

        .approval-list strong {
            font-weight: 700;
            color: var(--ink);
        }

        .signature-block {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 86px;
            padding: 0.6rem 0.7rem 0.45rem;
            border-bottom: 1px solid var(--line);
        }

        .signature-block .field-label {
            margin-bottom: 0.3rem;
            display: block;
        }

        .signature-line {
            border-bottom: 1px solid var(--line);
            height: 1.9rem;
            width: 100%;
            margin-bottom: 0.2rem;
        }

        .e-signature-image {
            max-width: 100%;
            max-height: 1.8rem;
            object-fit: contain;
            margin-bottom: 0.1rem;
        }

        .signature-name {
            font-size: 0.68rem;
            font-weight: 700;
            min-height: 0.9rem;
        }

        .footer-bar {
            font-size: 0.54rem;
            letter-spacing: 0.08em;
            text-align: right;
            padding: 0.3rem 0.45rem;
            color: var(--muted);
            background: #fff;
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

            @page {
                size: auto;
                margin: 6mm 6mm 6mm 6mm;
            }

            .print-settings-helper {
                display: none !important;
            }

            * {
                margin: 0;
                padding: 0;
            }

            html, body {
                width: 100%;
                margin: 0;
                padding: 0;
                background: #fff;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 8.5pt;
                line-height: 1.2;
            }

            body {
                display: block;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-shell {
                width: 100%;
                margin: 0;
                padding: 0;
                display: block;
            }

            .sheet-stack {
                width: 100%;
                margin: 0;
                padding: 0;
                display: block;
            }

            .copy-form {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-inside: avoid;
                break-inside: avoid;
                border: 0.75pt solid var(--line);
                background: #fff;
                display: block;
                box-sizing: border-box;
            }

            .copy-separator {
                width: 100%;
                height: 2mm;
                border: none;
                margin: 0;
                padding: 0;
                display: block;
                page-break-inside: avoid;
            }

            .form-body {
                display: block;
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .header-row {
                display: grid;
                grid-template-columns: 22mm 1fr 42mm;
                border-bottom: 0.75pt solid var(--line);
                width: 100%;
                box-sizing: border-box;
            }

            .logo-box {
                display: flex;
                align-items: center;
                justify-content: center;
                border-right: 0.75pt solid var(--line);
                background: #fff;
                padding: 1.2mm;
                box-sizing: border-box;
            }

            .logo-box img {
                width: 16mm;
                height: 16mm;
                object-fit: contain;
                border: none;
                border-radius: 0;
            }

            .title-box {
                display: flex;
                align-items: center;
                justify-content: center;
                border-right: 0.75pt solid var(--line);
                padding: 1.3mm 2mm;
                text-align: center;
                box-sizing: border-box;
            }

            .title-box div {
                width: 100%;
            }

            .title-box .small-tag {
                display: block;
                font-size: 6.5pt;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 0.5mm;
                font-weight: 700;
                line-height: 1.1;
            }

            .title-box .institution {
                font-size: 8pt;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                margin: 0 0 0.5mm 0;
                line-height: 1.05;
            }

            .title-box .qms {
                display: block;
                font-size: 6pt;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                font-weight: 700;
                margin: 0.5mm 0 0 0;
                line-height: 1.05;
            }

            .title-box .form-name {
                margin: 1mm 0 0 0;
                font-size: 7pt;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.01em;
                line-height: 1.1;
            }

            .reference-box {
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 1.2mm 2mm;
                font-size: 6.5pt;
                box-sizing: border-box;
            }

            .reference-box .meta-line {
                padding-bottom: 0.25mm;
                margin-bottom: 0.25mm;
                font-size: 6.5pt;
            }

            .reference-box .iso {
                padding: 0.4mm 0;
                font-size: 5.8pt;
                line-height: 1.05;
            }

            .reference-box .iso strong {
                font-size: 6.4pt;
                font-weight: 700;
            }

            .label-row,
            .meta-grid,
            .schedule-row,
            .approval-row {
                min-height: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .label-row,
            .meta-grid,
            .schedule-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                border-bottom: 0.75pt solid var(--line);
                gap: 0;
            }

            .label-row > div,
            .meta-grid > div,
            .schedule-row > div {
                padding: 0.7mm 1.8mm;
                box-sizing: border-box;
                border-right: 0.75pt solid var(--line);
            }

            .label-row > div:last-child,
            .meta-grid > div:last-child,
            .schedule-row > div:last-child {
                border-right: none;
            }

            .inclusive-row {
                display: block;
                width: 100%;
                padding: 0.7mm 1.8mm;
                box-sizing: border-box;
                border-bottom: 0.75pt solid var(--line);
            }

            .field-label {
                font-size: 7.5pt;
                font-weight: 700;
                margin: 0;
                display: block;
            }

            .field-value {
                font-size: 8.8pt;
                margin: 0;
                padding: 0.2mm 0 0 0;
                display: block;
                line-height: 1.15;
            }

            .purpose-row {
                display: block;
                width: 100%;
                padding: 0.7mm 1.8mm;
                box-sizing: border-box;
                border-bottom: 0.75pt solid var(--line);
            }

            .purpose-row .field-label,
            .purpose-row .field-value {
                display: inline;
            }

            .purpose-row .field-label::after {
                content: " ";
            }

            .section-box {
                display: block;
                width: 100%;
                padding: 1.1mm 1.8mm;
                box-sizing: border-box;
                border-bottom: 0.75pt solid var(--line);
                page-break-inside: avoid;
            }

            .section-box .title {
                font-size: 8.4pt;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 0.9mm;
                display: block;
            }

            .check-grid,
            .plain-list {
                display: flex;
                flex-wrap: wrap;
                gap: 1.5mm 4mm;
                font-size: 8pt;
            }

            .check-item,
            .plain-list span {
                display: inline;
                white-space: normal;
                line-height: 1.25;
            }

            .check-item input {
                display: none;
            }

            .check-item span {
                font-size: 8pt;
            }

            .other-specify {
                margin-top: 0.7mm;
                display: block;
                padding-top: 0.4mm;
            }

            .other-specify .field-label,
            .other-specify .field-value {
                font-size: 7.5pt;
            }

            .signature-block {
                display: block;
                width: 100%;
                padding: 0.9mm 1.8mm;
                box-sizing: border-box;
                border-bottom: 0.75pt solid var(--line);
                page-break-inside: avoid;
            }

            .signature-block .field-label {
                font-size: 8pt;
                font-weight: 700;
                margin-bottom: 0.7mm;
                display: block;
                text-align: center;
            }

            .signature-line {
                height: 3.5mm;
                border-bottom: 0.5pt solid var(--line);
                margin: 0 auto 0.7mm auto;
                width: 60%;
            }

            .signature-name {
                font-size: 8.2pt;
                line-height: 1.1;
                margin: 0.25mm 0 0 0 !important;
                display: block;
                text-align: center;
            }

            .signature-block > div:nth-child(4) {
                margin-top: 0 !important;
                font-weight: 600;
                font-size: 8.1pt;
                text-align: center;
            }

            .signature-block > div:last-child {
                margin-top: 0.3mm !important;
                font-size: 5.8pt;
                color: var(--muted);
                font-weight: 400;
                text-align: center;
            }

            .approval-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                border-bottom: 0.75pt solid var(--line);
                width: 100%;
                box-sizing: border-box;
                height: auto;
            }

            .approval-row:last-of-type {
                border-bottom: none;
            }

            .approval-panel {
                min-height: 0;
                height: auto;
                padding: 1.1mm 1.8mm;
                display: block;
                border-right: 0.75pt solid var(--line);
                box-sizing: border-box;
                page-break-inside: avoid;
                overflow: visible;
            }

            .approval-panel:last-child {
                border-right: none;
            }

            .approval-panel .title {
                font-size: 7.4pt;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                margin-bottom: 0.7mm;
                display: block;
                line-height: 1.05;
            }

            .approval-list {
                font-size: 7.8pt;
                line-height: 1.2;
                display: block;
            }

            .approval-list > div {
                margin-bottom: 0.3mm;
            }

            .footer-bar {
                font-size: 6.5pt;
                letter-spacing: 0.04em;
                text-align: right;
                padding: 0.8mm 1.5mm;
                color: var(--muted);
                background: #fff;
                border-top: 0.75pt solid var(--line);
                display: block;
                width: 100%;
                box-sizing: border-box;
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
            || ((string) ($request->start_time ?? '') === '08:00' && (string) ($request->end_time ?? '') === '00:00')
            || ((string) ($request->start_time ?? '') === '00:00' && (string) ($request->end_time ?? '') === '23:59');
        $scheduleTime = $endTime ? $startTime . ' - ' . $endTime : $startTime;
        $displayScheduleTime = $wholeDayRequested ? 'WHOLE DAY (12:00 AM – 11:59 PM)' : $scheduleTime;

        $requesterName = $request->requested_by ?? $request->requester?->name ?? 'N/A';
        $requesterPosition = $request->requested_by_position ?? $request->requester?->position ?? 'N/A';
        $venueApprover = $request->getStageApproverName('venue') ?: 'Pending';
        $equipmentApprover = $request->getStageApproverName('equipment') ?: 'Pending';
        $venueSignatureRecorded = $request->venue_status === 'approved' && !empty($request->venue_approval_signature);
        $equipmentSignatureRecorded = $request->equipment_status === 'approved' && !empty($request->equipment_approval_signature);
        $statusLabel = match ($request->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'needs_reschedule' => 'Needs Reschedule',
            default => 'Pending',
        };
    @endphp

    <div class="print-settings-helper" aria-live="polite">
        <div class="print-settings-box">
            <div class="print-settings-title">PRINT SETTINGS</div>
            <div class="print-settings-row"><span>A4</span><span>→</span><span>97%</span></div>
            <div class="print-settings-row"><span>Letter</span><span>→</span><span>91%</span></div>
            <div class="print-settings-row"><span>Folio</span><span>→</span><span>107%</span></div>
            <div class="print-settings-note">Select the same paper size in Chrome Print Preview and set the matching scale.</div>
        </div>
    </div>

    <div class="print-shell">
        <div class="sheet-stack">
            @foreach ([1, 2] as $copy)
                <div class="copy-form" aria-label="Official facility request form">
                    <span class="copy-label">COPY {{ $copy }}</span>
                    <div class="form-body">
                        <div class="header-row">
                            <div class="logo-box">
                                <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo">
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

                        @if (!empty($request->department))
                            <div class="label-row">
                                <div>
                                    <span class="field-label">Dept. / Requisitioning Office:</span>
                                    <span class="field-value">{{ $request->department }}</span>
                                </div>
                                <div>
                                    <span class="field-label">Control Number:</span>
                                    <span class="field-value">{{ $request->control_number }}</span>
                                </div>
                            </div>
                        @else
                            <div class="label-row">
                                <div style="grid-column: 2;">
                                    <span class="field-label">Control Number:</span>
                                    <span class="field-value">{{ $request->control_number }}</span>
                                </div>
                            </div>
                        @endif

                        @if ($request->date_requested)
                            <div class="meta-grid">
                                <div>
                                    <span class="field-label">Date Requested:</span>
                                    <span class="field-value">{{ \Carbon\Carbon::parse($request->date_requested)->format('M j, Y') }}</span>
                                </div>
                                <div>
                                    <span class="field-label">Status:</span>
                                    <span class="field-value">{{ $statusLabel }}</span>
                                </div>
                            </div>
                        @else
                            <div class="meta-grid">
                                <div style="grid-column: 2;">
                                    <span class="field-label">Status:</span>
                                    <span class="field-value">{{ $statusLabel }}</span>
                                </div>
                            </div>
                        @endif

                        @if (!empty($request->name_of_activity))
                            <div class="purpose-row">
                                <span class="field-label">Activity / Purpose:</span>
                                <span class="field-value">{{ $request->name_of_activity }}</span>
                            </div>
                        @endif

                        @if (!empty($request->expected_participants) || !empty($request->start_time))
                            <div class="schedule-row">
                                @if (!empty($request->expected_participants))
                                    <div>
                                        <span class="field-label">Expected No. of Participants:</span>
                                        <span class="field-value">{{ $request->expected_participants }}</span>
                                    </div>
                                @endif
                                @if (!empty($request->start_time))
                                    <div>
                                        <span class="field-label">Time:</span>
                                        <span class="field-value">{{ $displayScheduleTime }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if (!empty($request->start_date) && $inclusiveDates !== 'N/A')
                            <div class="inclusive-row">
                                <span class="field-label">Inclusive Dates:</span>
                                <span class="field-value">{{ $inclusiveDates }}</span>
                            </div>
                        @endif

                        @if (!empty($venueNames) || !empty($request->other_venue))
                            <div class="section-box">
                                <span class="title">Facility / Venue</span>
                                <div class="plain-list">
                                    @foreach ($venueNames as $venueName)
                                        <span>{{ trim($venueName) }}</span>
                                    @endforeach
                                    @if (!empty($request->other_venue))
                                        <span>{{ trim($request->other_venue) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (!empty($selectedEquipment) || !empty($request->other_equipment))
                            <div class="section-box">
                                <span class="title">Equipment</span>
                                <div class="plain-list">
                                    @foreach ($selectedEquipment as $equipmentName)
                                        @php $equipmentQty = (int) ($equipmentQuantities[$equipmentName] ?? 0); @endphp
                                        @if ($equipmentQty > 0)
                                            <span>{{ trim($equipmentName) }} ({{ $equipmentQty }})</span>
                                        @elseif (trim($equipmentName) !== '')
                                            <span>{{ trim($equipmentName) }}</span>
                                        @endif
                                    @endforeach
                                    @if (!empty($request->other_equipment))
                                        <span>{{ trim($request->other_equipment) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="signature-block">
                            <span class="field-label">Requisitioner</span>
                            @if($request->e_signature_file)
                                <img src="{{ route('request.signature', ['id' => $request->id]) }}"
                                     alt="E-Signature" class="e-signature-image">
                            @else
                                <div class="signature-line"></div>
                            @endif
                            <div class="signature-name">{{ $requesterName }}</div>
                            <div class="signature-name" style="font-weight: 600; margin-top: 0.3rem;">{{ $requesterPosition }}</div>
                            <div style="font-size: 0.52rem; letter-spacing: 0.12em; text-transform: uppercase; margin-top: 0.4rem; color: var(--muted);">
                                @if($request->e_signature_file)Electronic Signature@else Signature over printed name @endif
                            </div>
                        </div>

                        <div class="approval-row">
                            <div class="approval-panel">
                                <span class="title">Recommending Approval (Venue)</span>
                                <div class="approval-list">
                                    @if($venueSignatureRecorded)
                                        <div>{{ $venueApprover }}</div>
                                        <div>Electronic signature recorded</div>
                                    @else
                                        <div>Pending</div>
                                    @endif
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
                                    @if($equipmentSignatureRecorded)
                                        <div>{{ $equipmentApprover }}</div>
                                        <div>Electronic signature recorded</div>
                                    @else
                                        <div>Pending</div>
                                    @endif
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

    <script>
        window.PIT_PRINT_PRESET = {
            supportsNativeScaleOverride: false,
            scales: {
                A4: 97,
                Letter: 91,
                Folio: 107,
            },
            recommended: function (paperSize) {
                return this.scales[paperSize] ?? null;
            }
        };

        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 200);
        });
    </script>
</body>
</html>
