<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyActivity extends Model
{
    use HasFactory;

    protected $table = 'user_daily_activity';

    protected $fillable = [
        'user_id',
        'activity_date',
        'opened',
        'reading',
        'first_opened_at',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'opened' => 'boolean',
        'reading' => 'boolean',
        'first_opened_at' => 'datetime',
    ];

    /**
     * Get the user that owns this activity record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}