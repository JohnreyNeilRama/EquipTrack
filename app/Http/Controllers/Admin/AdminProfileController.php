<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Rules\UniqueAccountEmail;
use App\Services\AccountPassword;
use App\Services\AdminEmailChange;
use App\Services\AuditLogger;
use App\Support\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function show(): View
    {
        $admin = auth('admin')->user();

        return view('admin.profile', [
            'admin_name' => $admin->name,
            'admin_email' => $admin->email ?: $admin->username,
            // Empty when not set (the page shows "Not set"); a made-up ID here
            // would be saved as the admin's own the next time they save.
            'admin_employee_id' => $admin->employee_id ?? '',
            'admin_profile_image' => $admin->profile_image,
            'success_msg' => session('success_msg'),
            'error_msg' => session('error_msg'),
        ]);
    }

    /**
     * Saves the Personal Information form. The profile page sends it by AJAX
     * and gets JSON back; a plain form post is redirected back to the page.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $admin = auth('admin')->user();
        $oldEmail = (string) ($admin->email ?: $admin->username);

        // Changing the email needs the current password, so someone using a
        // session left signed in cannot redirect password resets to themselves.
        $emailChanging = strcasecmp(trim((string) $request->input('admin_email')), $oldEmail) !== 0;
        $passwordRules = AccountPassword::rules('admin', optional: true);
        if ($emailChanging) {
            $passwordRules['current_password'] = ['required', 'string', 'current_password:admin'];
        }

        $data = $request->validate([
            'admin_full_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:100', Rule::unique('admin', 'email')->ignore($admin->admin_id, 'admin_id'), UniqueAccountEmail::except(UniqueAccountEmail::ADMIN, $admin->admin_id)],
            'admin_employee_id' => ['nullable', 'string', 'max:50'],
            // Optional Change Password fields, checked by the shared rules.
            ...$passwordRules,
        ], [
            'admin_email.unique' => 'Another admin already uses that email/username.',
            ...AccountPassword::messages(),
            'current_password.required' => $emailChanging ? 'Enter your current password to change your email.' : 'Enter your current password.',
        ]);

        $update = [
            'name' => $data['admin_full_name'],
            'username' => $data['admin_email'], // legacy kept username synced to email
            'email' => $data['admin_email'],
            'employee_id' => ($data['admin_employee_id'] ?? null) ?: $admin->employee_id,
        ];

        // username has a UNIQUE index and legacy mirrored email into it; a
        // collision with another admin's username must not become a 500.
        $usernameTaken = Admin::where('username', $data['admin_email'])
            ->where('admin_id', '!=', $admin->admin_id)
            ->exists();
        if ($usernameTaken) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Another admin already uses that email/username.',
                    'errors' => ['admin_email' => ['Another admin already uses that email/username.']],
                ], 422);
            }

            return redirect()->route('admin.profile')
                ->with('error_msg', 'Another admin already uses that email/username.')
                ->withInput();
        }

        $changed = AuditLogger::changedFields(
            $admin->only(['name', 'email', 'employee_id']),
            $update,
            ['name' => 'Full name', 'email' => 'Email', 'employee_id' => 'Employee ID'],
        );

        $admin->update($update);

        $entity = 'admin:' . $admin->admin_id;
        if ($changed) {
            AuditLogger::log('Updated profile', $entity, 'Changed: ' . implode(', ', $changed) . AuditLogger::emailChangeNote($oldEmail, $data['admin_email']), AuditLogger::actorFor('admin', $admin), $request);
        }
        // The old address is told at once, with a link to undo the change.
        if ($emailChanging) {
            AdminEmailChange::notifyOldAddress($admin, $oldEmail, $data['admin_email'], $admin, $request);
        }
        // The current password was verified by the validation rules above.
        if (!empty($data['new_password'])) {
            AccountPassword::change($admin, 'admin', $data['new_password'], $entity, $request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Profile information updated successfully!']);
        }

        return redirect()->route('admin.profile')->with('success_msg', 'Profile changes saved successfully!');
    }

    /** Change Password section of the profile page (same as the User account's). */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(AccountPassword::rules('admin'), AccountPassword::messages());

        $admin = auth('admin')->user();
        AccountPassword::change($admin, 'admin', $data['new_password'], 'admin:' . $admin->admin_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been updated. Other devices signed in to your account have been signed out.',
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_avatar_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ]);

        $adminId = auth('admin')->user()->admin_id;
        $old = Admin::whereKey($adminId)->value('profile_image');
        $path = ProfileImage::store($data['admin_avatar_file'], 'admin-' . $adminId . '-avatar');

        Admin::where('admin_id', $adminId)->update(['profile_image' => $path]);
        ProfileImage::deleteUnused($old);

        AuditLogger::log('Updated profile picture', 'admin:' . auth('admin')->user()->admin_id, null, AuditLogger::actorFor('admin', auth('admin')->user()), $request);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully!',
            'image_url' => $path,
        ]);
    }
}
