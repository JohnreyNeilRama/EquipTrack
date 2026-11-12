<?php

namespace Tests\Feature;

use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\AdminEmailChangedNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\AdminEmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The Admin's email-change protection, for Students, Faculty and Department
 * accounts too: the current password is needed to change your own email, the
 * old address gets an alert with an undo link, and the audit trail keeps the
 * old and new address.
 */
class EmailChangeProtectionTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function alertTo(string $address): ?AdminEmailChangedNotification
    {
        $found = null;
        Notification::assertSentOnDemand(AdminEmailChangedNotification::class, function ($n, $channels, AnonymousNotifiable $to) use ($address, &$found) {
            if (($to->routes['mail'] ?? null) === $address) {
                $found = $n;

                return true;
            }

            return false;
        });

        return $found;
    }

    public function test_a_student_needs_the_password_to_change_their_email_and_the_old_address_can_undo_it(): void
    {
        $department = $this->makeDepartment();
        $juan = $this->makeStudent($department, 'juan@example.com', [], 'Study2026');

        $save = fn (array $extra = []) => $this->actingAs($juan, 'user')->postJson(route('user.profile.update'), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'hacker@example.com',
            'year_level' => '2nd Year', 'address' => 'Cebu City',
        ] + $extra);

        $save()->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'Enter your current password to change your email.']);
        $save(['current_password' => 'wrong-pass1'])->assertUnprocessable();
        $this->assertSame('juan@example.com', $juan->fresh()->email);

        $save(['current_password' => 'Study2026'])->assertOk();
        $this->assertSame('hacker@example.com', $juan->fresh()->email);

        // The old address is told, with an undo link; the audit trail keeps both addresses.
        $alert = $this->alertTo('juan@example.com');
        $this->assertSame('Student', $alert->accountType);
        $this->assertDatabaseHas('audit_trail', [
            'action' => 'Updated profile',
            'details' => 'Changed: Email · Email: juan@example.com → hacker@example.com',
        ]);

        // Undo: the old email is back, the password nobody knows, verified again.
        $this->app['auth']->forgetGuards();
        $this->post($alert->undoUrl)->assertOk()->assertSee('juan@example.com');

        $juan = $juan->fresh();
        $this->assertSame('juan@example.com', $juan->email);
        $this->assertTrue($juan->hasVerifiedEmail());
        $this->post('/login', ['email' => 'juan@example.com', 'password' => 'Study2026'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_trail', ['action' => 'Undid email change', 'affected_entity' => 'user_account:' . $juan->user_id]);
        // No new verification email for the restored address.
        Notification::assertNotSentTo($juan, VerifyEmailNotification::class, fn ($n, $ch, $to) => $to->email === 'juan@example.com');
    }

    public function test_a_department_account_needs_the_password_and_its_old_address_can_undo(): void
    {
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com');

        $save = fn (array $extra = []) => $this->actingAs($lab, 'dept')->postJson(route('department.profile.update'), [
            'full_name' => $lab->full_name, 'email' => 'other@example.com',
        ] + $extra);

        $save()->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $save(['current_password' => 'Lab2026pass'])->assertOk();

        $alert = $this->alertTo('lab@example.com');
        $this->assertSame('Laboratory Personnel', $alert->accountType);

        $this->app['auth']->forgetGuards();
        $this->post($alert->undoUrl)->assertOk();
        $this->assertSame('lab@example.com', $lab->fresh()->email);
    }

    public function test_staff_changing_a_verified_users_email_alerts_the_old_address(): void
    {
        $department = $this->makeDepartment();
        $lab = $this->makeLabPersonnel($department);
        $juan = $this->makeStudent($department, 'juan@example.com');

        $this->actingAs($lab, 'dept')->postJson(route('department.users.update'), ['user_id' => $juan->user_id] + array_merge(
            \App\Services\UserAccountEditor::values($juan->fresh('student')),
            ['email' => 'juan.new@example.com'],
        ))->assertOk();

        $alert = $this->alertTo('juan@example.com');
        $this->assertStringContainsString('laboratory', $alert->changedBy);
        $this->assertDatabaseHas('audit_trail', ['action' => 'Updated user account', 'dept_acc_id' => $lab->dept_acc_id]);
        $this->assertStringContainsString('juan@example.com → juan.new@example.com', (string) \App\Models\AuditTrail::where('action', 'Updated user account')->value('details'));
    }

    public function test_old_admin_undo_links_still_work(): void
    {
        $admin = $this->makeAdmin('admin@example.com');
        $admin->forceFill(['email' => 'stolen@example.com', 'username' => 'stolen@example.com'])->save();

        // A link sent before every account type was protected: the token holds only "a".
        $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['a' => $admin->admin_id, 'o' => 'admin@example.com', 'n' => 'stolen@example.com']));
        $url = URL::temporarySignedRoute('account.email-change.undo', now()->addDays(AdminEmailChange::UNDO_DAYS), ['t' => $token]);

        $this->post($url)->assertOk();
        $this->assertSame('admin@example.com', $admin->fresh()->email);
    }
}
