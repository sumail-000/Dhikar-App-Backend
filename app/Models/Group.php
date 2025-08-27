<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // 'khitma' | 'dhikr'
        'creator_id',
        'days_to_complete',
        'start_date',
    ];

    protected $casts = [
        'days_to_complete' => 'integer',
        'start_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KhitmaAssignment::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(InviteToken::class);
    }

    public function isAdmin(User $user): bool
    {
        if ($this->creator_id === $user->id) {
            return true;
        }
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}
