<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\PenaltySubmissionFile;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The daily cleanup (equiptrack:prune-old-records): old audit entries are
 * archived to CSV before removal, and proof files of long-closed penalties
 * are deleted while the proof record stays.
 */
class PruneOldRecordsTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->archive = storage_path('framework/testing/archives-' . uniqid());
        config([
            'equiptrack.retention.audit_keep_days' => 730,
            'equiptrack.retention.proof_keep_days' => 365,
            'equiptrack.retention.archive_path' => $this->archive,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->archive);

        parent::tearDown();
    }

    private function auditRow(string $action, string $createdAt, array $extra = []): AuditTrail
    {
        $row = AuditTrail::create($extra + [
            'action' => $action,
            'ip_address' => '127.0.0.1',
            'affected_entity' => 'equipment:1',
        ]);
        $row->created_at = $createdAt;
        $row->save();

        return $row;
    }

    /** A penalty with one proof file on the fake private disk. */
    private function proofFile(UserAccount $user, string $status, ?string $settledAt, string $name): PenaltySubmissionFile
    {
        static $loans = 0;
        $loans++;

        $loan = $this->makeLoan($user, $this->makeEquipment($user->department, ['name' => 'Projector ' . $loans]), '2026-10-25');
        $penalty = Penalty::create([
            'transaction_id' => $loan->transaction_id,
            'user_id' => $user->user_id,
            'type' => Penalty::TYPE_DAMAGED,
            'amount' => 500,
            'description' => 'Cracked lens',
            'status' => $status,
            'recorded_at' => '2024-01-01 09:00:00',
            'settled_at' => $settledAt,
        ]);
        $submission = PenaltySubmission::create([
            'penalty_id' => $penalty->penalty_id,
            'user_id' => $user->user_id,
            'type' => PenaltySubmission::TYPE_PAYMENT,
            'reference_no' => 'OR-' . $loans,
            'amount_paid' => 500,
            'settled_on' => '2024-01-02',
            'status' => PenaltySubmission::STATUS_APPROVED,
            'submitted_at' => '2024-01-02 09:00:00',
        ]);

        Storage::disk('local')->put('penalty-proofs/' . $name, 'receipt');

        return PenaltySubmissionFile::create([
            'submission_id' => $submission->submission_id,
            'path' => 'penalty-proofs/' . $name,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size' => 7,
        ]);
    }

    public function test_old_audit_entries_are_archived_to_csv_then_removed(): void
    {
        $admin = $this->makeAdmin();
        $old = $this->auditRow('Registered equipment: Projector', now()->subDays(800)->toDateTimeString(), ['admin_id' => $admin->admin_id]);
        $formula = $this->auditRow('=HYPERLINK("x")', now()->subDays(900)->toDateTimeString());
        $recent = $this->auditRow('Updated equipment: Projector', now()->subDays(10)->toDateTimeString(), ['admin_id' => $admin->admin_id]);

        $this->artisan('equiptrack:prune-old-records')->assertSuccessful();

        $this->assertDatabaseMissing('audit_trail', ['audit_id' => $old->audit_id]);
        $this->assertDatabaseMissing('audit_trail', ['audit_id' => $formula->audit_id]);
        $this->assertDatabaseHas('audit_trail', ['audit_id' => $recent->audit_id]);

        $files = File::glob($this->archive . '/audit-trail-*.csv');
        $this->assertCount(1, $files);
        $csv = file_get_contents($files[0]);
        $this->assertStringContainsString('Registered equipment: Projector', $csv);
        $this->assertStringContainsString('System Admin', $csv);
        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringNotContainsString('Updated equipment', $csv);

        // The cleanup itself is on record, as EquipTrack rather than a person.
        $entry = AuditTrail::where('action', 'Cleared old records')->sole();
        $this->assertStringContainsString('2 audit entries', $entry->details);
        $this->assertNull($entry->admin_id);
    }

    public function test_proof_files_go_a_year_after_the_penalty_closed_and_the_proof_record_stays(): void
    {
        $user = $this->makeStudent($this->makeDepartment());
        $oldSettled = $this->proofFile($user, Penalty::STATUS_SETTLED, now()->subDays(400)->toDateTimeString(), 'old-settled.jpg');
        $oldWaived = $this->proofFile($user, Penalty::STATUS_WAIVED, now()->subDays(400)->toDateTimeString(), 'old-waived.jpg');
        $recent = $this->proofFile($user, Penalty::STATUS_SETTLED, now()->subDays(30)->toDateTimeString(), 'recent.jpg');
        $pending = $this->proofFile($user, Penalty::STATUS_PENDING, null, 'pending.jpg');

        $this->artisan('equiptrack:prune-old-records')->assertSuccessful();

        Storage::disk('local')->assertMissing('penalty-proofs/old-settled.jpg');
        Storage::disk('local')->assertMissing('penalty-proofs/old-waived.jpg');
        Storage::disk('local')->assertExists('penalty-proofs/recent.jpg');
        Storage::disk('local')->assertExists('penalty-proofs/pending.jpg');

        $this->assertDatabaseMissing('penalty_submission_file', ['file_id' => $oldSettled->file_id]);
        $this->assertDatabaseMissing('penalty_submission_file', ['file_id' => $oldWaived->file_id]);
        $this->assertDatabaseHas('penalty_submission_file', ['file_id' => $recent->file_id]);
        $this->assertDatabaseHas('penalty_submission_file', ['file_id' => $pending->file_id]);
        $this->assertDatabaseHas('penalty_submission', ['submission_id' => $oldSettled->submission_id]);

        $this->assertStringContainsString('2 proof files', AuditTrail::where('action', 'Cleared old records')->sole()->details);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $old = $this->auditRow('Registered equipment: Projector', now()->subDays(800)->toDateTimeString());
        $file = $this->proofFile($this->makeStudent($this->makeDepartment()), Penalty::STATUS_SETTLED, now()->subDays(400)->toDateTimeString(), 'old.jpg');

        $this->artisan('equiptrack:prune-old-records --dry-run')
            ->expectsOutputToContain('Would archive and remove 1 audit entry and delete 1 proof file')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_trail', ['audit_id' => $old->audit_id]);
        $this->assertDatabaseHas('penalty_submission_file', ['file_id' => $file->file_id]);
        Storage::disk('local')->assertExists('penalty-proofs/old.jpg');
        $this->assertDirectoryDoesNotExist($this->archive);
    }

    public function test_zero_days_keeps_everything_and_writes_no_entry(): void
    {
        config(['equiptrack.retention.audit_keep_days' => 0, 'equiptrack.retention.proof_keep_days' => 0]);
        $old = $this->auditRow('Registered equipment: Projector', now()->subDays(5000)->toDateTimeString());
        $file = $this->proofFile($this->makeStudent($this->makeDepartment()), Penalty::STATUS_SETTLED, now()->subDays(5000)->toDateTimeString(), 'old.jpg');

        $this->artisan('equiptrack:prune-old-records')->assertSuccessful();

        $this->assertDatabaseHas('audit_trail', ['audit_id' => $old->audit_id]);
        $this->assertDatabaseHas('penalty_submission_file', ['file_id' => $file->file_id]);
        $this->assertSame(0, AuditTrail::where('action', 'Cleared old records')->count());
    }

    public function test_the_audit_page_shows_the_cleanup_as_done_by_equiptrack(): void
    {
        $admin = $this->makeAdmin();
        $this->auditRow('Registered equipment: Projector', now()->subDays(800)->toDateTimeString());

        $this->artisan('equiptrack:prune-old-records')->assertSuccessful();

        $rows = $this->actingAs($admin, 'admin')->getJson(route('admin.audit.data'))->assertOk()->json('records');
        $row = collect($rows)->firstWhere('action', 'Cleared Old Records');
        $this->assertNotNull($row);
        $this->assertSame('EquipTrack', $row['user']);
        $this->assertSame('System', $row['role']);
    }

    public function test_it_runs_daily_after_the_backup(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('equiptrack:prune-old-records')->assertSuccessful();
    }
}
