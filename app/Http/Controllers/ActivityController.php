<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityController extends Controller
{
    // POST /api/activity/ping
    public function ping(Request $request)
    {
        $user = $request->user();
        $now = now();
        $today = Carbon::today()->toDateString();
        
        // Check if this is the first opening of the day
        $existing = DB::table('user_daily_activity')
            ->where('user_id', $user->id)
            ->where('activity_date', $today)
            ->first();
        
        if ($existing) {
            // Update existing record, keep first_opened_at unchanged
            DB::table('user_daily_activity')
                ->where('user_id', $user->id)
                ->where('activity_date', $today)
                ->update([
                    'opened' => true,
                    'updated_at' => $now
                ]);
        } else {
            // Create new record with first opening time
            DB::table('user_daily_activity')->insert([
                'user_id' => $user->id,
                'activity_date' => $today,
                'opened' => true,
                'reading' => false,
                'first_opened_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
        
        return response()->json(['ok' => true]);
    }

    // POST /api/activity/reading
    public function reading(Request $request)
    {
        $user = $request->user();
        DB::table('user_daily_activity')->updateOrInsert(
            ['user_id' => $user->id, 'activity_date' => Carbon::today()->toDateString()],
            ['reading' => true, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['ok' => true]);
    }
}
