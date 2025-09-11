<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var Admin|null $admin */
        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (!$admin->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your admin account has been deactivated.'],
            ]);
        }

        // Revoke previous tokens
        $admin->tokens()->delete();

        // Create new token
        $token = $admin->createToken('admin-panel')->plainTextToken;

        // Update last login
        $admin->updateLastLogin($request->ip());

        return response()->json([
            'user' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->role,
                'avatar_url' => $admin->avatar_url,
                'last_login_at' => $admin->last_login_at?->toISOString(),
                'created_at' => $admin->created_at->toISOString(),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get current admin user
     */
    public function me(Request $request)
    {
        $admin = $request->user();
        return response()->json([
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => $admin->role,
            'avatar_url' => $admin->avatar_url,
            'last_login_at' => $admin->last_login_at?->toISOString(),
            'created_at' => $admin->created_at->toISOString(),
        ]);
    }

    /**
     * Refresh token (optional)
     */
    public function refresh(Request $request)
    {
        $admin = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $admin->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}