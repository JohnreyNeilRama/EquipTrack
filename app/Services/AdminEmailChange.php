<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\AdminEmailChangedNotification;
use App\Support\LoginLockout;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Protection against an account being taken over by changing its email
 * address, for every account type: Administrators, Department (Lab
 * Personnel) accounts, Students and Faculty. (Named after the Admin
 * accounts it first protected.)
 *
 * Whenever an account's email changes (on its own Profile page, or by staff
 * on a Users page), the OLD address is emailed at once with an undo link
 * valid for UNDO_DAYS days. Opening the link and confirming:
 *  - puts the old email back (for an Admin it is also the username),
 *  - replaces the password with a random one and the "remember me" token, so
 *    every device signed in to the account is signed out (auth.session),
 *  - cancels pending password reset links and clears any sign-in lockout,
 *  - is recorded in the audit trail.
 * The owner then uses Forgot Password, which reaches their own email again.
 *
 * Not sent when there is nobody to protect: an account still waiting for its
 * invite (the old address was never used), or a Department account handed
 * over to a new person (the old address belongs to the previous holder).
 *
 * The link is signed and expires; the details it needs (account, old and new
 * email) travel inside it encrypted, so nothing extra is stored and no email
 * address appears in readable form in the link. A link only works while the
 * account still has the email it was sent about, so it cannot be replayed
 * after a newer change or after it was used.
 */
class AdminEmailChange
{
    public const UNDO_DAYS = 7;

    /**
     * Emails the old address about a change of an account's email.
     *
     * @param  Admin|DepartmentAccount|null  $changedBy  staff who made the change (null or the same account: the owner on their Profile page)
     */
    public static function notifyOldAddress(Admin|DepartmentAccount|UserAccount $account, string $oldEmail, string $newEmail, Admin|DepartmentAccount|null $changedBy, ?Request $request = null): void
    {
        $oldEmail = trim($oldEmail);
        $newEmail = trim($newEmail);

        if ($oldEmail === '' || strcasecmp($oldEmail, $newEmail) === 0) {
            return;
        }

        $request ??= request();
        $byOwner = $changedBy === null || $changedBy->is($account);

        try {
            Notification::route('mail', $oldEmail)->notify(new AdminEmailChangedNotification(
                name: self::displayName($account) ?: 'there',
                oldEmail: $oldEmail,
                newEmail: self::mask($newEmail),
                changedBy: $byOwner ? 'your account, from its Profile page' : self::staffLabel($changedBy) . ', from the Users page',
                changedAt: now(config('app.display_timezone'))->format('M d, Y h:i A'),
                ipAddress: (string) ($request?->ip() ?? 'unknown'),
                undoUrl: self::undoUrl($account, $oldEmail, $newEmail),
                days: self::UNDO_DAYS,
                accountType: self::typeLabel($account),
                helpFrom: self::helpFrom($account),
            ));
        } catch (Throwable $e) {
            // The change itself is saved; a mail server problem is logged for
            // the developers and recorded in the audit trail.
            report($e);
            AuditLogger::log('Email change alert failed', self::entity($account), 'Could not email the old address about the email change', null, $request);
        }
    }

    /** The signed, expiring undo link sent to the old address. */
    public static function undoUrl(Admin|DepartmentAccount|UserAccount $account, string $oldEmail, string $newEmail): string
    {
        $token = Crypt::encryptString(json_encode([
            'k' => self::kind($account),
            'i' => (int) $account->getKey(),
            'o' => $oldEmail,
            'n' => $newEmail,
        ]));

        return URL::temporarySignedRoute('account.email-change.undo', now()->addDays(self::UNDO_DAYS), ['t' => $token]);
    }

    /**
     * The change an undo link is about, or null when the link is broken or
     * no longer applies. "stale" is true when the account no longer has the
     * new email (already undone, or changed again since).
     *
     * @return array{account: Admin|DepartmentAccount|UserAccount, old: string, new: string, stale: bool, type: string, helpFrom: string}|null
     */
    public static function read(?string $token): ?array
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (!is_array($data) || !isset($data['o'], $data['n'])) {
            return null;
        }

        // Links sent before every account type was protected carry only "a" (an admin_id).
        [$kind, $id] = isset($data['k'], $data['i']) ? [(string) $data['k'], (int) $data['i']] : ['admin', (int) ($data['a'] ?? 0)];

        $account = match ($kind) {
            'admin' => Admin::find($id),
            'dept' => DepartmentAccount::find($id),
            'user' => UserAccount::find($id),
            default => null,
        };
        if (!$account) {
            return null;
        }

