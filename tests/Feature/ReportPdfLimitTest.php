<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Support\ReportPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Admin Reports "Export PDF" lists at most equiptrack.report_pdf_limit rows:
 * DomPDF builds the whole file in memory and a very large one crashed.
 */
class ReportPdfLimitTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private function report(array $statuses): array
    {
        return [
            'title' => 'Borrowing Report',
            'columns' => [
                ['key' => 'name', 'label' => 'Name', 'kind' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'kind' => 'status'],
            ],
            'rows' => array_map(fn ($status, $i) => ['name' => 'Row ' . $i, 'status' => $status], $statuses, array_keys($statuses)),
            'total' => count($statuses),
        ];
    }

    public function test_a_long_report_lists_the_first_rows_but_counts_every_row(): void
    {
        $capped = ReportPdf::capRows($this->report(['Approved', 'Rejected', 'Approved', 'Approved', 'Pending']), 2);

        $this->assertCount(2, $capped['rows']);
        $this->assertSame('Row 0', $capped['rows'][0]['name']);
        $this->assertSame(5, $capped['total']);
        $this->assertSame(['Approved' => 3, 'Rejected' => 1, 'Pending' => 1], $capped['breakdown']);
        $this->assertStringContainsString('Only the first 2 of 5 records', $capped['note']);
        $this->assertStringContainsString('Export CSV', $capped['note']);
    }

    public function test_a_short_report_or_no_limit_leaves_it_alone(): void
    {
        $report = $this->report(['Approved', 'Rejected']);

        $this->assertSame($report, ReportPdf::capRows($report, 2));
        $this->assertSame($report, ReportPdf::capRows($report, 0));
    }

    public function test_the_export_uses_the_configured_limit_and_still_downloads(): void
    {
        config(['equiptrack.report_pdf_limit' => 1]);
        $admin = $this->makeAdmin();
        $student = $this->makeStudent($this->makeDepartment());
        $equipment = $this->makeEquipment($student->department, ['classification' => Equipment::CLASS_NOT_EXPENSIVE]);
        $this->makePendingRequest($student, $equipment);
        $this->makePendingRequest($student, $equipment);
        $this->assertSame(2, BorrowRequest::count());

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.export', ['format' => 'pdf', 'type' => 'borrowing']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
