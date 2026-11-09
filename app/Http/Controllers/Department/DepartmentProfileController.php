<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Rules\UniqueAccountEmail;
use App\Services\AccountPassword;
use App\Services\AuditLogger;
use App\Support\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentProfileController extends Controller
{
    public function show(): View
    {
        $account = auth('dept')->user()->load('department');

        return view('department.profile', [
            'dept_full_name' => $account->full_name,
            'dept_email_val' => $account->email,
            'dept_employee_id' => $account->employee_id ?? '',
            'dept_role_val' => $account->role ?: 'Department Head',
            'dept_assigned' => $account->department?->department_name ?: 'Department Office',
            'dept_profile_img' => $account->profile_image,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $account = auth('dept')->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:100',
                Rule::unique('department_account', 'email')->ignore($account->dept_acc_id, 'dept_acc_id'),
                UniqueAccountEmail::except(UniqueAccountEmail::DEPT, $account->dept_acc_id),
            ],
            'employee_id' => [
                'nullable', 'string', 'max:100',
                Rule::unique('department_account', 'employee_id')
                    ->ignore($account->dept_acc_id, 'dept_acc_id')
                    ->where(fn ($q) => $q->where('employee_id', '!=', '')),
            ],
            // Optional Change Password fields, checked by the shared rules.
            ...AccountPassword::rules('dept', optional: true),
        ], AccountPassword::messages());

        $update = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
        ];

        // NOTE vs legacy: the `role` field is no longer accepted here (a dept
        // account could POST any value to grant itself a different role).
        $changed = AuditLogger::changedFields(
            $account->only(['full_name', 'email', 'employee_id']),
            $update,
            ['full_name' => 'Full name', 'email' => 'Email', 'employee_id' => 'Employee ID'],
        );

        $account->update($update);

        $entity = 'department_account:' . $account->dept_acc_id;
        if ($changed) {
            AuditLogger::log('Updated profile', $entity, 'Changed: ' . implode(', ', $changed), AuditLogger::actorFor('dept', $account), $request);
        }
        // The current password was verified by the validation rules above.
        if (!empty($data['new_password'])) {
            AccountPassword::change($account, 'dept', $data['new_password'], $entity, $request);
        }

        return response()->json(['success' => true, 'message' => 'Profile information updated successfully!']);
    }

    /** Change Password section of the profile page (same as the User account's). */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(AccountPassword::rules('dept'), AccountPassword::messages());

        $account = auth('dept')->user();
        AccountPassword::change($account, 'dept', $data['new_password'], 'department_account:' . $account->dept_acc_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been updated. Other devices signed in to your account have been signed out.',
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ]);

        $account = auth('dept')->user();
        // The account's picture is also its department's logo.
        $old = [$account->profile_image, Department::whereKey($account->department_id)->value('profile_image')];
        $path = ProfileImage::store($data['avatar_file'], 'dept-account-' . $account->dept_acc_id . '-avatar');

        $account->update(['profile_image' => $path]);
        if ($account->department_id) {
            Department::where('department_id', $account->department_id)->update(['profile_image' => $path]);
        }
        ProfileImage::deleteUnused(...$old);

        AuditLogger::log('Updated profile picture', 'department_account:' . $account->dept_acc_id, null, AuditLogger::actorFor('dept', $account), $request);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully!',
            'profile_image' => $path,
        ]);
    }
}
