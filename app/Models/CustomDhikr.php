<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDhikr extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'title_arabic',
        'subtitle',
        'subtitle_arabic',
        'arabic_text',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

