<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Change Password for every account type (guards: user, admin, dept).
 *
 * The rules require the account's current password (checked against the
 * signed-in account of that guard), a new password that meets the shared
 * policy (Password::defaults(), see AppServiceProvider) and differs from the
 * current one, and a matching confirmation.
 *
 * Other devices signed in to the account are signed out by the auth.session
 * middleware once the password changes; the session that made the change
 * stays signed in.
 */
class AccountPassword
{
    /**
     * @param  bool  $optional  true where the password fields share a form with
     *                          other profile fields and may be left empty.
     */
    public static function rules(string $guard, bool $optional = false): array
    {
        return [
            'current_password' => $optional
                ? ['nullable', 'required_with:new_password', 'string', 'current_password:' . $guard]
                : ['required', 'string', 'current_password:' . $guard],
            'new_password' => [$optional ? 'nullable' : 'required', 'string', Password::defaults(), 'different:current_password'],
            'confirm_password' => [$optional ? 'nullable' : 'required', 'required_with:new_password', 'same:new_password'],
        ];
    }

    public static function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'current_password.current_password' => 'Current password is incorrect.',
            'new_password.required' => 'Enter a new password.',
            'new_password.different' => 'Your new password must be different from your current password.',
            'new_password.min' => 'Your new password must be at least :min characters.',
            'new_password.letters' => 'Your new password must include at least one letter.',
            'new_password.numbers' => 'Your new password must include at least one number.',
            'confirm_password.required' => 'Confirm your new password.',
            'confirm_password.required_with' => 'Confirm your new password.',
            'confirm_password.same' => 'New password and confirm password do not match.',
        ];
    }

    /** Saves the new password (hashed) and records it in the audit trail. */
    public static function change(Authenticatable $account, string $guard, string $newPassword, string $entity, ?Request $request = null): void
    {
        $account->forceFill(['password' => Hash::make($newPassword)])->save();

        AuditLogger::log('Changed password', $entity, null, AuditLogger::actorFor($guard, $account), $request);
    }
}
