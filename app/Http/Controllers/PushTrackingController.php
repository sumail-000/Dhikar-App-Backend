<?php

namespace App\Http\Controllers;

use App\Models\PushEvent;
use Illuminate\Http\Request;

class PushTrackingController extends Controller
{
    // POST /api/push/received
    public function received(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'notification_type' => ['nullable','string','max:255'],
            'device_token' => ['nullable','string','max:2048'],
            'data' => ['nullable','array'], // raw FCM data payload
            'title' => ['nullable','string','max:255'],
            'body' => ['nullable','string'],
        ]);

        $type = $data['notification_type'] ?? ($data['data']['type'] ?? null);

        PushEvent::create([
            'user_id' => $user?->id,
            'device_token' => $data['device_token'] ?? null,
            'notification_type' => $type,
            'event' => 'received',
            'payload' => [
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'data' => $data['data'] ?? null,
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    // POST /api/push/opened
    public function opened(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'notification_type' => ['nullable','string','max:255'],
            'device_token' => ['nullable','string','max:2048'],
            'data' => ['nullable','array'],
            'title' => ['nullable','string','max:255'],
            'body' => ['nullable','string'],
        ]);

        $type = $data['notification_type'] ?? ($data['data']['type'] ?? null);

        PushEvent::create([
            'user_id' => $user?->id,
            'device_token' => $data['device_token'] ?? null,
            'notification_type' => $type,
            'event' => 'opened',
            'payload' => [
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'data' => $data['data'] ?? null,
            ],
        ]);

        return response()->json(['ok' => true]);
    }
}
