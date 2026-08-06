<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIT Facility/Equipment Request - {{ $request->control_number }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.3;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            padding: 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 12mm;
            margin: 0 auto;
        }

        .sheet {
            border: 1px solid #000;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: start;
        }

        .logo {
            width: 90px;
        }

        .header-center {
            text-align: center;
        }

        .header-center .small-label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header-center .medium-label {
            display: block;
            font-size: 0.9rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .header-center .title {
            margin: 8px 0 0;
            font-size: 1.4rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            line-height: 1.1;
        }

        .meta-box {
            border: 1px solid #000;
            padding: 8px;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .meta-box strong {
            font-weight: 700;
        }

        .meta-box .iso-box {
            margin-top: 8px;
            padding: 6px;
            border: 1px solid #000;
            text-align: center;
            font-size: 0.75rem;
        }

        .section {
            border: 1px solid #000;
            padding: 10px;
            display: grid;
            gap: 10px;
        }

        .section-title {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .field {
            border: 1px solid #000;
            padding: 8px;
            min-height: 52px;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 4px;
        }

        .field .value {
            font-size: 0.95rem;
            font-weight: 600;
            min-height: 1.4rem;
        }

        .check-table,
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        .check-table td,
        .equipment-table th,
        .equipment-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        .check-table td {
            width: 24px;
            text-align: center;
            font-weight: 700;
        }

        .equipment-table th {
            font-weight: 700;
            text-align: left;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 6px;
        }

        .signature-block {
            border-top: 1px solid #000;
            padding-top: 8px;
            min-height: 72px;
        }

        .signature-block .caption {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 4px;
        }

        .signature-block .name {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.72rem;
            padding-top: 4px;
            border-top: 1px solid #000;
        }

        .footer .footer-left {
            max-width: 70%;
            line-height: 1.3;
        }

        .footer .footer-right {
            text-align: right;
        }

        .small-note {
            font-size: 0.78rem;
            line-height: 1.25;
        }

        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }

            body {
                padding: 0;
            }

            .page {
                padding: 0;
            }

            .sheet {
                border: none;
                padding: 0;
            }

            .section {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .field,
            .check-table td,
            .equipment-table th,
            .equipment-table td,
            .signature-block {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
@php
    $selectedVenues = $request->getVenueNames();
    $selectedEquipment = $request->getEquipmentItems();
    $equipmentQuantities = $request->getEquipmentQuantities();
    $dateRequested = $request->date_requested ? \Carbon\Carbon::parse($request->date_requested)->format('M j, Y') : 'N/A';
    $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M j, Y') : 'N/A';
    $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->format('M j, Y') : $startDate;
    $startTime = $request->start_time ? \Carbon\Carbon::parse($request->start_time)->format('g:i A') : 'N/A';
    $endTime = $request->end_time ? \Carbon\Carbon::parse($request->end_time)->format('g:i A') : 'N/A';
    $priorityLabel = $request->priority === 'institutional' ? 'Institutional' : ($request->priority === 'regular' ? 'Regular' : 'Regular');
    $emergencyLabel = $request->is_emergency ? 'Yes' : 'No';
    $emergencyJustification = $request->emergency_justification ? $request->emergency_justification : 'N/A';
    $venueList = [];
    foreach ($selectedVenues as $venueName) {
        if (strtolower(trim($venueName)) === 'others' || strtolower(trim($venueName)) === 'other') {
            continue;
        }
        $venueList[] = $venueName;
    }
    if (!empty($request->other_venue)) {
        $venueList[] = 'Others: ' . $request->other_venue;
    }
    if (empty($venueList) && !empty($selectedVenues)) {
        $venueList = $selectedVenues;
    }
    $equipmentList = [];
    foreach ($selectedEquipment as $equipmentName) {
        if (strtolower(trim($equipmentName)) === 'others' || strtolower(trim($equipmentName)) === 'other') {
            continue;
        }
        $equipmentList[] = [
            'name' => $equipmentName,
            'qty' => $equipmentQuantities[$equipmentName] ?? 1,
        ];
    }
    if (!empty($request->other_equipment)) {
        $equipmentList[] = [
            'name' => 'Others: ' . $request->other_equipment,
            'qty' => $equipmentQuantities['Others'] ?? 1,
        ];
    }
@endphp
<div class="page">
    <div class="sheet">
        <header class="header">
            <div class="logo">
                <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo" class="logo">
            </div>
            <div class="header-center">
                <span class="small-label">Palompon Institute of Technology</span>
                <span class="medium-label">Quality Management System</span>
                <h1 class="title">Request for the Use of Facility / Equipment</h1>
            </div>
            <div class="meta-box">
                <div><strong>Control Number:</strong> {{ $request->control_number }}</div>
                <div><strong>Date Requested:</strong> {{ $dateRequested }}</div>
                <div class="iso-box">
                    <strong>ISO 9001:2015</strong>
                    <span>Certified</span>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="section-title">Section I. Requestor Information</div>
            <div class="field-grid">
                <div class="field field-full">
                    <label>Department / Requisitioning Office</label>
                    <div class="value">{{ $request->department ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Requestor Name</label>
                    <div class="value">{{ $request->requested_by ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Position</label>
                    <div class="value">{{ $request->requested_by_position ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Emergency</label>
                    <div class="value">{{ $emergencyLabel }}</div>
                </div>
                <div class="field">
                    <label>Priority Status</label>
                    <div class="value">{{ $priorityLabel }}</div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">Section II. Activity Details</div>
            <div class="field-grid">
                <div class="field field-full">
                    <label>Activity / Purpose</label>
                    <div class="value">{{ $request->name_of_activity ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Number of Participants</label>
                    <div class="value">{{ $request->expected_participants ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Start Date</label>
                    <div class="value">{{ $startDate }}</div>
                </div>
                <div class="field">
                    <label>End Date</label>
                    <div class="value">{{ $endDate }}</div>
                </div>
                <div class="field">
                    <label>Start Time</label>
                    <div class="value">{{ $startTime }}</div>
                </div>
                <div class="field">
                    <label>End Time</label>
                    <div class="value">{{ $endTime }}</div>
                </div>
            </div>
            <div class="field field-full" style="margin-top:8px;">
                <label>Emergency Justification</label>
                <div class="value">{{ $request->is_emergency ? ($request->emergency_justification ?: 'N/A') : 'Not applicable' }}</div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">Section III. Requested Venue(s)</div>
            <table class="check-table">
                <tbody>
                    @if(count($venueList) > 0)
                        @foreach($venueList as $venueItem)
                            <tr>
                                <td>✓</td>
                                <td>{{ $venueItem }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td></td>
                            <td>No venue selected.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>

        <section class="section">
            <div class="section-title">Section IV. Requested Equipment</div>
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">✓</th>
                        <th>Equipment / Item</th>
                        <th style="width: 20%;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($equipmentList) > 0)
                        @foreach($equipmentList as $equipmentItem)
                            <tr>
                                <td style="text-align:center; font-weight:700;">✓</td>
                                <td>{{ $equipmentItem['name'] }}</td>
                                <td>{{ $equipmentItem['qty'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td></td>
                            <td colspan="2">No equipment requested.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>

        <section class="section">
            <div class="section-title">Section V. Approval and Authorization</div>
            <div class="signature-grid">
                <div class="signature-block">
                    <div class="caption">Requested by</div>
                    <div class="name">{{ $request->requested_by ?? '____________________' }}</div>
                </div>
                <div class="signature-block">
                    <div class="caption">Approved by</div>
                    <div class="name">{{ $request->approved_by ?? '____________________' }}</div>
                </div>
            </div>
        </section>

        <div class="footer">
            <div class="footer-left small-note">
                Palompon Institute of Technology - Quality Management System Form<br>
                Request for the Use of Facility / Equipment
            </div>
            <div class="footer-right small-note">
                Printed on {{ now()->format('M j, Y') }}
            </div>
        </div>
    </div>
</div>
<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
</body>
</html>
