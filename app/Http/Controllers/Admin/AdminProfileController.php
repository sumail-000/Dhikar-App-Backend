<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class AdminProfileController extends Controller
{
    /**
     * Update the authenticated admin's profile (username and avatar).
     */
    public function update(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'username' => ['required','string','max:255', Rule::unique('admins','username')->ignore($admin->id)],
            'avatar' => ['nullable','image','mimes:jpeg,jpg,png','max:2048'],
        ]);

        if (isset($validated['username'])) {
            $admin->username = $validated['username'];
        }

        if ($request->hasFile('avatar')) {
            if ($admin->avatar_path) {
                Storage::disk('public')->delete($admin->avatar_path);
            }
            $path = $request->file('avatar')->store('admin-avatars', 'public');
            $admin->avatar_path = $path;
        }

        $admin->save();

        return response()->json([
            'ok' => true,
            'admin' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->role,
                'avatar_url' => $admin->avatar_url,
                'last_login_at' => $admin->last_login_at?->toISOString(),
                'created_at' => $admin->created_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Delete the authenticated admin's avatar.
     */
    public function deleteAvatar(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user();
        if ($admin->avatar_path) {
            Storage::disk('public')->delete($admin->avatar_path);
            $admin->avatar_path = null;
            $admin->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Stream the authenticated admin's avatar image.
     */
    public function avatar(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user();
        if (! $admin->avatar_path) {
            return response()->json(['error' => 'No avatar'], 404);
        }
        if (! Storage::disk('public')->exists($admin->avatar_path)) {
            return response()->json(['error' => 'Avatar not found'], 404);
        }
        return Storage::disk('public')->response($admin->avatar_path);
    }


    /**
     * Change password for authenticated admin.
     */
    public function changePassword(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'current_password' => ['required','string'],
            'new_password' => ['required','string','min:8','confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            return response()->json([
                'ok' => false,
                'error' => 'Current password is incorrect.',
            ], 422);
        }

        $admin->password = $data['new_password'];
        $admin->save();

        // Revoke existing tokens optionally (security)
        $admin->tokens()->delete();

        return response()->json(['ok' => true, 'message' => 'Password changed successfully. Please login again.']);
    }
}
