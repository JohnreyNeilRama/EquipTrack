<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Notifications\AdminEmailChangedNotification;
use App\Services\UserAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Protection against an Administrator account being taken over by changing
 * its email: the change needs a password, the old address is emailed at once,
 * and its "This wasn't me" link puts the old email back and signs everyone out.
 */
class AdminEmailChangeProtectionTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private const OFFSET = 200000;

    private Admin $owner;

    private Admin $backup;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = $this->makeAdmin('owner@example.com', 'Olivia Owner', 'Owner2026');
        $this->backup = $this->makeAdmin('backup@example.com', 'Ben Backup', 'Backup2026');
    }

    private function changeOwnEmail(string $email, ?string $password)
    {
        return $this->actingAs($this->owner, 'admin')->postJson(route('admin.profile.update'), array_filter([
            'admin_full_name' => 'Olivia Owner',
            'admin_email' => $email,
            'admin_employee_id' => '',
            'current_password' => $password,
        ], fn ($value) => $value !== null));
    }

    private function editAdminEmail(Admin $actor, Admin $target, string $email, ?string $password)
    {
        return $this->actingAs($actor, 'admin')->post(route('admin.users.update'), array_filter([
            'form_type' => 'edit',
            'user_id' => self::OFFSET + $target->admin_id,
            'user_type' => 'admin',
            'current_password' => $password,
        ], fn ($value) => $value !== null) + array_merge(UserAccountEditor::values($target), ['email' => $email]));
    }

    /** The undo link emailed to $oldEmail. */
    private function undoLinkSentTo(string $oldEmail): string
    {
        $url = null;
        Notification::assertSentOnDemand(AdminEmailChangedNotification::class, function ($notification, $channels, $notifiable) use ($oldEmail, &$url) {
            if (($notifiable->routes['mail'] ?? null) !== $oldEmail) {
                return false;
            }
            $url = $notification->undoUrl;

            return true;
        });

        return $url;
    }

    // ------------------------------------------------------------------
    // Password confirmation
    // ------------------------------------------------------------------

    public function test_changing_your_own_email_needs_your_current_password(): void
    {
        $this->changeOwnEmail('hacker@example.com', null)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password' => 'Enter your current password to change your email.']);

        $this->changeOwnEmail('hacker@example.com', 'WrongGuess1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame('owner@example.com', $this->owner->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_other_profile_changes_need_no_password(): void
    {
        $this->changeOwnEmail('owner@example.com', null)->assertOk()->assertJson(['success' => true]);

        Notification::assertNothingSent();
    }

    public function test_changing_another_administrators_email_needs_your_password(): void
    {
        $this->editAdminEmail($this->owner, $this->backup, 'evil@example.com', null)
            ->assertSessionHasErrors(['current_password' => 'Enter your password to confirm this change.']);

        $this->editAdminEmail($this->owner, $this->backup, 'evil@example.com', 'Backup2026') // the target's password does not count
            ->assertSessionHasErrors(['current_password' => 'Your password is incorrect, so nothing was changed.']);

        $this->assertSame('backup@example.com', $this->backup->fresh()->email);
        Notification::assertNothingSent();

        // The typed password is never kept for the re-opened form.
        $this->assertArrayNotHasKey('current_password', session()->getOldInput());
    }

    public function test_activating_deactivating_and_deleting_an_administrator_need_your_password(): void
    {
        $toggle = fn (?string $password, string $status) => $this->actingAs($this->owner, 'admin')->post(route('admin.users.toggle'), array_filter([
            'target_user_id' => self::OFFSET + $this->backup->admin_id,
            'target_user_type' => 'admin',
            'new_status' => $status,
            'current_password' => $password,
        ]));

        $toggle(null, 'Deactivated')->assertSessionHas('serverMsg', 'Enter your password to confirm this change.');
        $toggle('WrongGuess1', 'Deactivated')->assertSessionHas('serverMsg', 'Your password is incorrect, so nothing was changed.');
        $this->assertSame('Active', $this->backup->fresh()->status);

        $toggle('Owner2026', 'Deactivated')->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Deactivated', $this->backup->fresh()->status);

        $toggle(null, 'Active')->assertSessionHas('serverMsgType', 'error');
        $toggle('Owner2026', 'Active')->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Active', $this->backup->fresh()->status);

        $delete = fn (?string $password) => $this->actingAs($this->owner, 'admin')->post(route('admin.users.delete'), array_filter([
            'user_id' => self::OFFSET + $this->backup->admin_id,
            'user_type' => 'admin',
            'current_password' => $password,
        ]));

        $delete('WrongGuess1')->assertSessionHas('serverMsgType', 'error');
        $this->assertNotNull($this->backup->fresh());

        $delete('Owner2026')->assertSessionHas('serverMsg', 'Administrator account deleted successfully.');
        $this->assertNull($this->backup->fresh());
    }

    public function test_students_and_lab_personnel_can_still_be_managed_without_a_password(): void
    {
        $student = $this->makeStudent($this->makeDepartment());

        $this->actingAs($this->owner, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => $student->user_id,
            'target_user_type' => 'user_account',
            'new_status' => 'Deactivated',
        ])->assertSessionHas('serverMsgType', 'success');

        $this->assertSame('Deactivated', $student->fresh()->status);
    }

    public function test_wrong_passwords_are_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->editAdminEmail($this->owner, $this->backup, 'evil@example.com', 'WrongGuess' . $attempt);
        }

        $this->editAdminEmail($this->owner, $this->backup, 'evil@example.com', 'Owner2026')
            ->assertSessionHasErrors('current_password');
        $this->assertStringContainsString('Too many wrong passwords', session('errors')->first('current_password'));
        $this->assertSame('backup@example.com', $this->backup->fresh()->email);
    }

    // ------------------------------------------------------------------
    // Alert to the old address
    // ------------------------------------------------------------------

    public function test_the_old_address_is_emailed_when_you_change_your_own_email(): void
    {
        $this->changeOwnEmail('new.owner@example.com', 'Owner2026')->assertOk();

        $this->assertSame('new.owner@example.com', $this->owner->fresh()->email);

        Notification::assertSentOnDemand(AdminEmailChangedNotification::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);

            return ($notifiable->routes['mail'] ?? null) === 'owner@example.com'
                && $notification->newEmail === 'n••••@example.com'
                && str_contains($notification->changedBy, 'Profile page')
                && $mail->subject === 'Your EquipTrack Admin email was changed'
                && str_contains($notification->undoUrl, 'signature=');
        });
    }

    public function test_the_old_address_is_emailed_when_another_administrator_changes_it(): void
    {
        $this->editAdminEmail($this->owner, $this->backup, 'ben.new@example.com', 'Owner2026')
            ->assertSessionHas('serverMsgType', 'success');

        $this->assertSame('ben.new@example.com', $this->backup->fresh()->email);

        Notification::assertSentOnDemand(AdminEmailChangedNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'backup@example.com'
                && str_contains($notification->changedBy, 'Olivia Owner');
        });
    }

    public function test_the_alert_email_renders_with_the_undo_link(): void
    {
        $this->changeOwnEmail('new.owner@example.com', 'Owner2026');
        $url = $this->undoLinkSentTo('owner@example.com');

        $html = view('emails.admin-email-changed', [
            'name' => 'Olivia Owner', 'oldEmail' => 'owner@example.com', 'newEmail' => 'n••••@example.com',
            'changedBy' => 'your account, from its Profile page', 'changedAt' => 'Nov 11, 2026 03:42 PM',
            'ipAddress' => '127.0.0.1', 'undoUrl' => $url, 'days' => 7,
        ])->render();

        $this->assertStringContainsString("This wasn't me: undo this change", $html);
        $this->assertStringContainsString(e($url), $html);
        $this->assertStringNotContainsString('new.owner@example.com', $url); // no readable email in the link
    }

    // ------------------------------------------------------------------
    // Undo link
    // ------------------------------------------------------------------

    public function test_the_owner_undoes_a_takeover_and_the_attacker_loses_access(): void
    {
        // The attacker knows the stolen password, changes the email and then the password.
        $this->changeOwnEmail('attacker@example.com', 'Owner2026')->assertOk();
        $this->actingAs($this->owner->fresh(), 'admin')->postJson(route('admin.profile.password'), [
            'current_password' => 'Owner2026',
            'new_password' => 'Attacker2026',
            'confirm_password' => 'Attacker2026',
        ])->assertOk();
        DB::table('admin_password_reset_tokens')->insert(['email' => 'attacker@example.com', 'token' => Hash::make('x'), 'created_at' => now()]);
        $rememberBefore = $this->owner->fresh()->remember_token;

        $url = $this->undoLinkSentTo('owner@example.com');
        auth('admin')->logout();

        // Opening the link only asks; it changes nothing (safe for email link scanners).
        $this->get($url)->assertOk()->assertSee('Undo the email change?')->assertSee('a••••@example.com');
        $this->assertSame('attacker@example.com', $this->owner->fresh()->email);

        $this->post($url)->assertOk()->assertSee('Your email is back');

        $owner = $this->owner->fresh();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertSame('owner@example.com', $owner->username);
        $this->assertFalse(Hash::check('Attacker2026', $owner->password));
        $this->assertFalse(Hash::check('Owner2026', $owner->password));
        $this->assertNotSame($rememberBefore, $owner->remember_token);
        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => 'attacker@example.com']);

        // The attacker can no longer sign in; the owner can request a reset to their own email.
        $this->post('/login', ['email' => 'attacker@example.com', 'password' => 'Attacker2026'])->assertSessionHasErrors('email');

        $audit = AuditTrail::where('action', 'Undid email change')->firstOrFail();
        $this->assertSame('admin:' . $owner->admin_id, $audit->affected_entity);
    }

    public function test_an_undo_link_works_once(): void
    {
        $this->changeOwnEmail('new.owner@example.com', 'Owner2026');
        $url = $this->undoLinkSentTo('owner@example.com');

        $this->post($url)->assertSee('Your email is back');
        $this->get($url)->assertSee("This link can't be used", false)->assertSee('already undone');
        $this->post($url)->assertSee("This link can't be used", false);
    }

    public function test_an_undo_link_stops_working_after_a_newer_change(): void
    {
        $this->changeOwnEmail('first@example.com', 'Owner2026');
        $url = $this->undoLinkSentTo('owner@example.com');

        $this->actingAs($this->owner->fresh(), 'admin')->postJson(route('admin.profile.update'), [
            'admin_full_name' => 'Olivia Owner', 'admin_email' => 'second@example.com', 'current_password' => 'Owner2026',
        ])->assertOk();

        $this->post($url)->assertSee("This link can't be used", false);
        $this->assertSame('second@example.com', $this->owner->fresh()->email);
    }

    public function test_expired_or_tampered_links_are_refused(): void
    {
        $this->changeOwnEmail('new.owner@example.com', 'Owner2026');
        $url = $this->undoLinkSentTo('owner@example.com');

        $this->post($url . 'x')->assertSee("This link can't be used", false);
        $this->post(str_replace('t=', 't=a', $url))->assertSee("This link can't be used", false);

        $this->travel(8)->days();
        $this->post($url)->assertSee("This link can't be used", false)->assertSee('expired');

        $this->assertSame('new.owner@example.com', $this->owner->fresh()->email);
    }

    public function test_undo_is_refused_when_the_old_email_now_belongs_to_another_account(): void
    {
        $this->changeOwnEmail('new.owner@example.com', 'Owner2026');
        $url = $this->undoLinkSentTo('owner@example.com');
        $this->makeStudent($this->makeDepartment(), 'owner@example.com');

        $this->post($url)->assertSee('now used by another EquipTrack account');
        $this->assertSame('new.owner@example.com', $this->owner->fresh()->email);
    }
}
