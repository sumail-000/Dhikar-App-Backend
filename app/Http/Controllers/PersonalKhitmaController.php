<?php

namespace App\Http\Controllers;

use App\Models\PersonalKhitmaProgress;
use App\Models\PersonalKhitmaDailyProgress;
use App\Models\UthmanicHafsQuranText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PersonalKhitmaController extends Controller
{
    /**
     * Get all personal khitmas for authenticated user
     * GET /api/personal-khitma
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $khitmas = PersonalKhitmaProgress::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($khitma) {
                return [
                    'id' => $khitma->id,
                    'khitma_name' => $khitma->khitma_name,
                    'total_days' => $khitma->total_days,
                    'start_date' => $khitma->start_date->toDateString(),
                    'target_completion_date' => $khitma->target_completion_date->toDateString(),
                    'current_juzz' => $khitma->current_juzz,
                    'current_surah' => $khitma->current_surah,
                    'current_page' => $khitma->current_page,
                    'current_verse' => $khitma->current_verse,
                    'total_pages_read' => $khitma->total_pages_read,
                    'juzz_completed' => $khitma->juzz_completed,
                    'completion_percentage' => (float) $khitma->completion_percentage,
                    'status' => $khitma->status,
                    'last_read_at' => $khitma->last_read_at?->toISOString(),
                    'completed_at' => $khitma->completed_at?->toISOString(),
                    'is_on_track' => $khitma->isOnTrack(),
                    'daily_pages_target' => $khitma->getDailyPagesTarget(),
                    'reading_streak' => $khitma->getReadingStreak(),
                ];
            });

        return response()->json([
            'ok' => true,
            'khitmas' => $khitmas,
        ]);
    }

    /**
     * Create a new personal khitma
     * POST /api/personal-khitma
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'khitma_name' => 'required|string|max:255',
            'total_days' => 'required|integer|min:1|max:365',
            'start_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $startDate = isset($data['start_date']) && $data['start_date'] ? Carbon::parse($data['start_date']) : now();
        $targetCompletionDate = $startDate->copy()->addDays($data['total_days'] - 1);

        // Check if user already has an active khitma
        $existingActive = PersonalKhitmaProgress::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return response()->json([
                'ok' => false,
                'message' => 'You already have an active personal khitma. Please complete or pause the current one before starting a new one.',
            ], 422);
        }

        $khitma = PersonalKhitmaProgress::create([
            'user_id' => $user->id,
            'khitma_name' => $data['khitma_name'],
            'total_days' => $data['total_days'],
            'start_date' => $startDate,
            'target_completion_date' => $targetCompletionDate,
            'current_juzz' => 1,
            'current_surah' => 1,
            'current_page' => 1,
            'current_verse' => 1,
            'status' => 'active',
        ]);

        return response()->json([
            'ok' => true,
            'khitma' => [
                'id' => $khitma->id,
                'khitma_name' => $khitma->khitma_name,
                'total_days' => $khitma->total_days,
                'start_date' => $khitma->start_date->toDateString(),
                'target_completion_date' => $khitma->target_completion_date->toDateString(),
                'current_juzz' => $khitma->current_juzz,
                'current_surah' => $khitma->current_surah,
                'current_page' => $khitma->current_page,
                'status' => $khitma->status,
                'daily_pages_target' => $khitma->getDailyPagesTarget(),
            ],
        ], 201);
    }

    /**
     * Get specific personal khitma details
     * GET /api/personal-khitma/{id}
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        
        $khitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->findOrFail($id);

        // Get recent daily progress
        $recentProgress = PersonalKhitmaDailyProgress::where('khitma_id', $khitma->id)
            ->orderByDesc('reading_date')
            ->limit(7)
            ->get()
            ->map(function ($progress) {
                return [
                    'id' => $progress->id,
                    'reading_date' => $progress->reading_date->toDateString(),
                    'juzz_read' => $progress->juzz_read,
                    'surah_read' => $progress->surah_read,
                    'start_page' => $progress->start_page,
                    'end_page' => $progress->end_page,
                    'pages_read' => $progress->pages_read,
                    'reading_duration_minutes' => $progress->reading_duration_minutes,
                    'notes' => $progress->notes,
                ];
            });

        return response()->json([
            'ok' => true,
            'khitma' => [
                'id' => $khitma->id,
                'khitma_name' => $khitma->khitma_name,
                'total_days' => $khitma->total_days,
                'start_date' => $khitma->start_date->toDateString(),
                'target_completion_date' => $khitma->target_completion_date->toDateString(),
                'current_juzz' => $khitma->current_juzz,
                'current_surah' => $khitma->current_surah,
                'current_page' => $khitma->current_page,
                'current_verse' => $khitma->current_verse,
                'total_pages_read' => $khitma->total_pages_read,
                'juzz_completed' => $khitma->juzz_completed,
                'completion_percentage' => (float) $khitma->completion_percentage,
                'status' => $khitma->status,
                'last_read_at' => $khitma->last_read_at?->toISOString(),
                'completed_at' => $khitma->completed_at?->toISOString(),
                'is_on_track' => $khitma->isOnTrack(),
                'daily_pages_target' => $khitma->getDailyPagesTarget(),
                'reading_streak' => $khitma->getReadingStreak(),
            ],
            'recent_progress' => $recentProgress,
        ]);
    }

    /**
     * Save reading progress for current session
     * POST /api/personal-khitma/{id}/progress
     */
    public function saveProgress(Request $request, int $id)
    {
        $user = $request->user();
        
        $khitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'juzz_read' => 'required|integer|min:1|max:30',
            'surah_read' => 'required|integer|min:1|max:114',
            'start_page' => 'required|integer|min:1|max:604',
            'end_page' => 'required|integer|min:1|max:604',
            'start_verse' => 'nullable|integer|min:1',
            'end_verse' => 'nullable|integer|min:1',
            'reading_duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // Validate page range
        if ($data['start_page'] > $data['end_page']) {
            return response()->json([
                'ok' => false,
                'message' => 'Start page cannot be greater than end page.',
            ], 422);
        }

        $pagesRead = $data['end_page'] - $data['start_page'] + 1;
        $today = now()->toDateString();

        DB::transaction(function () use ($khitma, $data, $pagesRead, $today) {
            // Check if there's already a reading session for today
            $existingToday = PersonalKhitmaDailyProgress::where('khitma_id', $khitma->id)
                ->where('reading_date', $today)
                ->first();

            if ($existingToday) {
                // Update existing session
                $existingToday->update([
                    'juzz_read' => $data['juzz_read'],
                    'surah_read' => $data['surah_read'],
                    'start_page' => min($existingToday->start_page, $data['start_page']),
                    'end_page' => max($existingToday->end_page, $data['end_page']),
                    'pages_read' => $existingToday->pages_read + $pagesRead,
                    'start_verse' => $data['start_verse'] ?? $existingToday->start_verse,
                    'end_verse' => $data['end_verse'] ?? $existingToday->end_verse,
                    'reading_duration_minutes' => ($existingToday->reading_duration_minutes ?? 0) + (isset($data['reading_duration_minutes']) ? $data['reading_duration_minutes'] : 0),
                    'notes' => isset($data['notes']) ? $data['notes'] : $existingToday->notes,
                ]);
            } else {
                // Create new session
                PersonalKhitmaDailyProgress::create([
                    'khitma_id' => $khitma->id,
                    'reading_date' => $today,
                    'juzz_read' => $data['juzz_read'],
                    'surah_read' => $data['surah_read'],
                    'start_page' => $data['start_page'],
                    'end_page' => $data['end_page'],
                    'pages_read' => $pagesRead,
                    'start_verse' => isset($data['start_verse']) ? $data['start_verse'] : null,
                    'end_verse' => isset($data['end_verse']) ? $data['end_verse'] : null,
                    'reading_duration_minutes' => isset($data['reading_duration_minutes']) ? $data['reading_duration_minutes'] : null,
                    'notes' => isset($data['notes']) ? $data['notes'] : null,
                ]);
            }

            // Update khitma progress
            $currentPage = max($data['end_page'], $khitma->current_page);
            $currentJuzz = $data['juzz_read'];
            
            // Calculate Juz completed based on current page position
            // More accurate Juz boundaries based on standard Mushaf pages
            $juzzCompleted = 0;
            if ($currentPage >= 1) $juzzCompleted = 1;
            if ($currentPage >= 22) $juzzCompleted = 2;
            if ($currentPage >= 42) $juzzCompleted = 3;
            if ($currentPage >= 62) $juzzCompleted = 4;
            if ($currentPage >= 82) $juzzCompleted = 5;
            if ($currentPage >= 102) $juzzCompleted = 6;
            if ($currentPage >= 122) $juzzCompleted = 7;
            if ($currentPage >= 142) $juzzCompleted = 8;
            if ($currentPage >= 162) $juzzCompleted = 9;
            if ($currentPage >= 182) $juzzCompleted = 10;
            if ($currentPage >= 202) $juzzCompleted = 11;
            if ($currentPage >= 222) $juzzCompleted = 12;
            if ($currentPage >= 242) $juzzCompleted = 13;
            if ($currentPage >= 262) $juzzCompleted = 14;
            if ($currentPage >= 282) $juzzCompleted = 15;
            if ($currentPage >= 302) $juzzCompleted = 16;
            if ($currentPage >= 322) $juzzCompleted = 17;
            if ($currentPage >= 342) $juzzCompleted = 18;
            if ($currentPage >= 362) $juzzCompleted = 19;
            if ($currentPage >= 382) $juzzCompleted = 20;
            if ($currentPage >= 402) $juzzCompleted = 21;
            if ($currentPage >= 422) $juzzCompleted = 22;
            if ($currentPage >= 442) $juzzCompleted = 23;
            if ($currentPage >= 462) $juzzCompleted = 24;
            if ($currentPage >= 482) $juzzCompleted = 25;
            if ($currentPage >= 502) $juzzCompleted = 26;
            if ($currentPage >= 522) $juzzCompleted = 27;
            if ($currentPage >= 542) $juzzCompleted = 28;
            if ($currentPage >= 562) $juzzCompleted = 29;
            if ($currentPage >= 582) $juzzCompleted = 30;
            
            $khitma->update([
                'current_juzz' => $currentJuzz,
                'current_surah' => $data['surah_read'],
                'current_page' => $currentPage,
                'current_verse' => isset($data['end_verse']) ? $data['end_verse'] : $khitma->current_verse,
                'total_pages_read' => $currentPage,
                'juzz_completed' => $juzzCompleted,
                'last_read_at' => now(),
            ]);

            // Update completion percentage
            $khitma->updateCompletionPercentage();

            // Check if khitma is completed
            if ($khitma->total_pages_read >= 604) {
                $khitma->markAsCompleted();
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Reading progress saved successfully!',
            'khitma' => [
                'id' => $khitma->id,
                'current_juzz' => $khitma->current_juzz,
                'current_surah' => $khitma->current_surah,
                'current_page' => $khitma->current_page,
                'total_pages_read' => $khitma->total_pages_read,
                'completion_percentage' => (float) $khitma->completion_percentage,
                'status' => $khitma->status,
                'is_completed' => $khitma->status === 'completed',
            ],
        ]);
    }

    /**
     * Update khitma status (pause/resume/complete)
     * PATCH /api/personal-khitma/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();
        
        $khitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,paused,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->status;

        if ($newStatus === 'completed') {
            $khitma->markAsCompleted();
        } else {
            $khitma->update(['status' => $newStatus]);
        }

        return response()->json([
            'ok' => true,
            'message' => "Khitma status updated to {$newStatus}.",
            'khitma' => [
                'id' => $khitma->id,
                'status' => $khitma->status,
                'completed_at' => $khitma->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Delete a personal khitma
     * DELETE /api/personal-khitma/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        
        $khitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->findOrFail($id);

        $khitma->delete(); // This will cascade delete daily progress records

        return response()->json([
            'ok' => true,
            'message' => 'Personal khitma deleted successfully.',
        ]);
    }

    /**
     * Get user's active personal khitma
     * GET /api/personal-khitma/active
     */
    public function getActiveKhitma(Request $request)
    {
        $user = $request->user();
        
        $activeKhitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$activeKhitma) {
            return response()->json([
                'ok' => true,
                'active_khitma' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'active_khitma' => [
                'id' => $activeKhitma->id,
                'khitma_name' => $activeKhitma->khitma_name,
                'total_days' => $activeKhitma->total_days,
                'start_date' => $activeKhitma->start_date->toDateString(),
                'target_completion_date' => $activeKhitma->target_completion_date->toDateString(),
                'current_juzz' => $activeKhitma->current_juzz,
                'current_surah' => $activeKhitma->current_surah,
                'current_page' => $activeKhitma->current_page,
                'current_verse' => $activeKhitma->current_verse,
                'total_pages_read' => $activeKhitma->total_pages_read,
                'juzz_completed' => $activeKhitma->juzz_completed,
                'completion_percentage' => (float) $activeKhitma->completion_percentage,
                'status' => $activeKhitma->status,
                'last_read_at' => $activeKhitma->last_read_at?->toISOString(),
                'is_on_track' => $activeKhitma->isOnTrack(),
                'daily_pages_target' => $activeKhitma->getDailyPagesTarget(),
                'reading_streak' => $activeKhitma->getReadingStreak(),
            ],
        ]);
    }

    /**
     * Get reading statistics for a khitma
     * GET /api/personal-khitma/{id}/statistics
     */
    public function statistics(Request $request, int $id)
    {
        $user = $request->user();
        
        $khitma = PersonalKhitmaProgress::where('user_id', $user->id)
            ->findOrFail($id);

        $dailyProgress = $khitma->dailyProgress()->get();

        $stats = [
            'total_reading_days' => $dailyProgress->count(),
            'total_reading_time_minutes' => $dailyProgress->sum('reading_duration_minutes'),
            'average_pages_per_day' => $dailyProgress->count() > 0 ? round($dailyProgress->avg('pages_read'), 2) : 0,
            'reading_streak' => $khitma->getReadingStreak(),
            'is_on_track' => $khitma->isOnTrack(),
            'days_remaining' => max(0, now()->diffInDays($khitma->target_completion_date, false)),
            'pages_remaining' => max(0, 604 - $khitma->total_pages_read),
            'daily_progress_chart' => $dailyProgress->map(function ($progress) {
                return [
                    'date' => $progress->reading_date->toDateString(),
                    'pages_read' => $progress->pages_read,
                    'reading_time' => $progress->reading_duration_minutes ?? 0,
                ];
            })->sortBy('date')->values(),
        ];

        return response()->json([
            'ok' => true,
            'statistics' => $stats,
        ]);
    }
}
