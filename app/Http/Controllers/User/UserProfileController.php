<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email')->ignore($account->user_id, 'user_id')],
            'year_level' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:150'],
        ], [
            'email.unique' => 'That email address is already in use by another account.',
        ]);

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

        return response()->json(['success' => true, 'message' => 'Personal information updated successfully!']);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_image_file' => ['required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif'],
        ], [
            'profile_image_file.mimes' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF',
            'profile_image_file.max' => 'Image size must be less than 5MB',
        ]);

        // FIX vs legacy base64-in-LONGTEXT and per-upload ALTER TABLE.
        $file = $data['profile_image_file'];
        $filename = 'images/user-' . auth('user')->user()->user_id . '-profile.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));
        $path = '/storage/' . $filename;

        auth('user')->user()->update(['profile_image' => $path]);

        return response()->json(['success' => true, 'image_url' => $path]);
    }
}
