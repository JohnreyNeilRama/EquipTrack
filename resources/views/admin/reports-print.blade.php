{{-- Print-ready report. Opened by Export PDF / Print Report; the browser's print dialog can save it as a PDF. --}}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - {{ $report['title'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}?v={{ filemtime(public_path('images/favicon.png')) }}">
    <style>
        @page { size: A4 landscape; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #333333;
            background: #ffffff;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #eff4fa;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            color: #385585;
        }

        .toolbar span { margin-right: auto; }

        .toolbar button {
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            background: #385585;
            color: #ffffff;
        }

        .toolbar button.secondary {
            background: #ffffff;
            color: #385585;
            border: 1px solid #e2e8f0;
        }

        .doc-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 14px;
            border-bottom: 3px solid #385585;
            margin-bottom: 14px;
        }

        .doc-header img { height: 44px; width: auto; }

        .doc-header h1 {
            margin: 0;
            font-size: 22px;
            color: #385585;
            letter-spacing: -0.3px;
        }

        .doc-header p { margin: 2px 0 0; color: #666666; font-size: 12px; }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px 24px;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .meta div span { color: #666666; }
        .meta div strong { color: #333333; }

        .breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        .breakdown .chip {
            padding: 4px 10px;
            border-radius: 20px;
            background: #eff4fa;
            color: #385585;
            font-weight: 600;
            font-size: 11.5px;
        }

        table { width: 100%; border-collapse: collapse; }

        thead { display: table-header-group; }

        th {
            background: #eff4fa;
            color: #385585;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 10.5px;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
        }

        td {
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }

        tr { page-break-inside: avoid; }

        tbody tr:nth-child(even) td { background: #f8fafc; }

        .num { text-align: center; }

        .status { font-weight: 700; }
        .status.returned, .status.available, .status.high { color: #059669; }
        .status.moderate { color: #2563eb; }
        .status.low { color: #d97706; }
        .status.not-used { color: #64748b; }
        .status.borrowed, .status.approved { color: #2563eb; }
        .status.pending, .status.returned-late, .status.damaged, .status.incomplete, .status.on-hold { color: #d97706; }
        .status.overdue, .status.rejected, .status.lost, .status.unavailable { color: #dc2626; }
        .status.under-maintenance { color: #4f46e5; }

        .empty {
            text-align: center;
            padding: 36px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            color: #666666;
        }

        .foot {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            color: #666666;
            font-size: 11px;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <span>
            @if ($format === 'pdf')
                To save as a PDF, choose <strong>Save as PDF</strong> as the destination in the print dialog.
            @else
                The print dialog opens automatically. You can also print again with the button.
            @endif
        </span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <button type="button" class="secondary" onclick="window.close()">Close</button>
    </div>

    <header class="doc-header">
        <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
        <div>
            <h1>{{ $report['title'] }}</h1>
            <p>Equipment reservation and asset tracking</p>
        </div>
    </header>

    <section class="meta">
        <div><span>Generated by:</span> <strong>{{ $generatedBy }}</strong></div>
        <div><span>Generated on:</span> <strong>{{ $generatedAt }}</strong></div>
        <div><span>Filters:</span> <strong>{{ $filtersText }}</strong></div>
        @if (!empty($report['note']))
            <div><span>Note:</span> <strong>{{ $report['note'] }}</strong></div>
        @endif
        <div><span>Total records:</span> <strong>{{ $report['total'] }}</strong></div>
    </section>

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
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th @class(['num' => $column['kind'] === 'num'])>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        @foreach ($columns as $column)
                            @php $value = $row[$column['key']] ?? ''; @endphp
                            @if ($column['kind'] === 'status')
                                <td><span class="status {{ \Illuminate\Support\Str::slug($value) }}">{{ $value }}</span></td>
                            @else
                                <td @class(['num' => $column['kind'] === 'num'])>{{ $value === '' ? '—' : $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        <span>EquipTrack - {{ $report['title'] }}</span>
        <span>{{ $report['total'] }} {{ \Illuminate\Support\Str::plural('record', $report['total']) }}</span>
    </div>

    @if ($report['total'] > 0)
        <script>
            // Print once the logo has loaded so it appears in the output.
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 300);
            });
        </script>
    @endif
</body>
</html>
