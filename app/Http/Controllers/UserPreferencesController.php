<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;

class UserPreferencesController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $pref = UserNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'allow_group_notifications' => true,
                'allow_motivational_notifications' => true,
                'allow_personal_reminders' => true,
                'preferred_personal_reminder_hour' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'preferences' => [
                'allow_group_notifications' => (bool) $pref->allow_group_notifications,
                'allow_motivational_notifications' => (bool) $pref->allow_motivational_notifications,
                'allow_personal_reminders' => (bool) $pref->allow_personal_reminders,
                'preferred_personal_reminder_hour' => $pref->preferred_personal_reminder_hour,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'allow_group_notifications' => ['nullable','boolean'],
            'allow_motivational_notifications' => ['nullable','boolean'],
            'allow_personal_reminders' => ['nullable','boolean'],
            'preferred_personal_reminder_hour' => ['nullable','regex:/^(?:[01]?\d|2[0-3])$/'],
        ]);

        $pref = UserNotificationPreference::firstOrCreate(['user_id' => $user->id]);
        foreach (['allow_group_notifications','allow_motivational_notifications','allow_personal_reminders','preferred_personal_reminder_hour'] as $k) {
            if (array_key_exists($k, $data)) {
                $pref->{$k} = $data[$k];
            }
        }
        $pref->save();

        return response()->json(['ok' => true]);
    }
}

