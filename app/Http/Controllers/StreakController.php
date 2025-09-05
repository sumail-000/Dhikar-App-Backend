<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StreakController extends Controller
{
    // GET /api/streak
    public function get(Request $request)
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';
        $today = Carbon::now($tz)->startOfDay();
        $todayStr = $today->toDateString();

        // Today open
        $openedToday = DB::table('user_daily_activity')
            ->where('user_id', $user->id)
            ->where('activity_date', $todayStr)
            ->where('opened', true)
            ->exists();

        // Reading dates from all sources
        $dates = collect();

        // Personal khitma
        $dates = $dates->merge(
            DB::table('personal_khitma_daily_progress')
                ->join('personal_khitma_progress', 'personal_khitma_daily_progress.khitma_id', '=', 'personal_khitma_progress.id')
                ->where('personal_khitma_progress.user_id', $user->id)
                ->pluck('personal_khitma_daily_progress.reading_date')
        );

        // Group khitma
        $dates = $dates->merge(
            DB::table('group_khitma_progress')
                ->where('user_id', $user->id)
                ->pluck('reading_date')
        );

        // Dhikr group daily
        if (Schema::hasTable('dhikr_group_daily_contributions')) {
            $dates = $dates->merge(
                DB::table('dhikr_group_daily_contributions')
                    ->where('user_id', $user->id)
                    ->pluck('contribution_date')
            );
        }

        // Client-only personal dhikr: reading flag
        if (Schema::hasTable('user_daily_activity')) {
            $dates = $dates->merge(
                DB::table('user_daily_activity')
                    ->where('user_id', $user->id)
                    ->where('reading', true)
                    ->pluck('activity_date')
            );
        }

        $uniqueDates = $dates->map(fn($d) => Carbon::parse($d, $tz)->toDateString())->unique()->sort()->values();

        $hasReadingOn = function (Carbon $day) use ($uniqueDates): bool {
            return $uniqueDates->contains($day->toDateString());
        };

        // Compute last streak excluding today
        $streak = 0;
        $cursor = $today->copy()->subDay();
        while ($hasReadingOn($cursor)) {
            $streak++;
            $cursor->subDay();
        }

        $readingToday = $hasReadingOn($today);
        $todayMet = $openedToday && $readingToday;
        $currentStreak = $todayMet ? $streak + 1 : $streak;

        return response()->json([
            'ok' => true,
            'streak' => $currentStreak,
            'today_met' => $todayMet,
        ]);
    }
}
