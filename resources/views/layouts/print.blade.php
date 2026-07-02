{{--
    resources/views/layouts/print.blade.php
    Standalone layout for all printed documents.
    No sidebar, no nav — just content + print CSS.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print') — {{ \App\Models\AppSetting::get('clinic_name', 'Dentfluence') }}</title>

    @php
        $print   = \App\Models\AppSetting::group('print');
        $clinic  = \App\Models\AppSetting::group('clinic');

        $headerType  = $print['print_header_type'] ?? 'plain';
        $marginTop   = $print['print_margin_top']    ?? '';
        $marginBot   = $print['print_margin_bottom'] ?? '';
        $marginLeft  = $print['print_margin_left']   ?? '';
        $marginRight = $print['print_margin_right']  ?? '';

        // Build @page margin string
        $pt = $marginTop    ? "{$marginTop}in"  : '1cm';
        $pb = $marginBot    ? "{$marginBot}in"  : '1cm';
        $pl = $marginLeft   ? "{$marginLeft}in" : '1.2cm';
        $pr = $marginRight  ? "{$marginRight}in": '1.2cm';
    @endphp

    <style>
        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            'Inter', sans-serif;
            font-size: 12pt;
            color: #111;
            background: #fff;
            padding: 0;
        }

        /* ── Print page setup ── */
        @page {
            size: A4 portrait;
            margin: {{ $pt }} {{ $pr }} {{ $pb }} {{ $pl }};
        }

        /* ── Screen preview wrapper ── */
        .print-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px 32px;
            background: #fff;
        }

        /* ── Print header styles ── */
        .print-header-logo {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #6a0f70;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .print-header-logo img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .print-header-logo .clinic-name {
            font-size: 18pt;
            font-weight: 700;
            color: #3a0050;
        }
        .print-header-logo .clinic-sub {
            font-size: 9pt;
            color: #666;
            margin-top: 2px;
        }
        .print-header-letterhead {
            width: 100%;
            display: block;
            margin-bottom: 16px;
        }

        /* ── Document body ── */
        .print-section {
            margin-bottom: 18px;
        }
        .print-section-title {
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6a0f70;
            border-bottom: 1px solid #ede4f3;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .print-row {
            display: flex;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 11pt;
        }
        .print-label {
            font-weight: 600;
            min-width: 130px;
            color: #333;
            flex-shrink: 0;
        }
        .print-value {
            color: #111;
            flex: 1;
        }
        table.print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5pt;
            margin-top: 6px;
        }
        table.print-table th {
            background: #f3eef7;
            color: #3a0050;
            font-weight: 700;
            padding: 6px 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table.print-table td {
            padding: 6px 10px;
            border: 1px solid #eee;
            vertical-align: top;
        }
        table.print-table tr:nth-child(even) td {
            background: #faf7fc;
        }

        /* ── Footer ── */
        .print-footer {
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            color: #888;
        }
        .print-signature {
            text-align: right;
            margin-top: 40px;
        }
        .print-signature .sig-line {
            border-top: 1px solid #333;
            display: inline-block;
            width: 160px;
            margin-bottom: 4px;
        }
        .print-signature .sig-name {
            font-size: 10pt;
            font-weight: 600;
            color: #3a0050;
        }

        /* ── Screen-only: toolbar ── */
        .no-print {
            background: #1a0320;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .no-print button {
            background: #8b44aa;
            color: #fff;
            border: none;
            padding: 7px 18px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        .no-print a {
            color: #d4b8e0;
            text-decoration: none;
            font-size: 13px;
        }

        /* ── Hide toolbar when printing ── */
        @media print {
            .no-print { display: none !important; }
            .print-page { padding: 0; max-width: 100%; }
            body { font-size: 10.5pt; }
        }
    </style>

    @stack('print-styles')
</head>
<body>

{{-- Screen toolbar --}}
<div class="no-print">
    <a href="{{ url()->previous() }}">← Back</a>
    <span style="font-size:13px;color:#d4b8e0;">@yield('title', 'Document')</span>
    <button onclick="window.print()">Print</button>
</div>

<div class="print-page">

    {{-- ── Shared Header Partial ── --}}
    @include('partials.print-header', ['print' => $print, 'clinic' => $clinic])

    {{-- ── Page content ── --}}
    @yield('content')

</div>

</body>
</html>
