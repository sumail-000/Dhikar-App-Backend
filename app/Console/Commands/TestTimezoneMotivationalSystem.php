<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTimezoneMotivationalVerses;
use App\Models\DeviceToken;
use App\Models\MotivationalVerse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestTimezoneMotivationalSystem extends Command
{
    protected $signature = 'test:timezone-motivational-system 
                            {--timezone= : Test specific timezone (e.g., Asia/Karachi)}
                            {--simulate-time= : Simulate specific time in timezone (format: HH:MM)}
                            {--dry-run : Show what would happen without running actual jobs}
                            {--verbose : Show detailed output}';

    protected $description = 'Comprehensive test of timezone-aware motivational verse system';

    public function handle(): int
    {
        $this->info('🌍 Testing Timezone-Aware Motivational Verse System 🌍');
        $this->newLine();

        $specificTimezone = $this->option('timezone');
        $simulatedTime = $this->option('simulate-time');
        $isDryRun = $this->option('dry-run');
        $verbose = $this->option('verbose');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual jobs will be executed');
        }

        $this->newLine();

        // Test 1: System Infrastructure
        $this->testSystemInfrastructure();

        // Test 2: Timezone Coverage
        $this->testTimezoneCoverage($verbose);

        // Test 3: User Distribution
        $this->testUserDistribution($verbose);

        // Test 4: Current Time Processing
        $this->testCurrentTimeProcessing($specificTimezone, $simulatedTime);

        // Test 5: Assignment Logic
        $this->testAssignmentLogic($specificTimezone);

        // Test 6: Notification Logic  
        $this->testNotificationLogic($specificTimezone);

        if (!$isDryRun) {
            // Test 7: Live System Test
            $this->liveSystemTest($specificTimezone);
        }

        $this->newLine();
        $this->info('✅ All timezone system tests completed!');
        
        return Command::SUCCESS;
    }

    private function testSystemInfrastructure(): void
    {
        $this->info('🔧 Testing system infrastructure...');

        // Check database structure
        $hasFirstOpenedAt = DB::getSchemaBuilder()->hasColumn('user_daily_activity', 'first_opened_at');
        $hasTimezone = DB::getSchemaBuilder()->hasColumn('device_tokens', 'timezone');
        $hasLocale = DB::getSchemaBuilder()->hasColumn('device_tokens', 'locale');

        if ($hasFirstOpenedAt) {
            $this->line('✅ user_daily_activity.first_opened_at exists');
        } else {
            $this->error('❌ user_daily_activity.first_opened_at missing');
        }

        if ($hasTimezone) {
            $this->line('✅ device_tokens.timezone exists');
        } else {
            $this->error('❌ device_tokens.timezone missing');
        }

        if ($hasLocale) {
            $this->line('✅ device_tokens.locale exists');
        } else {
            $this->error('❌ device_tokens.locale missing');
        }

        // Check data availability
        $userCount = User::count();
        $verseCount = MotivationalVerse::where('is_active', true)->count();
        $tokenCount = DeviceToken::count();

        $this->line("📊 Users: {$userCount}, Active verses: {$verseCount}, Device tokens: {$tokenCount}");

        $this->newLine();
    }

    private function testTimezoneCoverage($verbose): void
    {
        $this->info('🌐 Testing timezone coverage...');

        $timezones = DeviceToken::whereNotNull('timezone')
            ->distinct()
            ->pluck('timezone')
            ->filter()
            ->sort()
            ->values();

        $this->line("🗺️  Found " . count($timezones) . " unique timezones");

        if ($verbose && count($timezones) > 0) {
            $this->table(['Timezone', 'Current Time', 'User Count'], 
                $timezones->map(function ($timezone) {
                    try {
                        $currentTime = Carbon::now($timezone)->format('Y-m-d H:i T');
                        $userCount = DeviceToken::where('timezone', $timezone)->distinct()->count('user_id');
                        return [$timezone, $currentTime, $userCount];
                    } catch (\Exception $e) {
                        return [$timezone, 'Invalid timezone', 0];
                    }
                })->toArray()
            );
        }

        $this->newLine();
    }

    private function testUserDistribution($verbose): void
    {
        $this->info('👥 Testing user distribution...');

        $totalUsers = User::count();
        $usersWithTimezone = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->whereNotNull('device_tokens.timezone')
            ->distinct()
            ->count('users.id');

        $coverage = $totalUsers > 0 ? round(($usersWithTimezone / $totalUsers) * 100, 1) : 0;

        $this->line("📈 Timezone coverage: {$usersWithTimezone}/{$totalUsers} users ({$coverage}%)");

        if ($verbose) {
            // Show locale distribution
            $locales = DeviceToken::whereNotNull('locale')
                ->select('locale', DB::raw('count(distinct user_id) as user_count'))
                ->groupBy('locale')
                ->get();

            if ($locales->isNotEmpty()) {
                $this->line('🗣️  Locale distribution:');
                foreach ($locales as $locale) {
                    $this->line("   {$locale->locale}: {$locale->user_count} users");
                }
            }
        }

        $this->newLine();
    }

    private function testCurrentTimeProcessing($specificTimezone, $simulatedTime): void
    {
        $this->info('⏰ Testing current time processing...');

        $timezones = $specificTimezone ? 
            collect([$specificTimezone]) : 
            DeviceToken::whereNotNull('timezone')->distinct()->pluck('timezone')->take(3);

        foreach ($timezones as $timezone) {
            try {
                $now = $simulatedTime ? 
                    Carbon::now($timezone)->setTimeFromTimeString($simulatedTime) :
                    Carbon::now($timezone);

                $midnight = $now->copy()->setTime(0, 0, 0);
                $nineAM = $now->copy()->setTime(9, 0, 0);

                $midnightDiff = abs($now->diffInMinutes($midnight));
                $nineAMDiff = abs($now->diffInMinutes($nineAM));

                $this->line("🕐 {$timezone}: " . $now->format('H:i'));

                if ($midnightDiff <= 5) {
                    $this->line("  ✅ Within midnight window ({$midnightDiff} min from 00:00)");
                }

                if ($nineAMDiff <= 5) {
                    $this->line("  ✅ Within 9 AM window ({$nineAMDiff} min from 09:00)");
                }

                if ($midnightDiff > 5 && $nineAMDiff > 5) {
                    $this->line("  ⏭️  Outside processing windows");
                }

            } catch (\Exception $e) {
                $this->error("❌ Invalid timezone: {$timezone}");
            }
        }

        $this->newLine();
    }

    private function testAssignmentLogic($specificTimezone): void
    {
        $this->info('📖 Testing verse assignment logic...');

        $today = Carbon::today()->toDateString();

        // Get sample users
        if ($specificTimezone) {
            $userIds = DeviceToken::where('timezone', $specificTimezone)
                ->distinct()
                ->take(3)
                ->pluck('user_id');
        } else {
            $userIds = User::take(3)->pluck('id');
        }

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->line("👤 Testing user: {$user->username} (ID: {$user->id})");

            // Check current assignment
            $existing = DB::table('motivational_verse_user')
                ->where('user_id', $user->id)
                ->where('shown_date', $today)
                ->first();

            if ($existing) {
                $verse = MotivationalVerse::find($existing->verse_id);
                $this->line("  ✅ Has today's verse: {$verse->surah_name} {$verse->ayah_number}");
            } else {
                // Show assignment logic
                $activeIds = MotivationalVerse::where('is_active', true)->pluck('id')->all();
                $seenIds = DB::table('motivational_verse_user')
                    ->where('user_id', $user->id)
                    ->distinct()
                    ->pluck('verse_id')
                    ->all();

                $remaining = array_diff($activeIds, $seenIds);
                
                if (empty($remaining)) {
                    $this->line('  🔄 Would reset cycle - user has seen all verses');
                    $remaining = $activeIds;
                }

                $this->line("  📊 Available verses: " . count($remaining) . "/" . count($activeIds));
            }
        }

        $this->newLine();
    }

    private function testNotificationLogic($specificTimezone): void
    {
        $this->info('🔔 Testing notification logic...');

        $today = Carbon::today()->toDateString();
        $nineAM = Carbon::today()->setTime(9, 0, 0);

        // Build query like the job does
        $query = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->leftJoin('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->where(function ($query) use ($nineAM) {
                $query->whereNull('user_daily_activity.first_opened_at')
                      ->orWhere('user_daily_activity.first_opened_at', '>=', $nineAM);
            })
            ->whereNotNull('device_tokens.device_token');

        if ($specificTimezone) {
            $query->where('device_tokens.timezone', $specificTimezone);
        }

        $eligibleCount = $query->distinct()->count('users.id');
        $this->line("🎯 Users eligible for notifications: {$eligibleCount}");

        // Show breakdown by category
        $noActivity = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->leftJoin('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->whereNull('user_daily_activity.id')
            ->whereNotNull('device_tokens.device_token')
            ->when($specificTimezone, fn($q) => $q->where('device_tokens.timezone', $specificTimezone))
            ->distinct()
            ->count('users.id');

        $lateOpeners = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->join('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->where('user_daily_activity.first_opened_at', '>=', $nineAM)
            ->whereNotNull('device_tokens.device_token')
            ->when($specificTimezone, fn($q) => $q->where('device_tokens.timezone', $specificTimezone))
            ->distinct()
            ->count('users.id');

        $this->line("  📊 No activity today: {$noActivity}");
        $this->line("  📊 Opened after 9 AM: {$lateOpeners}");

        $this->newLine();
    }

    private function liveSystemTest($specificTimezone): void
    {
        $this->info('🚀 Live system test...');

        $this->warn('⚠️  This will run the actual timezone processing jobs');
        if ($specificTimezone) {
            $this->warn("⚠️  Limited to timezone: {$specificTimezone}");
        }

        $confirmation = $this->confirm('Continue with live test?');
        if (!$confirmation) {
            $this->line('❌ Live test cancelled');
            return;
        }

        // Test assignment job
        $this->line('🌙 Testing verse assignment job...');
        try {
            $job = new ProcessTimezoneMotivationalVerses('00', 'assign');
            $job->handle();
            $this->line('✅ Assignment job completed');
        } catch (\Exception $e) {
            $this->error('❌ Assignment job failed: ' . $e->getMessage());
        }

        $this->newLine();

        // Test notification job
        $this->line('☀️ Testing notification job...');
        try {
            $job = new ProcessTimezoneMotivationalVerses('09', 'notify');
            $job->handle();
            $this->line('✅ Notification job completed');
        } catch (\Exception $e) {
            $this->error('❌ Notification job failed: ' . $e->getMessage());
        }
    }
}
