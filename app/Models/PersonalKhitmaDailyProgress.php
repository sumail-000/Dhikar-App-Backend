<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalKhitmaDailyProgress extends Model
{
    use HasFactory;

    protected $table = 'personal_khitma_daily_progress';

    protected $fillable = [
        'khitma_id',
        'reading_date',
        'juzz_read',
        'surah_read',
        'start_page',
        'end_page',
        'pages_read',
        'start_verse',
        'end_verse',
        'reading_duration_minutes',
        'notes',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'juzz_read' => 'integer',
        'surah_read' => 'integer',
        'start_page' => 'integer',
        'end_page' => 'integer',
        'pages_read' => 'integer',
        'start_verse' => 'integer',
        'end_verse' => 'integer',
        'reading_duration_minutes' => 'integer',
    ];

    public function khitma(): BelongsTo
    {
        return $this->belongsTo(PersonalKhitmaProgress::class, 'khitma_id');
    }

    /**
     * Scope for today's reading
     */
    public function scopeToday($query)
    {
        return $query->where('reading_date', now()->toDateString());
    }

    /**
     * Scope for this week's reading
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('reading_date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString(),
        ]);
    }

    /**
     * Scope for this month's reading
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('reading_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ]);
    }
}
