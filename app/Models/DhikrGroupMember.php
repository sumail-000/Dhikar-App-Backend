<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DhikrGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'dhikr_group_id', 'user_id', 'role', 'joined_at', 'dhikr_contribution',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(DhikrGroup::class, 'dhikr_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

