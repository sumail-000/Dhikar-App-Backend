<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required','string','regex:/^[\p{L}\s]+$/u', Rule::unique('users','username')->ignore($user->id)],
            'avatar' => ['nullable','image','mimes:jpeg,jpg,png','max:2048'], // 2MB
        ], [
            'username.regex' => __('messages.username_invalid'),
        ]);

        if (isset($validated['username'])) {
            $user->username = $validated['username'];
        }

        if ($request->hasFile('avatar')) {
            // delete old
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
        }

        $user->save();

        return response()->json([
            'message' => __('messages.profile_updated'),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_path ? url('storage/'.$user->avatar_path) : null,
                'joined_at' => optional($user->created_at)->toISOString(),
            ],
        ]);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }
        return response()->json(['message' => __('messages.avatar_deleted')]);
    }
}
