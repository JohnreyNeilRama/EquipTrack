<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Rules\UniqueAccountEmail;
use App\Services\AccountPassword;
use App\Services\AdminEmailChange;
use App\Services\AuditLogger;
use App\Support\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    public function show(): View
    {
        $account = auth('user')->user()->load(['student', 'facultyMember']);

        $profile = $account->role === 'Student' ? $account->student : $account->facultyMember;

        return view('user.profile', [
            'first_name' => $profile?->first_name ?? 'User',
            'last_name' => $profile?->last_name ?? '',
            'full_name' => $account->fullName() ?: 'User',
            'user_role' => $account->role,
            'user_email' => $account->email,
            'user_year_level' => $account->role === 'Student' ? ($profile?->year_level ?: 'N/A') : 'N/A',
            'user_address' => $profile?->address ?? '',
            'user_profile_image' => $account->profile_image,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $account = auth('user')->user();

        // Changing the email needs the current password (someone using a
        // browser left signed in can't take the account over), and the old
        // address is then told, with an undo link (AdminEmailChange).
        $oldEmail = (string) $account->email;
        $emailChanging = strcasecmp(trim((string) $request->input('email')), $oldEmail) !== 0;

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email')->ignore($account->user_id, 'user_id'), UniqueAccountEmail::except(UniqueAccountEmail::USER, $account->user_id)],
            'year_level' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:150'],
        ] + ($emailChanging ? ['current_password' => ['required', 'string', 'current_password:user']] : []), [
            'email.unique' => 'That email address is already in use by another account.',
            'current_password.required' => 'Enter your current password to change your email.',
            'current_password.current_password' => 'Current password is incorrect.',
        ]);

        $isStudent = $account->role === 'Student';
        $profile = $isStudent ? $account->student : $account->facultyMember;
        $labels = ['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email']
            + ($isStudent ? ['year_level' => 'Year level'] : [])
            + ['address' => 'Address'];
        $changed = AuditLogger::changedFields(
            ['email' => $account->email] + ($profile?->only(['first_name', 'last_name', 'year_level', 'address']) ?? []),
            // The values as they are saved below.
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'],
                'year_level' => $data['year_level'] ?? 'N/A',
                'address' => $data['address'] ?? '',
            ],
            $labels,
        );

        // FIX vs legacy: a failed email UPDATE was silently ignored while the
        // session still showed the new value. Everything is now one transaction
        // and validation guarantees a clean write.
        DB::transaction(function () use ($account, $data) {
            $account->update(['email' => $data['email']]);

            if ($account->role === 'Student') {
                $account->student()->updateOrCreate(['user_id' => $account->user_id], [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? '',
                    'year_level' => $data['year_level'] ?? 'N/A',
                    'address' => $data['address'] ?? '',
                ]);
            } else {
                $account->facultyMember()->updateOrCreate(['user_id' => $account->user_id], [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? '',
                    'address' => $data['address'] ?? '',
                ]);
            }
        });

        $account->unsetRelation('student')->unsetRelation('facultyMember');

        if ($changed) {
            AuditLogger::log('Updated profile', 'user_account:' . $account->user_id, 'Changed: ' . implode(', ', $changed) . AuditLogger::emailChangeNote($oldEmail, $data['email']), AuditLogger::actorFor('user', $account), $request);
        }

        if ($emailChanging) {
            AdminEmailChange::notifyOldAddress($account, $oldEmail, $data['email'], null, $request);
        }

        return response()->json([
            'success' => true,
            'message' => $emailChanging
                ? 'Personal information updated. We sent a verification link to your new email, and told your old email about the change.'
                : 'Personal information updated successfully!',
        ]);
    }

    /** Change Password section of the profile page. */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(AccountPassword::rules('user'), AccountPassword::messages());

        $account = auth('user')->user();
        AccountPassword::change($account, 'user', $data['new_password'], 'user_account:' . $account->user_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been updated. Other devices signed in to your account have been signed out.',
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_image_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ], [
            'profile_image_file.mimes' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF',
            'profile_image_file.max' => 'Image size must be less than 5MB',
        ]);

        $user = auth('user')->user();
        $old = $user->profile_image;
        $path = ProfileImage::store($data['profile_image_file'], 'user-' . $user->user_id . '-profile');

        $user->update(['profile_image' => $path]);
        ProfileImage::deleteUnused($old);

        AuditLogger::log('Updated profile picture', 'user_account:' . auth('user')->user()->user_id, null, AuditLogger::actorFor('user', auth('user')->user()), $request);

        return response()->json(['success' => true, 'image_url' => $path]);
    }
}
