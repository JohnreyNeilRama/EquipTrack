<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'admin_employee_id' => $admin->employee_id ?: 'ADM-0001',
            'admin_profile_image' => $admin->profile_image,
            'success_msg' => session('success_msg'),
            'error_msg' => session('error_msg'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();

        $data = $request->validate([
            'admin_full_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:100', Rule::unique('admin', 'email')->ignore($admin->admin_id, 'admin_id')],
            'admin_employee_id' => ['nullable', 'string', 'max:50'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6'],
            'confirm_password' => ['nullable', 'required_with:new_password', 'same:new_password'],
        ], [
            'admin_email.unique' => 'Another admin already uses that email/username.',
            'confirm_password.same' => 'New password and confirm password do not match.',
        ]);

        $update = [
            'name' => $data['admin_full_name'],
            'username' => $data['admin_email'], // legacy kept username synced to email
            'email' => $data['admin_email'],
            'employee_id' => $data['admin_employee_id'] ?: $admin->employee_id,
        ];

        // username has a UNIQUE index and legacy mirrored email into it; a
        // collision with another admin's username must not become a 500.
        $usernameTaken = Admin::where('username', $data['admin_email'])
            ->where('admin_id', '!=', $admin->admin_id)
            ->exists();
        if ($usernameTaken) {
            return redirect()->route('admin.profile')
                ->with('error_msg', 'Another admin already uses that email/username.')
                ->withInput();
        }

        if (!empty($data['new_password'])) {
            // FIX vs legacy: an empty current_password let a forged POST set a new
            // password with zero verification. It is now required to change it.
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $admin->password)) {
                return redirect()->route('admin.profile')
                    ->with('error_msg', 'Current password is incorrect.')
                    ->withInput();
            }
            $update['password'] = Hash::make($data['new_password']);
        }

        $admin->update($update);

        return redirect()->route('admin.profile')->with('success_msg', 'Profile changes saved successfully!');
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_avatar_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ]);

        // FIX vs legacy base64-in-LONGTEXT storage.
        $file = $data['admin_avatar_file'];
        $filename = 'images/admin-' . auth('admin')->user()->admin_id . '-avatar.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));
        $path = '/storage/' . $filename;

        Admin::where('admin_id', auth('admin')->user()->admin_id)->update(['profile_image' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully!',
            'image_url' => $path,
        ]);
    }
}
