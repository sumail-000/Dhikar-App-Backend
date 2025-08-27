<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InviteToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'token', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public static function generateForGroup(Group $group, ?\DateTimeInterface $expiresAt = null): self
    {
        $token = new self();
        $token->group_id = $group->id;
        $token->token = Str::random(48);
        $token->expires_at = $expiresAt;
        $token->save();
        return $token;
    }
}