        return [
            'account' => $account,
            'old' => (string) $data['o'],
            'new' => (string) $data['n'],
            'stale' => strcasecmp((string) $account->email, (string) $data['n']) !== 0,
            'type' => self::typeLabel($account),
            'helpFrom' => self::helpFrom($account),
        ];
    }

    /**
     * Undoes the change. Returns an error message, or null when it was undone.
     *
     * @param  array{account: Admin|DepartmentAccount|UserAccount, old: string, new: string}  $change
     */
    public static function undo(array $change, Request $request): ?string
    {
        $account = $change['account'];
        $old = $change['old'];
        $new = $change['new'];

        return DB::transaction(function () use ($account, $old, $new, $request) {
            $locked = $account->newQuery()->whereKey($account->getKey())->lockForUpdate()->first();

            if (!$locked || strcasecmp((string) $locked->email, $new) !== 0) {
                return 'This change was already undone, or the email has been changed again since. Nothing was changed.';
            }

            // The old email must still be free (an email belongs to one account only).
            $owner = PasswordResetAccounts::find($old);
            $takenByAnother = $owner && !$owner['account']->is($locked);
            $usernameTaken = Admin::where('username', $old)
                ->when($locked instanceof Admin, fn ($q) => $q->where('admin_id', '!=', $locked->admin_id))
                ->exists();
            if ($takenByAnother || $usernameTaken) {
                return 'Your old email is now used by another EquipTrack account, so it could not be put back. Ask ' . self::helpFrom($locked) . ' for help.';
            }

            $locked->forceFill([
                'email' => $old,
                // A random password nobody knows: every signed-in device is
                // signed out, and the owner sets a new one with Forgot Password.
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(60),
            ] + ($locked instanceof Admin ? ['username' => $old] : [])
              // The owner just proved they read the old inbox.
              + ($locked instanceof UserAccount ? ['email_verified_at' => now()] : []));

            // Quietly for Students & Faculty: an email change there would ask
            // for a new verification email, but this address is already proven.
            $locked instanceof UserAccount ? $locked->saveQuietly() : $locked->save();

            DB::table(self::resetTokenTable($locked))->whereIn('email', [$old, $new])->delete();
            LoginLockout::clear($old, $new);

            AuditLogger::log(
                'Undid email change',
                self::entity($locked),
                'Email changed back from ' . self::mask($new) . ' using the link sent to the old address; password cancelled and all devices signed out',
                AuditLogger::actorFor(self::guard($locked), $locked),
                $request,
            );

            return null;
        });
    }

    /** "m••••@gmail.com": enough to recognize an address without revealing it. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1) . '••••' . ($domain !== '' ? '@' . $domain : '');
    }

    private static function kind(Admin|DepartmentAccount|UserAccount $account): string
    {
        return match (true) {
            $account instanceof Admin => 'admin',
            $account instanceof DepartmentAccount => 'dept',
            default => 'user',
        };
    }

    /** The auth guard of the account (config/auth.php). */
    private static function guard(Admin|DepartmentAccount|UserAccount $account): string
    {
        return self::kind($account);
    }

    private static function entity(Admin|DepartmentAccount|UserAccount $account): string
    {
        return match (true) {
            $account instanceof Admin => 'admin:' . $account->admin_id,
            $account instanceof DepartmentAccount => 'department_account:' . $account->dept_acc_id,
            default => 'user_account:' . $account->user_id,
        };
    }

    private static function resetTokenTable(Admin|DepartmentAccount|UserAccount $account): string
    {
        return match (true) {
            $account instanceof Admin => 'admin_password_reset_tokens',
            $account instanceof DepartmentAccount => 'dept_password_reset_tokens',
            default => 'password_reset_tokens',
        };
    }

    private static function typeLabel(Admin|DepartmentAccount|UserAccount $account): string
    {
        return match (true) {
            $account instanceof Admin => 'Administrator',
            $account instanceof DepartmentAccount => 'Laboratory Personnel',
            default => $account->role === 'Faculty' ? 'Faculty' : 'Student',
        };
    }

    /** Who to ask when the undo link can't be used. */
    private static function helpFrom(Admin|DepartmentAccount|UserAccount $account): string
    {
        return match (true) {
            $account instanceof Admin => 'another EquipTrack administrator',
            $account instanceof DepartmentAccount => 'the EquipTrack Administrator',
            default => 'your department\'s laboratory or the EquipTrack Administrator',
        };
    }

    private static function displayName(Admin|DepartmentAccount|UserAccount $account): string
    {
        return trim((string) match (true) {
            $account instanceof Admin => $account->name,
            $account instanceof DepartmentAccount => $account->full_name,
            default => $account->fullName(),
        });
    }

    private static function staffLabel(Admin|DepartmentAccount $staff): string
    {
        return $staff instanceof Admin
            ? 'administrator ' . (trim((string) $staff->name) ?: $staff->email)
            : 'the ' . ($staff->department?->department_name ?: 'department') . ' laboratory';
    }
}
