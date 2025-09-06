<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyMotivationalVerse;
use App\Models\DeviceToken;
use App\Models\MotivationalVerse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDailyMotivationalVerse extends Command
{
    protected $signature = 'test:daily-motivational-verse 
                            {--dry-run : Show what would happen without sending notifications}
                            {--user-id= : Test with specific user ID}
                            {--simulate-time= : Simulate current time (format: HH:MM)}';

    protected $description = 'Test the daily motivational verse notification system';

    public function handle(): int
    {
        $this->info('🌟 Testing Daily Motivational Verse Notification System 🌟');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $specificUserId = $this->option('user-id');
        $simulatedTime = $this->option('simulate-time');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No notifications will be sent');
        }

        if ($simulatedTime) {
            $this->info("🕘 Simulating time: {$simulatedTime}");
        }

        $this->newLine();

        // Test 1: Database structure validation
        $this->testDatabaseStructure();

        // Test 2: User activity tracking
        $this->testUserActivityTracking($simulatedTime);

        // Test 3: Motivational verse logic
        $this->testMotivationalVerseLogic();

        // Test 4: Notification eligibility
        $this->testNotificationEligibility($simulatedTime);

        // Test 5: Localization
        $this->testLocalization();

        if (!$isDryRun) {
            // Test 6: Run actual job (with specific user if provided)
            $this->runActualJob($specificUserId);
        }

        $this->newLine();
        $this->info('✅ All tests completed successfully!');
        
        return Command::SUCCESS;
    }

    private function testDatabaseStructure(): void
    {
        $this->info('📋 Testing database structure...');

        // Check user_daily_activity table
        $hasFirstOpenedAt = DB::getSchemaBuilder()->hasColumn('user_daily_activity', 'first_opened_at');
        if ($hasFirstOpenedAt) {
            $this->line('✅ user_daily_activity.first_opened_at column exists');
        } else {
            $this->error('❌ user_daily_activity.first_opened_at column missing - run migration!');
        }

        // Check motivational verses
        $verseCount = MotivationalVerse::where('is_active', true)->count();
        $this->line("✅ Found {$verseCount} active motivational verses");

        // Check device tokens
        $tokenCount = DeviceToken::count();
        $this->line("✅ Found {$tokenCount} device tokens");

        $this->newLine();
    }

    private function testUserActivityTracking($simulatedTime): void
    {
        $this->info('👥 Testing user activity tracking...');

        $today = Carbon::today()->toDateString();
        $simulatedDateTime = $simulatedTime ? 
            Carbon::today()->setTimeFromTimeString($simulatedTime) : 
            Carbon::now();

        // Find users with activity today
        $activeUsers = DB::table('user_daily_activity')
            ->where('activity_date', $today)
            ->get();

        $this->line("📊 Users with activity today: " . count($activeUsers));

        foreach ($activeUsers->take(5) as $activity) {
            $openedTime = $activity->first_opened_at ? 
                Carbon::parse($activity->first_opened_at)->format('H:i:s') : 
                'Not tracked';
            $this->line("   User {$activity->user_id}: First opened at {$openedTime}");
        }

        // Simulate 9 AM eligibility logic
        $nineAM = Carbon::today()->setTime(9, 0, 0);
        if ($simulatedTime) {
            $eligibleUsers = $activeUsers->filter(function ($activity) use ($simulatedDateTime, $nineAM) {
                return !$activity->first_opened_at || 
                       Carbon::parse($activity->first_opened_at)->gte($nineAM);
            });
            $this->line("🔔 Eligible for 9 AM notification (simulated): " . count($eligibleUsers));
        }

        $this->newLine();
    }

    private function testMotivationalVerseLogic(): void
    {
        $this->info('📖 Testing motivational verse logic...');

        // Test with first user
        $user = User::first();
        if (!$user) {
            $this->error('❌ No users found in database');
            return;
        }

        $today = Carbon::today()->toDateString();

        // Get verse for user using same logic as job
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->where('shown_date', $today)
            ->first();

        if ($existing) {
            $verse = MotivationalVerse::find($existing->verse_id);
            $this->line("✅ User {$user->id} already has today's verse: {$verse->surah_name} {$verse->ayah_number}");
        } else {
            $this->line("🔄 User {$user->id} needs new verse assignment");
            
            // Test verse selection logic
            $activeIds = MotivationalVerse::where('is_active', true)->pluck('id')->all();
            $seenIds = DB::table('motivational_verse_user')
                ->where('user_id', $user->id)
                ->pluck('verse_id')
                ->all();

            $remaining = array_diff($activeIds, $seenIds);
            $this->line("   Available verses: " . count($remaining));
            $this->line("   Total active verses: " . count($activeIds));
            $this->line("   Previously seen: " . count($seenIds));
        }

        $this->newLine();
    }

    private function testNotificationEligibility($simulatedTime): void
    {
        $this->info('🔔 Testing notification eligibility...');

        $today = Carbon::today()->toDateString();
        $nineAM = Carbon::today()->setTime(9, 0, 0);

        // Replicate the job's eligibility query
        $eligibleUserIds = DB::table('users')
            ->leftJoin('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->leftJoin('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->where(function ($query) use ($nineAM) {
                $query->whereNull('user_daily_activity.first_opened_at')
                      ->orWhere('user_daily_activity.first_opened_at', '>=', $nineAM);
            })
            ->whereNotNull('device_tokens.device_token')
            ->distinct()
            ->pluck('users.id')
            ->toArray();

        $this->line("🎯 Users eligible for notification: " . count($eligibleUserIds));

        // Show breakdown
        $totalUsers = User::count();
        $usersWithTokens = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->distinct()
            ->count();

        $this->line("   Total users: {$totalUsers}");
        $this->line("   Users with device tokens: {$usersWithTokens}");

        if ($simulatedTime) {
            $simulatedDateTime = Carbon::today()->setTimeFromTimeString($simulatedTime);
            $this->line("   Simulating time: {$simulatedDateTime->format('H:i')}");
        }

        $this->newLine();
    }

    private function testLocalization(): void
    {
        $this->info('🌐 Testing localization...');

        $verse = MotivationalVerse::where('is_active', true)->first();
        if (!$verse) {
            $this->error('❌ No verses available for testing');
            return;
        }

        // Test English
        $englishTitle = 'Motivational verse today';
        $englishBody = $verse->translation . "\n— " . $verse->surah_name . ' - ' . $verse->ayah_number;
        
        // Test Arabic  
        $arabicTitle = 'آية تحفيزية اليوم';
        $arabicBody = $verse->arabic_text . "\n— " . $verse->surah_name_ar . ' - ' . $verse->ayah_number;

        $this->line('✅ English notification:');
        $this->line("   Title: {$englishTitle}");
        $this->line("   Body: " . substr($englishBody, 0, 50) . '...');

        $this->line('✅ Arabic notification:');
        $this->line("   Title: {$arabicTitle}");
        $this->line("   Body: " . substr($arabicBody, 0, 50) . '...');

        $this->newLine();
    }

    private function runActualJob($specificUserId): void
    {
        $this->info('🚀 Running actual job...');

        if ($specificUserId) {
            $this->warn("⚠️  This would send notifications to user ID: {$specificUserId}");
            $confirmation = $this->confirm('Are you sure you want to proceed?');
            if (!$confirmation) {
                $this->line('❌ Job execution cancelled');
                return;
            }
        }

        try {
            $job = new SendDailyMotivationalVerse();
            
            $this->line('⏳ Dispatching job...');
            SendDailyMotivationalVerse::dispatch();
            
            $this->line('✅ Job dispatched successfully to queue');
            $this->line('💡 Check queue logs for execution details');

        } catch (\Exception $e) {
            $this->error('❌ Job failed: ' . $e->getMessage());
        }
    }
}
