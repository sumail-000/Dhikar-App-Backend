<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    // POST /api/devices/register
    public function register(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'device_token' => ['required','string','max:4096'],
            'platform' => ['nullable','in:android,ios,web'],
            'locale' => ['nullable','string','max:10'],
            'timezone' => ['nullable','string','max:64'],
        ]);

        $row = DeviceToken::updateOrCreate(
            ['device_token' => $data['device_token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'locale' => $data['locale'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    // POST /api/devices/unregister
    public function unregister(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'device_token' => ['required','string'],
        ]);

        DeviceToken::where('device_token', $data['device_token'])
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}

