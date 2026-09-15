<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            ],
            'employee_id' => [
                'nullable', 'string', 'max:100',
                Rule::unique('department_account', 'employee_id')
                    ->ignore($account->dept_acc_id, 'dept_acc_id')
                    ->where(fn ($q) => $q->where('employee_id', '!=', '')),
            ],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6'],
            'confirm_password' => ['nullable', 'required_with:new_password', 'same:new_password'],
        ]);

        if (!empty($data['new_password'])) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $account->password)) {
                return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 400);
            }
        }

        $update = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
        ];

        // NOTE vs legacy: the `role` field is no longer accepted here (a dept
        // account could POST any value to grant itself a different role).
        if (!empty($data['new_password'])) {
            $update['password'] = Hash::make($data['new_password']);
        }

        $account->update($update);

        return response()->json(['success' => true, 'message' => 'Profile information updated successfully!']);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ]);

        $account = auth('dept')->user();
        $file = $data['avatar_file'];
        $filename = 'images/dept-account-' . $account->dept_acc_id . '-avatar.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));
        $path = '/storage/' . $filename;

        $account->update(['profile_image' => $path]);
        if ($account->department_id) {
            Department::where('department_id', $account->department_id)->update(['profile_image' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully!',
            'profile_image' => $path,
        ]);
    }
}
