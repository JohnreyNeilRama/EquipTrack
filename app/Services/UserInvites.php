<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\UserInviteNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Invites for Student and Faculty accounts that staff add on a Users page
 * (Admin or Lab Personnel). Staff never choose the password: the owner gets
 * an email with a link to set it (Auth\AccountSetupController), which also
 * confirms the email address. Staff can send the invite again, e.g. after
 * fixing a mistyped email or when the 7-day link expired.
 *
 * Tokens come from the "user_invites" broker (config/auth.php).
 */
class UserInvites
{
    /** Emails a new invite; returns why it wasn't sent, or null when it was. */
    public static function send(UserAccount $account, Admin|DepartmentAccount $sender, ?Request $request = null): ?string
    {
        if ($account->hasVerifiedEmail()) {
            return 'This account is already set up; its owner signs in with their own password.';
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker('user_invites');
        $tokens = $broker->getRepository();

        if ($tokens->recentlyCreatedToken($account)) {
            return 'An invite was just sent to this address. Please wait a minute before sending another.';
        }

        $token = $tokens->create($account);

        try {
            $account->notify(new UserInviteNotification($token, self::senderName($sender)));
        } catch (\Throwable $e) {
            // The mail server is down or misconfigured; the account stays
            // waiting for an invite (invited_at was set when staff created it,
            // see UserAccountCreator), which can be sent again later.
            report($e);
            $tokens->delete($account);

            return 'The account was saved, but the invite email could not be sent. Check the mail settings, then use Resend Invite.';
        }

        $account->forceFill(['invited_at' => now()])->save();

        AuditLogger::log(
            'Sent account invite',
            'user_account:' . $account->user_id,
            'Invite to set a password emailed to ' . $account->email,
            AuditLogger::actorFor($sender instanceof Admin ? 'admin' : 'dept', $sender),
            $request,
        );

        return null;
    }

    private static function senderName(Admin|DepartmentAccount $sender): string
    {
        if ($sender instanceof Admin) {
            return 'The EquipTrack Administrator';
        }

        return $sender->department?->department_name
            ? 'The ' . $sender->department->department_name . ' laboratory'
            : 'Your department laboratory';
    }
}
