<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'allow_group_notifications',
        'allow_motivational_notifications',
        'allow_personal_reminders',
        'preferred_personal_reminder_hour',
    ];

    public static function allowsGroup(int $userId): bool
    {
        $p = static::where('user_id', $userId)->first();
        return $p ? (bool) $p->allow_group_notifications : true;
    }

    public static function allowsMotivation(int $userId): bool
    {
        $p = static::where('user_id', $userId)->first();
        return $p ? (bool) $p->allow_motivational_notifications : true;
    }

    public static function allowsPersonal(int $userId): bool
    {
        $p = static::where('user_id', $userId)->first();
        return $p ? (bool) $p->allow_personal_reminders : true;
    }
}

