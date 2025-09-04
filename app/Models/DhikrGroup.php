<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DhikrGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'creator_id',
        'days_to_complete',
        'is_public',
        'members_target',
        'dhikr_target',
        'dhikr_count',
        'dhikr_title',
        'dhikr_title_arabic',
    ];

    protected $casts = [
        'days_to_complete' => 'integer',
        'is_public' => 'boolean',
        'members_target' => 'integer',
        'dhikr_target' => 'integer',
        'dhikr_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DhikrGroupMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(DhikrInviteToken::class);
    }

    public function isAdmin(User $user): bool
    {
        if ($this->creator_id === $user->id) return true;
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}

