<?php

namespace Tests\Feature;

use App\Models\UserAccount;
use App\Notifications\UserInviteNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Emails are sent while the page waits, so the mail connection has a short
 * timeout (MAIL_TIMEOUT, 10 seconds by default), and a mail server that can't
 * be reached never breaks the action itself.
 */
class MailTimeoutTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    public function test_the_smtp_connection_gives_up_after_the_configured_timeout(): void
    {
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));

        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertSame(10.0, (float) $transport->getStream()->getTimeout());
    }

    public function test_an_unreachable_mail_server_does_not_stop_staff_adding_a_student(): void
    {
        // Nothing listens on this port: the connection is refused.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
        Mail::purge('smtp');

        $department = $this->makeDepartment();
        $lab = $this->makeLabPersonnel($department);

        $this->actingAs($lab, 'dept')->postJson(route('department.users.student'), [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
            'email' => 'ana@example.com', 'address' => 'Cebu City',
        ])->assertOk()->assertJsonPath('message', fn (string $m) => str_contains($m, 'could not be sent'));

        // The account is saved and still counts as added by staff, waiting
        // for its invite (not as a self-registered, unverified account).
        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertFalse($ana->hasVerifiedEmail());
        $this->assertSame('invited', $ana->accountState());

        // Changing the email doesn't send a verification email: she has no password yet.
        Notification::fake();
        $ana->update(['email' => 'ana.santos@example.com']);
        Notification::assertNotSentTo($ana, VerifyEmailNotification::class);

        // Mail working again: Resend sends a real invite to set a password.
        $this->postJson(route('department.users.invite'), ['user_id' => $ana->user_id])
            ->assertOk()->assertJsonPath('message', fn (string $m) => str_contains($m, 'invite'));

        Notification::assertSentTo($ana, UserInviteNotification::class);
        Notification::assertNotSentTo($ana, VerifyEmailNotification::class);
    }

    public function test_the_admin_resend_after_a_failed_invite_also_sends_an_invite(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
        Mail::purge('smtp');

        $department = $this->makeDepartment();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->post(route('admin.users.student'), [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
            'email' => 'ana@example.com', 'address' => 'Cebu City', 'department' => $department->department_name,
        ])->assertSessionHas('serverMsgType', 'error');

        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('invited', $ana->accountState());

        Notification::fake();
        $this->post(route('admin.users.invite'), ['user_id' => $ana->user_id])->assertSessionHas('serverMsgType', 'success');

        Notification::assertSentTo($ana, UserInviteNotification::class);
        Notification::assertNotSentTo($ana, VerifyEmailNotification::class);
    }
}
