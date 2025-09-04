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
        DB::table('user_daily_activity')->updateOrInsert(
            ['user_id' => $user->id, 'activity_date' => Carbon::today()->toDateString()],
            ['opened' => true, 'updated_at' => now(), 'created_at' => now()]
        );
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
