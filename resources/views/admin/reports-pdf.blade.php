{{-- Downloadable PDF of a report (Export PDF), rendered by DomPDF. Same content
     and look as the print view (admin/reports-print.blade.php), written with
     tables and simple CSS because DomPDF does not support flexbox or grid.
     The table header and the footer (title, "Page X of Y") repeat on every
     page. DejaVu Sans covers ₱ and —. --}}
@php
    $columns = $report['columns'];
    $statusColumn = collect($columns)->firstWhere('kind', 'status');
    $breakdown = $statusColumn
        ? collect($report['rows'])->groupBy($statusColumn['key'])->map->count()->sortDesc()
        : collect();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EquipTrack - {{ $report['title'] }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #333333;
        }

        /* Header: logo, title, subtitle, blue rule */
        .doc-header { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #385585; margin-bottom: 10px; }
        .doc-header td { border: none; padding: 0 0 10px 0; vertical-align: middle; }
        .doc-header .logo-cell { width: 168px; padding-right: 14px; }
        /* The logo is white (made for the dark sidebar): show it on the sidebar's navy */
        .logo-panel { background: #2A3F63; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .logo-panel img { height: 26px; }
        .doc-header h1 { margin: 0; font-size: 17px; color: #385585; }
        .doc-header p { margin: 2px 0 0; color: #666666; font-size: 9.5px; }
        .doc-header .stamp { text-align: right; color: #666666; font-size: 9px; }

        /* Details: label / value pairs in two columns */
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { border: none; padding: 2px 16px 2px 0; vertical-align: top; width: 50%; font-size: 9.5px; }
        .meta .label { color: #666666; }
        .meta .value { color: #222222; font-weight: bold; }
        .note {
            margin: 0 0 8px; padding: 6px 8px; font-size: 9px; color: #555555;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;
        }

        /* Status summary chips */
        .breakdown { margin: 0 0 10px; }
        .chip {
            display: inline-block; margin: 0 6px 4px 0; padding: 3px 9px;
            border-radius: 10px; background: #eff4fa; color: #385585;
            font-weight: bold; font-size: 9px;
        }

        /* Report table */
        table.report { width: 100%; border-collapse: collapse; }
        table.report thead { display: table-header-group; }
        table.report th {
            background: #385585; color: #ffffff; text-align: left;
            font-size: 8.5px; font-weight: bold; text-transform: uppercase;
            padding: 6px 6px; border: 1px solid #2f477a;
        }
        table.report td { padding: 5px 6px; border: 1px solid #cbd5e1; vertical-align: top; word-wrap: break-word; }
        table.report tr { page-break-inside: avoid; }
        table.report tbody tr.even td { background: #f5f8fc; }
        .num { text-align: center; }

        .status { font-weight: bold; }
        .status.returned, .status.available, .status.high, .status.settled { color: #059669; }
        .status.moderate, .status.borrowed, .status.approved, .status.ready-for-pickup { color: #2563eb; }
        .status.low, .status.pending, .status.returned-late, .status.damaged, .status.incomplete, .status.on-hold { color: #d97706; }
        .status.overdue, .status.rejected, .status.lost, .status.unavailable, .status.not-claimed { color: #dc2626; }
        .status.under-maintenance { color: #4f46e5; }
        .status.not-used, .status.waived, .status.cancelled, .status.expired, .status.reservation-cancelled { color: #64748b; }

        .empty {
            text-align: center; padding: 30px 12px; color: #666666;
            border: 1px dashed #cbd5e1; border-radius: 6px;
        }

        .total { margin-top: 8px; text-align: right; font-size: 9px; color: #666666; }

        /* Footer on every page (fixed elements repeat on each page) */
        .page-footer {
            position: fixed; left: 0; right: 0; bottom: -9mm; height: 6mm;
            font-size: 8px; color: #666666; border-top: 1px solid #e2e8f0; padding-top: 4px;
        }
        .page-footer .right { position: absolute; right: 0; top: 4px; }
        .page-footer .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <div class="page-footer">
        EquipTrack - {{ $report['title'] }}
        <span class="right">Page <span class="pagenum"></span>@if ($totalPages) of {{ $totalPages }}@endif</span>
    </div>

    <table class="doc-header">
        <tr>
            @if ($logo)
                <td class="logo-cell"><div class="logo-panel"><img src="{{ $logo }}" alt="EquipTrack"></div></td>
            @endif
            <td>
                <h1>{{ $report['title'] }}</h1>
                <p>EquipTrack · Equipment reservation and asset tracking</p>
            </td>
            <td class="stamp">Generated {{ $generatedAt }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td><span class="label">Generated by:</span> <span class="value">{{ $generatedBy }}</span></td>
            <td><span class="label">Generated on:</span> <span class="value">{{ $generatedAt }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Filters:</span> <span class="value">{{ $filtersText }}</span></td>
            <td><span class="label">Total records:</span> <span class="value">{{ $report['total'] }}</span></td>
        </tr>
    </table>

    @if (!empty($report['note']))
        <p class="note"><strong>Note:</strong> {{ $report['note'] }}</p>
    @endif

    @if ($breakdown->isNotEmpty())
        <div class="breakdown">
            @foreach ($breakdown as $label => $count)
                <span class="chip">{{ $label }}: {{ $count }}</span>
            @endforeach
        </div>
    @endif

    @if ($report['total'] === 0)
        <div class="empty">No records match the selected filters.</div>
    @else
        <table class="report">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th @class(['num' => $column['kind'] === 'num'])>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr @class(['even' => $loop->even])>
                        @foreach ($columns as $column)
                            @php $value = $row[$column['key']] ?? ''; @endphp
                            @if ($column['kind'] === 'status')
                                <td><span class="status {{ \Illuminate\Support\Str::slug($value) }}">{{ $value === '' ? '—' : $value }}</span></td>
                            @else
                                <td @class(['num' => $column['kind'] === 'num'])>{{ $value === '' ? '—' : $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="total">{{ $report['total'] }} {{ \Illuminate\Support\Str::plural('record', $report['total']) }}</p>
    @endif
</body>
</html>
