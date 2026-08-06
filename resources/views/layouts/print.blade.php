<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PIT Request Print')</title>
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
            color: #111827;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.45;
        }

        body {
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        .document-sheet {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            padding: 16mm 16mm 12mm;
            background: #fff;
            color: #111827;
        }

        .document-header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .header-logo {
            width: 92px;
            height: auto;
            border: 1px solid #cbd5e1;
            padding: 8px;
            background: #fff;
            border-radius: 0.75rem;
        }

        .header-center {
            text-align: center;
        }

        .header-subtitle {
            margin: 0;
            font-size: 0.78rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #475569;
        }

        .header-title {
            margin: 0.25rem 0 0;
            font-size: 0.92rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #0f172a;
            font-weight: 700;
        }

        .document-title {
            margin: 0.5rem 0 0;
            font-size: 1.65rem;
            line-height: 1.1;
            color: #0f172a;
        }

        .document-meta {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            font-size: 0.8rem;
            color: #334155;
            text-align: right;
            min-width: 175px;
        }

        .document-meta div {
            margin-bottom: 0.35rem;
        }

        .iso-box {
            margin-top: 0.6rem;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 0.6rem 0.75rem;
            border-radius: 0.85rem;
            display: inline-block;
            font-size: 0.72rem;
            text-align: center;
            color: #334155;
        }

        .iso-box strong {
            display: block;
            font-size: 0.88rem;
            color: #0f172a;
            margin-bottom: 0.15rem;
        }

        .document-section {
            margin-bottom: 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 1rem;
            padding: 1rem 1.05rem;
            background: #f8fafc;
        }

        .section-header {
            display: block;
            margin-bottom: 1rem;
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #475569;
            font-weight: 700;
        }

        .field-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .field label {
            font-size: 0.72rem;
            color: #475569;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin: 0;
        }

        .field .value {
            min-height: 2rem;
            font-size: 0.96rem;
            color: #0f172a;
            padding: 0.55rem 0.45rem;
            border-bottom: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 0.55rem;
        }

        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
            background: #fff;
        }

        .equipment-table th,
        .equipment-table td {
            border: 1px solid #cbd5e1;
            padding: 0.75rem 0.9rem;
            text-align: left;
            vertical-align: top;
        }

        .equipment-table th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 700;
        }

        .approval-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }

        .approval-card {
            border: 1px solid #cbd5e1;
            border-radius: 0.95rem;
            padding: 0.9rem 1rem;
            background: #fff;
        }

        .approval-label {
            margin: 0 0 0.55rem;
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #475569;
            font-weight: 700;
        }

        .approval-value {
            margin: 0 0 0.45rem;
            font-size: 1.05rem;
            color: #0f172a;
            font-weight: 700;
        }

        .approval-detail {
            margin: 0.18rem 0 0;
            font-size: 0.86rem;
            color: #475569;
        }

        .document-signature {
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .signature-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .signature-block {
            border-top: 1px solid #94a3b8;
            padding-top: 1rem;
        }

        .signature-title {
            margin: 0 0 0.6rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #475569;
            font-weight: 700;
        }

        .signature-line {
            width: 100%;
            height: 1px;
            background: #0f172a;
            margin-bottom: 0.65rem;
        }

        .signature-name {
            margin: 0;
            font-size: 0.92rem;
            color: #0f172a;
            font-weight: 600;
        }

        .signature-name.pending {
            color: #94a3b8;
            font-style: italic;
        }

        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }

            .document-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: auto;
                max-width: none;
            }

            .document-meta,
            .document-section,
            .approval-card,
            .signature-block {
                page-break-inside: avoid;
            }
        }

        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }

            .document-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: auto;
                max-width: none;
            }

            .document-meta,
            .document-section,
            .approval-card,
            .signature-block {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    @yield('content')
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
