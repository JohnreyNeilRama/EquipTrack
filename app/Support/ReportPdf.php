<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * A report as a PDF file download (Export PDF on Admin Reports and Audit
 * Trail), drawn with admin/reports-pdf.blade.php: A4 landscape, the table
 * header repeated on every page, and "EquipTrack - title" with "Page X of N"
 * at the foot of each page.
 */
class ReportPdf
{
    /** 12mm side margin (@page in the template), in points. */
    private const SIDE_MARGIN_PT = 34.0;

    /**
     * Top of the "Page X of N" text, from the bottom of the page, in points:
     * on the same baseline as the footer title (.page-footer in the template).
     */
    private const FOOTER_TOP_PT = 36.5;

    /** The footer title's size: 8px in the template is 6pt. */
    private const FOOTER_SIZE_PT = 6.0;

    /**
     * Keeps a report small enough for DomPDF, which builds the whole document
     * in memory: only the first $limit rows are listed, with a note saying
     * so. The total and the status counts at the top still cover every row.
     * $limit <= 0 lists everything.
     */
    public static function capRows(array $report, int $limit): array
    {
        if ($limit <= 0 || count($report['rows']) <= $limit) {
            return $report;
        }

        $statusColumn = collect($report['columns'])->firstWhere('kind', 'status');
        if ($statusColumn) {
            $report['breakdown'] = collect($report['rows'])->groupBy($statusColumn['key'])->map->count()->sortDesc()->all();
        }

        $report['rows'] = array_slice($report['rows'], 0, $limit);
        $report['note'] = 'Only the first ' . number_format($limit) . ' of ' . number_format((int) $report['total'])
            . ' records are listed. Narrow the dates or filters, or use Export CSV for every record.';

        return $report;
    }

    /**
     * @param  array  $report  title, columns, rows, total and optional note
     *                         (the shape admin/reports-print.blade.php reads);
     *                         a column may set pdfWidth (a % of the table)
     */
    public static function download(array $report, string $filtersText, string $generatedAt, string $generatedBy, string $filename): Response
    {
        $logoPath = public_path('images/EquipTrack_logo.png');
        $data = [
            'report' => $report,
            'filtersText' => $filtersText,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'logo' => is_file($logoPath) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath)) : null,
        ];

        $pdf = Pdf::loadView('admin.reports-pdf', $data)
            ->setPaper('a4', 'landscape')
            // Embed only the characters used, not the whole font (keeps the file small).
            ->setOption('isFontSubsettingEnabled', true);

        // "Page X of N" at the right end of the footer line, on every page
        // (CSS can't give DomPDF the page total).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $dompdf->getCanvas()->page_script(function (int $page, int $pages, $canvas, $metrics) use ($font) {
            $text = "Page {$page} of {$pages}";
            $x = $canvas->get_width() - self::SIDE_MARGIN_PT - $metrics->getTextWidth($text, $font, self::FOOTER_SIZE_PT);
            $canvas->text($x, $canvas->get_height() - self::FOOTER_TOP_PT, $text, $font, self::FOOTER_SIZE_PT, [0.4, 0.4, 0.4]);
        });

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
