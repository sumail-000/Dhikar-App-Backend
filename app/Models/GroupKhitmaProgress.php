<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupKhitmaProgress extends Model
{
    use HasFactory;

    protected $table = 'group_khitma_progress';

    protected $fillable = [
        'group_id',
        'user_id',
        'reading_date',
        'juzz_read',
        'surah_read',
        'page_read',
        'start_verse',
        'end_verse',
        'notes',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'juzz_read' => 'integer',
        'surah_read' => 'integer',
        'page_read' => 'integer',
        'start_verse' => 'integer',
        'end_verse' => 'integer',
    ];

    /**
     * Get the group that this progress belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who made this progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the latest reading progress for a user in a specific group.
     */
    public static function getLatestProgressForUser(int $groupId, int $userId): ?self
    {
        return self::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->latest('reading_date')
            ->latest('created_at')
            ->first();
    }

    /**
     * Get total progress for a group (all members combined).
     */
    public static function getGroupProgressSummary(int $groupId): array
    {
        $totalPages = self::where('group_id', $groupId)
            ->distinct('page_read')
            ->count('page_read');

        $uniqueJuzz = self::where('group_id', $groupId)
            ->distinct('juzz_read')
            ->count('juzz_read');

        $memberContributions = self::where('group_id', $groupId)
            ->selectRaw('user_id, COUNT(DISTINCT page_read) as pages_read, COUNT(DISTINCT juzz_read) as juzz_read')
            ->groupBy('user_id')
            ->with('user:id,username')
            ->get();

        return [
            'total_pages_read' => $totalPages,
            'unique_juzz_read' => $uniqueJuzz,
            'total_juzz' => 30,
            'completion_percentage' => round(($uniqueJuzz / 30) * 100, 2),
            'member_contributions' => $memberContributions,
        ];
    }

    /**
     * Get user's total group khitma progress across all groups.
     */
    public static function getUserTotalGroupProgress(int $userId): array
    {
        // Get all groups user is a member of
        $userGroups = Group::whereIn('id', function ($query) use ($userId) {
            $query->select('group_id')
                ->from('group_members')
                ->where('user_id', $userId);
        })->where('type', 'khitma')->get();

        $completedGroups = 0;
        $totalGroups = $userGroups->count();
        $totalProgress = 0;

        foreach ($userGroups as $group) {
            $assignments = KhitmaAssignment::where('group_id', $group->id)->get();
            $completedJuzz = $assignments->where('status', 'completed')->count();
            $groupProgress = ($completedJuzz / 30) * 100;
            $totalProgress += $groupProgress;

            if ($completedJuzz >= 30) {
                $completedGroups++;
            }
        }

        $averageProgress = $totalGroups > 0 ? round($totalProgress / $totalGroups, 2) : 0;

        return [
            'total_groups' => $totalGroups,
            'completed_groups' => $completedGroups,
            'average_progress' => $averageProgress,
            'groups' => $userGroups->map(function ($group) {
                $assignments = KhitmaAssignment::where('group_id', $group->id)->get();
                $completedJuzz = $assignments->where('status', 'completed')->count();
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'progress_percentage' => round(($completedJuzz / 30) * 100, 2),
                    'completed_juzz' => $completedJuzz,
                    'total_juzz' => 30,
                ];
            }),
        ];
    }
}
