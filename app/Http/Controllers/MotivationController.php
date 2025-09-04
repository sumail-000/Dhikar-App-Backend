<?php

namespace App\Http\Controllers;

use App\Models\MotivationalVerse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MotivationController extends Controller
{
    // GET /api/motivation
    public function today(Request $request)
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';
        $today = Carbon::now($tz)->toDateString();

        // If already assigned today, return the same
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->where('shown_date', $today)
            ->first();
        if ($existing) {
            $row = MotivationalVerse::where('is_active', true)->find($existing->verse_id);
            if ($row) {
                return response()->json([
                    'ok' => true,
                    'verse' => [
                        'id' => $row->id,
                        'surah_name' => $row->surah_name,
                        'surah_name_ar' => $row->surah_name_ar,
                        'surah_number' => $row->surah_number,
                        'ayah_number' => $row->ayah_number,
                        'arabic_text' => $row->arabic_text,
                        'translation' => $row->translation,
                    ],
                ]);
            }
        }

        // Pool of active verses
        $activeIds = MotivationalVerse::where('is_active', true)->pluck('id')->all();
        if (empty($activeIds)) {
            return response()->json(['ok' => true, 'verse' => null]);
        }

        // Seen verses by user (not limited to today)
        $seenIds = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('verse_id')
            ->all();

        // Remaining pool
        $remaining = array_values(array_diff($activeIds, $seenIds));
        if (empty($remaining)) {
            // Reset cycle: clear history and start fresh
            DB::table('motivational_verse_user')->where('user_id', $user->id)->delete();
            $remaining = $activeIds;
        }

        // Pick random verse from remaining
        $verseId = $remaining[array_rand($remaining)];
        $row = MotivationalVerse::find($verseId);

        // Persist today's assignment (ensure uniqueness of (user, date))
        DB::table('motivational_verse_user')->updateOrInsert(
            ['user_id' => $user->id, 'shown_date' => $today],
            ['verse_id' => $verseId, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json([
            'ok' => true,
            'verse' => [
                'id' => $row?->id,
                'surah_name' => $row?->surah_name,
                'surah_name_ar' => $row?->surah_name_ar,
                'surah_number' => $row?->surah_number,
                'ayah_number' => $row?->ayah_number,
                'arabic_text' => $row?->arabic_text,
                'translation' => $row?->translation,
            ],
        ]);
    }
}
