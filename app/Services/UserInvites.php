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
 * Invites to set a first password, for accounts added by someone else:
 * - Students and Faculty added on a Users page (Admin or Lab Personnel);
 * - Department (Lab Personnel) and Admin accounts added by an Admin.
 *
 * Nobody chooses the password for the owner: they get an email with a link
 * to set it (Auth\AccountSetupController), which also confirms the email
 * address. The sender can send the invite again, e.g. after fixing a
 * mistyped email or when the 7-day link expired.
 *
 * Tokens come from the invite broker of the account type (config/auth.php:
 * user_invites, department_invites, admin_invites).
 */
class UserInvites
{
    /** Emails a new invite; returns why it wasn't sent, or null when it was. */
    public static function send(UserAccount|DepartmentAccount|Admin $account, Admin|DepartmentAccount $sender, ?Request $request = null): ?string
    {
        if (self::isSetUp($account)) {
            return 'This account is already set up; its owner signs in with their own password.';
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker(self::broker($account));
        $tokens = $broker->getRepository();

        if ($tokens->recentlyCreatedToken($account)) {
            return 'An invite was just sent to this address. Please wait a minute before sending another.';
        }

        $token = $tokens->create($account);

        try {
            $account->notify(new UserInviteNotification($token, self::senderName($sender)));
        } catch (\Throwable $e) {
            // The mail server is down or misconfigured; the account stays
            // waiting for an invite (invited_at was set when it was created),
            // which can be sent again later.
            report($e);
            $tokens->delete($account);

            return 'The account was saved, but the invite email could not be sent. Check the mail settings, then use Resend Invite.';
        }

        $account->forceFill(['invited_at' => now()])->save();

        AuditLogger::log(
            'Sent account invite',
            self::entity($account),
            'Invite to set a password emailed to ' . $account->email,
            AuditLogger::actorFor($sender instanceof Admin ? 'admin' : 'dept', $sender),
            $request,
        );

        return null;
    }

    /** The invite broker of the account's type (config/auth.php). */
    public static function broker(UserAccount|DepartmentAccount|Admin $account): string
    {
        return match (true) {
            $account instanceof Admin => 'admin_invites',
            $account instanceof DepartmentAccount => 'department_invites',
            default => 'user_invites',
        };
    }

    /** The owner has set a password (or the account predates invites). */
    public static function isSetUp(UserAccount|DepartmentAccount|Admin $account): bool
    {
        return $account instanceof UserAccount ? $account->hasVerifiedEmail() : $account->isSetUp();
    }

    private static function entity(UserAccount|DepartmentAccount|Admin $account): string
    {
        return match (true) {
            $account instanceof Admin => 'admin:' . $account->admin_id,
            $account instanceof DepartmentAccount => 'department_account:' . $account->dept_acc_id,
            default => 'user_account:' . $account->user_id,
        };
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
