<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request)
    {
        $user = $request->user();
        $items = AppNotification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (AppNotification $n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'data' => $n->data,
                    'read_at' => optional($n->read_at)->toISOString(),
                    'created_at' => optional($n->created_at)->toISOString(),
                ];
            });

        return response()->json(['ok' => true, 'notifications' => $items]);
    }

    // PATCH /api/notifications/{id}/read
    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        $data = $request->validate([
            'read' => ['required','boolean'],
        ]);
        $n = AppNotification::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $n->read_at = $data['read'] ? now() : null;
        $n->save();
        return response()->json(['ok' => true]);
    }

    // DELETE /api/notifications/{id}
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $n = AppNotification::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $n->delete();
        return response()->json(['ok' => true]);
    }
}

