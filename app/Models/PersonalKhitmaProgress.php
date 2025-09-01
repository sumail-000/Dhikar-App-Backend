<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PersonalKhitmaProgress extends Model
{
    use HasFactory;

    protected $table = 'personal_khitma_progress';

    protected $fillable = [
        'user_id',
        'khitma_name',
        'total_days',
        'start_date',
        'target_completion_date',
        'current_juzz',
        'current_surah',
        'current_page',
        'current_verse',
        'total_pages_read',
        'juzz_completed',
        'completion_percentage',
        'status',
        'last_read_at',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'total_days' => 'integer',
        'current_juzz' => 'integer',
        'current_surah' => 'integer',
        'current_page' => 'integer',
        'current_verse' => 'integer',
        'total_pages_read' => 'integer',
        'juzz_completed' => 'integer',
        'completion_percentage' => 'decimal:2',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyProgress(): HasMany
    {
        return $this->hasMany(PersonalKhitmaDailyProgress::class, 'khitma_id');
    }

    /**
     * Calculate completion percentage based on pages read
     */
    public function calculateCompletionPercentage(): float
    {
        $totalPages = 604; // Total pages in the Quran
        return round(($this->total_pages_read / $totalPages) * 100, 2);
    }

    /**
     * Update completion percentage
     */
    public function updateCompletionPercentage(): void
    {
        $this->completion_percentage = $this->calculateCompletionPercentage();
        $this->save();
    }

    /**
     * Mark khitma as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_percentage' => 100.00,
            'total_pages_read' => 604,
            'juzz_completed' => 30,
        ]);
    }

    /**
     * Calculate daily pages target based on remaining days
     */
    public function getDailyPagesTarget(): int
    {
        $remainingPages = 604 - $this->total_pages_read;
        $remainingDays = max(1, now()->diffInDays($this->target_completion_date) + 1);
        return max(1, ceil($remainingPages / $remainingDays));
    }

    /**
     * Check if user is on track with their reading schedule
     */
    public function isOnTrack(): bool
    {
        $daysElapsed = $this->start_date->diffInDays(now()) + 1;
        $expectedPages = ($daysElapsed / $this->total_days) * 604;
        return $this->total_pages_read >= $expectedPages * 0.9; // 10% tolerance
    }

    /**
     * Get reading streak (consecutive days)
     */
    public function getReadingStreak(): int
    {
        $streak = 0;
        $currentDate = now()->toDateString();
        
        while (true) {
            $hasReading = $this->dailyProgress()
                ->where('reading_date', $currentDate)
                ->exists();
                
            if (!$hasReading) {
                break;
            }
            
            $streak++;
            $currentDate = Carbon::parse($currentDate)->subDay()->toDateString();
        }
        
        return $streak;
    }

    /**
     * Scope for active khitmas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for completed khitmas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
