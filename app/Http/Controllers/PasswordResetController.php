<?php

namespace App\Http\Controllers;

use App\Mail\ResetCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class PasswordResetController extends Controller
{
    public function requestReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email', Rule::exists('users', 'email')]]);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $request->email],
            [
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(10),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Mail::to($request->email)->send(new ResetCodeMail($code));

        return response()->json(['message' => __('messages.reset_code_sent')]);
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email'],
            'code' => ['required','digits:6'],
        ]);

        $row = DB::table('password_reset_codes')
            ->where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->first();

        if (!$row) {
            return response()->json(['message' => __('Invalid code.')], 422);
        }
        if (Carbon::parse($row->expires_at)->isPast()) {
            return response()->json(['message' => __('The code has expired.')], 422);
        }
        return response()->json(['message' => __('Code verified.')]);
    }

    public function resetWithCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email', Rule::exists('users','email')],
            'code' => ['required','digits:6'],
            'password' => ['required','string','min:8','confirmed'],
        ]);

        $row = DB::table('password_reset_codes')
            ->where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->first();

        if (!$row) {
            return response()->json(['message' => __('Invalid code.')], 422);
        }
        if (Carbon::parse($row->expires_at)->isPast()) {
            return response()->json(['message' => __('The code has expired.')], 422);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Invalidate used code
        DB::table('password_reset_codes')->where('email', $validated['email'])->delete();

        return response()->json(['message' => __('Password has been reset.')]);
    }
}
