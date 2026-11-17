<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="{{ asset('images/PIT-LOGO.png') }}">
    <title>Request Verification · PIT Supply Office</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; line-height: 1.5; }
        main { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        header img { width: 44px; height: 44px; }
        header p { margin: 0; font-size: 13px; color: #4b5563; }
        header h1 { margin: 0; font-size: 18px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .banner { border-radius: 10px; padding: 12px 14px; font-weight: 600; margin-bottom: 16px; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 6px 12px; margin: 0; font-size: 14px; }
        dt { color: #6b7280; }
        dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
        h2 { font-size: 15px; margin: 0 0 12px; }
        .record { border-top: 1px solid #f3f4f6; padding: 10px 0; font-size: 14px; }
        .record:first-of-type { border-top: 0; padding-top: 0; }
        .ref { font-family: ui-monospace, Consolas, monospace; font-size: 13px; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; }
        .muted { color: #6b7280; font-size: 13px; }
        @media (max-width: 480px) { dl { grid-template-columns: 1fr; } dt { margin-top: 6px; } }
    </style>
</head>
<body>
<main>
    <header>
        <img src="{{ asset('images/PIT-LOGO.png') }}" alt="">
        <div>
            <p>Palompon Institute of Technology · Supply Office</p>
            <h1>Request Form Verification</h1>
        </div>
    </header>

    @if (! $request)
        <div class="banner bad">This verification link is not valid.</div>
        <div class="card">
            <p style="margin:0">No request matches this code. The printed form may have been altered, or the link was copied incorrectly. Contact the Supply Office to confirm the form.</p>
        </div>
    @else
        @php
            $statusText = match ($request->status) {
                'approved' => 'Approved',
                'pending' => 'Pending — not yet fully approved',
                'needs_reschedule' => 'Pending — needs reschedule',
                'rejected' => 'Declined',
                'cancelled' => 'Cancelled',
                default => ucfirst(str_replace('_', ' ', (string) $request->status)),
            };
            $bannerClass = match ($request->status) {
                'approved' => 'ok',
                'pending', 'needs_reschedule' => 'warn',
                default => 'bad',
            };
            $start = $request->start_date?->format('M j, Y');
            $end = $request->end_date?->format('M j, Y');
            $schedule = trim(($start ?? '') . ($end && $end !== $start ? ' – ' . $end : '') . ' · '
                . ($request->start_time ? \Carbon\Carbon::parse($request->start_time)->format('g:i A') : '')
                . ($request->end_time ? ' – ' . \Carbon\Carbon::parse($request->end_time)->format('g:i A') : ''), ' ·');
        @endphp

        <div class="banner {{ $bannerClass }}">
            This form matches a request in the PIT reservation system. Current status: {{ $statusText }}.
        </div>

        <div class="card">
            <dl>
                <dt>Control number</dt><dd>{{ $request->control_number }}</dd>
                <dt>Schedule</dt><dd>{{ $schedule ?: '—' }}</dd>
                <dt>Venue</dt><dd>{{ implode(', ', $request->getVenueNames()) ?: '—' }}</dd>
                <dt>Status</dt><dd>{{ $statusText }}</dd>
            </dl>
        </div>

        <div class="card">
            <h2>Digital Approval Records</h2>
            @php $anyRecord = false; @endphp
            @foreach ($approvalRecords as $records)
                @foreach ($records as $record)
                    @php $anyRecord = true; @endphp
                    <div class="record">
                        <strong>{{ $record['action'] }} by {{ $record['role'] }}</strong>
                        @if ($record['scope'])<div class="muted">{{ $record['scope'] }}</div>@endif
                        <div class="muted">
                            {{ $record['date'] ?? 'Date not recorded' }} · Ref: <span class="ref">{{ $record['reference'] }}</span>
                        </div>
                    </div>
                @endforeach
            @endforeach
            @if (! $anyRecord)
                <p class="muted" style="margin:0">No approvals have been recorded for this request yet.</p>
            @endif
        </div>

        <p class="muted">Check that the references above match the ones printed on the form. Approvals are recorded by authenticated system accounts; they are not handwritten signatures.</p>
    @endif
</main>
</body>
</html>
