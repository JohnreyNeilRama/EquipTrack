<?php

namespace App\Services;

use App\Models\Admin;
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
 * Protection against an Administrator account being taken over by changing
 * its email address.
 *
 * Whenever an Admin's email changes (on their Profile page, or by another
 * Admin on the Users page), the OLD address is emailed at once with an undo
 * link valid for UNDO_DAYS days. Opening the link and confirming:
 *  - puts the old email back (it is also the Admin's username),
 *  - replaces the password with a random one and the "remember me" token, so
 *    every device signed in to the account is signed out (auth.session),
 *  - cancels pending password reset links and clears any sign-in lockout,
 *  - is recorded in the audit trail.
 * The owner then uses Forgot Password, which reaches their own email again.
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
     * Emails the old address about a change of an Admin's email.
     *
     * @param  Admin|null  $changedBy  the Admin who made the change (null or the same account: the owner on their Profile page)
     */
    public static function notifyOldAddress(Admin $admin, string $oldEmail, string $newEmail, ?Admin $changedBy, ?Request $request = null): void
    {
        $oldEmail = trim($oldEmail);
        $newEmail = trim($newEmail);

        if ($oldEmail === '' || strcasecmp($oldEmail, $newEmail) === 0) {
            return;
        }

        $request ??= request();
        $byOwner = $changedBy === null || (int) $changedBy->admin_id === (int) $admin->admin_id;

        try {
            Notification::route('mail', $oldEmail)->notify(new AdminEmailChangedNotification(
                name: trim((string) $admin->name) ?: 'there',
                oldEmail: $oldEmail,
                newEmail: self::mask($newEmail),
                changedBy: $byOwner ? 'your account, from its Profile page' : 'administrator ' . (trim((string) $changedBy->name) ?: $changedBy->email) . ', from the Users page',
                changedAt: now(config('app.display_timezone'))->format('M d, Y h:i A'),
                ipAddress: (string) ($request?->ip() ?? 'unknown'),
                undoUrl: self::undoUrl($admin, $oldEmail, $newEmail),
                days: self::UNDO_DAYS,
            ));
        } catch (Throwable $e) {
            // The change itself is saved; a mail server problem is logged for
            // the developers and recorded in the audit trail.
            report($e);
            AuditLogger::log('Email change alert failed', 'admin:' . $admin->admin_id, 'Could not email the old address about the email change', null, $request);
        }
    }

    /** The signed, expiring undo link sent to the old address. */
    public static function undoUrl(Admin $admin, string $oldEmail, string $newEmail): string
    {
        $token = Crypt::encryptString(json_encode([
            'a' => (int) $admin->admin_id,
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
     * @return array{admin: Admin, old: string, new: string, stale: bool}|null
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

        if (!is_array($data) || !isset($data['a'], $data['o'], $data['n'])) {
            return null;
        }

        $admin = Admin::find((int) $data['a']);
        if (!$admin) {
            return null;
        }

        return [
            'admin' => $admin,
            'old' => (string) $data['o'],
            'new' => (string) $data['n'],
            'stale' => strcasecmp((string) $admin->email, (string) $data['n']) !== 0,
        ];
    }

    /**
     * Undoes the change. Returns an error message, or null when it was undone.
     *
     * @param  array{admin: Admin, old: string, new: string, stale: bool}  $change
     */
    public static function undo(array $change, Request $request): ?string
    {
        $admin = $change['admin'];
        $old = $change['old'];
        $new = $change['new'];

        return DB::transaction(function () use ($admin, $old, $new, $request) {
            $locked = Admin::whereKey($admin->admin_id)->lockForUpdate()->first();

            if (!$locked || strcasecmp((string) $locked->email, $new) !== 0) {
                return 'This change was already undone, or the email has been changed again since. Nothing was changed.';
            }

            // The old email must still be free (an email belongs to one account only).
            $owner = PasswordResetAccounts::find($old);
            $takenByAnother = $owner && !($owner['account'] instanceof Admin && (int) $owner['account']->admin_id === (int) $locked->admin_id);
            $usernameTaken = Admin::where('username', $old)->where('admin_id', '!=', $locked->admin_id)->exists();
            if ($takenByAnother || $usernameTaken) {
                return 'Your old email is now used by another EquipTrack account, so it could not be put back. Ask another administrator for help.';
            }

            $locked->forceFill([
                'email' => $old,
                'username' => $old,
                // A random password nobody knows: every signed-in device is
                // signed out, and the owner sets a new one with Forgot Password.
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('admin_password_reset_tokens')->whereIn('email', [$old, $new])->delete();
            LoginLockout::clear($old, $new);

            AuditLogger::log(
                'Undid email change',
                'admin:' . $locked->admin_id,
                'Email changed back from ' . self::mask($new) . ' using the link sent to the old address; password cancelled and all devices signed out',
                AuditLogger::actorFor('admin', $locked),
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
}
