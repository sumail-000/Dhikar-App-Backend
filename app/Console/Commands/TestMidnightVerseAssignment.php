<?php

namespace App\Console\Commands;

use App\Jobs\AssignDailyMotivationalVerses;
use App\Models\MotivationalVerse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestMidnightVerseAssignment extends Command
{
    protected $signature = 'test:midnight-verse-assignment 
                            {--user-id= : Test with specific user ID}
                            {--dry-run : Show what would happen without assigning verses}';

    protected $description = 'Test the midnight verse assignment system';

    public function handle(): int
    {
        $this->info('🌙 Testing Midnight Verse Assignment System');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $specificUserId = $this->option('user-id');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No verses will be assigned');
        }

        $this->newLine();

        // Test 1: Check system readiness
        $this->testSystemReadiness();

        // Test 2: Check current assignments
        $this->testCurrentAssignments();

        // Test 3: Test assignment logic
        $this->testAssignmentLogic($specificUserId, $isDryRun);

        if (!$isDryRun) {
            // Test 4: Run actual assignment
            $this->runActualAssignment($specificUserId);
        }

        $this->newLine();
        $this->info('✅ Midnight verse assignment test completed!');
        
        return Command::SUCCESS;
    }

    private function testSystemReadiness(): void
    {
        $this->info('🔧 Testing system readiness...');

        $userCount = User::count();
        $verseCount = MotivationalVerse::where('is_active', true)->count();
        
        $this->line("👥 Total users: {$userCount}");
        $this->line("📖 Active verses: {$verseCount}");

        if ($userCount === 0) {
            $this->error('❌ No users found - cannot test assignment');
        }

        if ($verseCount === 0) {
            $this->error('❌ No active verses found - cannot assign verses');
        }

        $this->newLine();
    }

    private function testCurrentAssignments(): void
    {
        $this->info('📊 Checking current verse assignments...');

        $today = Carbon::today()->toDateString();
        
        $assignments = DB::table('motivational_verse_user')
            ->join('users', 'motivational_verse_user.user_id', '=', 'users.id')
            ->join('motivational_verses', 'motivational_verse_user.verse_id', '=', 'motivational_verses.id')
            ->where('motivational_verse_user.shown_date', $today)
            ->select(
                'users.id as user_id',
                'users.username',
                'motivational_verses.surah_name',
                'motivational_verses.ayah_number',
                'motivational_verse_user.created_at'
            )
            ->get();

        $this->line("📅 Today's assignments ({$today}): " . count($assignments));

        if (count($assignments) > 0) {
            $this->table(
                ['User ID', 'Username', 'Verse', 'Assigned At'],
                $assignments->map(function ($assignment) {
                    return [
                        $assignment->user_id,
                        $assignment->username,
                        "{$assignment->surah_name} {$assignment->ayah_number}",
                        Carbon::parse($assignment->created_at)->format('H:i:s')
                    ];
                })->toArray()
            );
        } else {
            $this->warn('⚠️  No verses assigned for today yet');
        }

        $this->newLine();
    }

    private function testAssignmentLogic($specificUserId, $isDryRun): void
    {
        $this->info('🧮 Testing assignment logic...');

        if ($specificUserId) {
            $users = User::where('id', $specificUserId)->get();
            if ($users->isEmpty()) {
                $this->error("❌ User ID {$specificUserId} not found");
                return;
            }
        } else {
            $users = User::take(3)->get(); // Test with first 3 users
        }

        foreach ($users as $user) {
            $this->line("Testing user: {$user->username} (ID: {$user->id})");

            $tz = $user->timezone ?? 'UTC';
            $today = Carbon::now($tz)->toDateString();

            // Check current assignment
            $existing = DB::table('motivational_verse_user')
                ->where('user_id', $user->id)
                ->where('shown_date', $today)
                ->first();

            if ($existing) {
                $verse = MotivationalVerse::find($existing->verse_id);
                $this->line("   ✅ Already has today's verse: {$verse->surah_name} {$verse->ayah_number}");
            } else {
                // Check what verse would be assigned
                $activeIds = MotivationalVerse::where('is_active', true)->pluck('id')->all();
                $seenIds = DB::table('motivational_verse_user')
                    ->where('user_id', $user->id)
                    ->distinct()
                    ->pluck('verse_id')
                    ->all();

                $remaining = array_diff($activeIds, $seenIds);
                
                if (empty($remaining)) {
                    $this->line("   🔄 Would reset cycle (seen all verses)");
                    $remaining = $activeIds;
                }

                $this->line("   📊 Available verses: " . count($remaining));
                
                if (!empty($remaining)) {
                    $sampleVerseId = $remaining[array_rand($remaining)];
                    $sampleVerse = MotivationalVerse::find($sampleVerseId);
                    $this->line("   📝 Would assign: {$sampleVerse->surah_name} {$sampleVerse->ayah_number}");
                }
            }
        }

        $this->newLine();
    }

    private function runActualAssignment($specificUserId): void
    {
        $this->info('🚀 Running actual verse assignment...');

        if ($specificUserId) {
            $this->warn("⚠️  This will assign verses to user ID: {$specificUserId}");
        } else {
            $this->warn('⚠️  This will assign verses to ALL users');
        }

        $confirmation = $this->confirm('Are you sure you want to proceed?');
        if (!$confirmation) {
            $this->line('❌ Assignment cancelled');
            return;
        }

        try {
            $this->line('⏳ Dispatching assignment job...');
            
            AssignDailyMotivationalVerses::dispatch();
            
            $this->line('✅ Assignment job dispatched successfully to queue');
            $this->line('💡 Check queue logs for execution details');

        } catch (\Exception $e) {
            $this->error('❌ Assignment job failed: ' . $e->getMessage());
        }
    }
}
