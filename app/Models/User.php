<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar_path',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's daily activity records
     */
    public function dailyActivity()
    {
        return $this->hasMany(\App\Models\UserDailyActivity::class);
    }

    /**
     * Get the user's group memberships
     */
    public function groups()
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Get the user's dhikr group memberships
     */
    public function dhikrGroups()
    {
        return $this->hasMany(DhikrGroupMember::class);
    }

    /**
     * Get the user's device tokens
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get the user's notifications
     */
    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    /**
     * Get the user's notification preferences
     */
    public function notificationPreferences()
    {
        return $this->hasOne(UserNotificationPreference::class);
    }
}
