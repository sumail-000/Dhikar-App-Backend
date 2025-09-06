<?php

namespace App\Jobs;

use App\Models\MotivationalVerse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignDailyMotivationalVerses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        // Use notifications queue to match existing worker
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::info('🌙 Starting daily verse assignment at midnight');
        
        $users = User::all();
        $assignedCount = 0;
        
        foreach ($users as $user) {
            try {
                $this->assignTodaysVerseToUser($user);
                $assignedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to assign verse to user {$user->id}: " . $e->getMessage());
            }
        }
        
        Log::info("✅ Completed daily verse assignment: {$assignedCount} users processed");
    }
    
    private function assignTodaysVerseToUser($user): void
    {
        $tz = $user->timezone ?? 'UTC';
        $today = Carbon::now($tz)->toDateString();
        
        // Check if already assigned today
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->where('shown_date', $today)
            ->first();
            
        if ($existing) {
            // Already assigned today, skip
            return;
        }
        
        // Get or assign a new verse (same logic as MotivationController)
        $activeIds = MotivationalVerse::where('is_active', true)->pluck('id')->all();
        if (empty($activeIds)) {
            Log::warning("No active verses available for user {$user->id}");
            return;
        }
        
        $seenIds = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('verse_id')
            ->all();
        
        $remaining = array_values(array_diff($activeIds, $seenIds));
        if (empty($remaining)) {
            // Reset cycle: user has seen all verses
            DB::table('motivational_verse_user')->where('user_id', $user->id)->delete();
            $remaining = $activeIds;
            Log::info("Reset verse cycle for user {$user->id} - seen all verses");
        }
        
        // Pick random verse from remaining
        $verseId = $remaining[array_rand($remaining)];
        $verse = MotivationalVerse::find($verseId);
        
        // Assign today's verse
        DB::table('motivational_verse_user')->insert([
            'user_id' => $user->id,
            'shown_date' => $today,
            'verse_id' => $verseId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        Log::info("Assigned verse '{$verse->surah_name} {$verse->ayah_number}' to user {$user->id} for {$today}");
    }
}
