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

    public static function makeReadableToken(int $length = 12): string
    {
        // Exclude ambiguous chars: I, O, 1, 0
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $result = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }
        // Group into chunks of 4 with dashes (e.g., ABCD-7K9P-4TZQ)
        return implode('-', str_split($result, 4));
    }

    public static function generateForGroup(Group $group, ?\DateTimeInterface $expiresAt = null): self
    {
        // Generate unique, readable token (12 chars grouped 4-4-4)
        do {
            $tokenStr = self::makeReadableToken(12);
        } while (self::where('token', $tokenStr)->exists());

        $token = new self();
        $token->group_id = $group->id;
        $token->token = $tokenStr; // Stored uppercase with dashes
        $token->expires_at = $expiresAt; // Can be null (no expiry)
        $token->save();
        return $token;
    }
}
