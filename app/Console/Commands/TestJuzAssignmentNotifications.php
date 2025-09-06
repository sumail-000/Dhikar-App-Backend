<?php

namespace App\Console\Commands;

use App\Jobs\SendJuzAssignmentNotification;
use App\Models\DeviceToken;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\KhitmaAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestJuzAssignmentNotifications extends Command
{
    protected $signature = 'test:juz-assignment-notifications 
                            {--group-id= : Test with specific group ID}
                            {--user-id= : Test with specific user ID}
                            {--assignment-type= : Test specific type (auto or manual)}
                            {--dry-run : Show what would happen without sending notifications}
                            {--detailed : Show detailed output}';

    protected $description = 'Test the Juz assignment notification system with Islamic greetings';

    public function handle(): int
    {
        $this->info('🕌 Testing Juz Assignment Notification System 🕌');
        $this->newLine();

        $specificGroupId = $this->option('group-id');
        $specificUserId = $this->option('user-id');
        $assignmentType = $this->option('assignment-type');
        $isDryRun = $this->option('dry-run');
        $verbose = $this->option('detailed');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual notifications will be sent');
        }

        $this->newLine();

        // Test 1: System Infrastructure
        $this->testSystemInfrastructure();

        // Test 2: Group and User Data
        $this->testGroupAndUserData($specificGroupId, $specificUserId, $verbose);

        // Test 3: Localization Support
        $this->testLocalizationSupport($verbose);

        // Test 4: Auto Assignment Simulation
        if (!$assignmentType || $assignmentType === 'auto') {
            $this->testAutoAssignmentNotifications($specificGroupId, $specificUserId, $isDryRun);
        }

        // Test 5: Manual Assignment Simulation  
        if (!$assignmentType || $assignmentType === 'manual') {
            $this->testManualAssignmentNotifications($specificGroupId, $specificUserId, $isDryRun);
        }

        if (!$isDryRun) {
            // Test 6: Live Notification Test
            $this->liveNotificationTest($specificGroupId, $specificUserId, $assignmentType);
        }

        $this->newLine();
        $this->info('✅ All Juz assignment notification tests completed!');
        
        return Command::SUCCESS;
    }

    private function testSystemInfrastructure(): void
    {
        $this->info('🔧 Testing system infrastructure...');

        // Check key tables exist
        $tables = ['groups', 'group_members', 'khitma_assignments', 'device_tokens', 'notifications'];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $this->line("✅ Table '{$table}' exists");
            } else {
                $this->error("❌ Table '{$table}' missing");
            }
        }

        // Check essential columns
        $hasLocale = DB::getSchemaBuilder()->hasColumn('device_tokens', 'locale');
        $hasTimezone = DB::getSchemaBuilder()->hasColumn('device_tokens', 'timezone');

        if ($hasLocale) {
            $this->line('✅ device_tokens.locale exists (for localization)');
        } else {
            $this->error('❌ device_tokens.locale missing');
        }

        if ($hasTimezone) {
            $this->line('✅ device_tokens.timezone exists');
        } else {
            $this->error('❌ device_tokens.timezone missing');
        }

        // Check data counts
        $groupCount = Group::where('type', 'khitma')->count();
        $userCount = User::count();
        $deviceTokenCount = DeviceToken::count();

        $this->line("📊 Khitma groups: {$groupCount}, Users: {$userCount}, Device tokens: {$deviceTokenCount}");

        $this->newLine();
    }

    private function testGroupAndUserData($specificGroupId, $specificUserId, $verbose): void
    {
        $this->info('👥 Testing group and user data...');

        // Get test groups
        if ($specificGroupId) {
            $groups = Group::where('type', 'khitma')->where('id', $specificGroupId)->get();
            if ($groups->isEmpty()) {
                $this->error("❌ Group ID {$specificGroupId} not found or not a Khitma group");
                return;
            }
        } else {
            $groups = Group::where('type', 'khitma')->with('members')->take(3)->get();
        }

        if ($groups->isEmpty()) {
            $this->error('❌ No Khitma groups found for testing');
            return;
        }

        foreach ($groups as $group) {
            $memberCount = $group->members()->count();
            $assignmentCount = KhitmaAssignment::where('group_id', $group->id)->count();
            $this->line("🏘️  Group: {$group->name} (ID: {$group->id}) - {$memberCount} members, {$assignmentCount} assignments");

            if ($verbose) {
                $members = $group->members()->with('user')->get();
                foreach ($members->take(3) as $member) {
                    $user = $member->user;
                    $deviceTokens = DeviceToken::where('user_id', $user->id)->count();
                    $this->line("   👤 {$user->username} (ID: {$user->id}) - {$deviceTokens} device tokens");
                }
            }
        }

        // Test specific user if provided
        if ($specificUserId) {
            $user = User::find($specificUserId);
            if ($user) {
                $deviceTokens = DeviceToken::where('user_id', $user->id)->get();
                $groupMemberships = GroupMember::where('user_id', $user->id)->count();
                $this->line("👤 Test User: {$user->username} - {$deviceTokens->count()} tokens, {$groupMemberships} group memberships");
                
                if ($verbose && $deviceTokens->isNotEmpty()) {
                    foreach ($deviceTokens->take(2) as $token) {
                        $locale = $token->locale ?? 'not set';
                        $timezone = $token->timezone ?? 'not set';
                        $this->line("   📱 Token locale: {$locale}, timezone: {$timezone}");
                    }
                }
            } else {
                $this->error("❌ User ID {$specificUserId} not found");
            }
        }

        $this->newLine();
    }

    private function testLocalizationSupport($verbose): void
    {
        $this->info('🌐 Testing localization support...');

        // Check locale distribution
        $locales = DeviceToken::whereNotNull('locale')
            ->select('locale', DB::raw('count(*) as count'))
            ->groupBy('locale')
            ->get();

        $this->line("🗣️  Found " . $locales->count() . " different locales");

        if ($verbose && $locales->isNotEmpty()) {
            foreach ($locales as $locale) {
                $this->line("   {$locale->locale}: {$locale->count} tokens");
            }
        }

        // Test Islamic greeting generation
        $testNames = ['Ahmed', 'Fatima', 'Muhammad Ali', 'Aisha Bint Omar', 'عبد الله', 'User123'];
        
        $this->line('📝 Testing Islamic name handling:');
        foreach ($testNames as $name) {
            $firstName = $this->extractFirstName($name);
            $this->line("   '{$name}' → '{$firstName}'");
        }

        $this->newLine();
    }

    private function testAutoAssignmentNotifications($specificGroupId, $specificUserId, $isDryRun): void
    {
        $this->info('🔄 Testing auto assignment notifications...');

        // Get a suitable test group
        $group = $this->getTestGroup($specificGroupId);
        if (!$group) {
            $this->error('❌ No suitable group found for auto assignment testing');
            return;
        }

        $members = $group->members()->with('user')->get();
        if ($members->isEmpty()) {
            $this->error("❌ Group {$group->name} has no members");
            return;
        }

        $memberIds = $members->pluck('user_id')->toArray();
        if ($specificUserId && !in_array($specificUserId, $memberIds)) {
            $this->warn("⚠️  User ID {$specificUserId} is not a member of group {$group->name}");
        }

        $this->line("📢 Auto assignment test for group: {$group->name}");
        $this->line("   Target members: " . implode(', ', $memberIds));

        if ($isDryRun) {
            $this->line('🔍 Would dispatch: SendJuzAssignmentNotification(group_id=' . $group->id . ', type=auto, members=' . count($memberIds) . ')');
            
            // Show what notification content would be generated
            foreach ($members->take(2) as $member) {
                $user = $member->user;
                $sampleContent = $this->generateSampleNotificationContent($user, $group, 'auto');
                $this->line("   Sample for {$user->username}: {$sampleContent['title']}");
            }
        }

        $this->newLine();
    }

    private function testManualAssignmentNotifications($specificGroupId, $specificUserId, $isDryRun): void
    {
        $this->info('✍️ Testing manual assignment notifications...');

        // Get a suitable test group
        $group = $this->getTestGroup($specificGroupId);
        if (!$group) {
            $this->error('❌ No suitable group found for manual assignment testing');
            return;
        }

        $members = $group->members()->with('user')->get();
        if ($members->isEmpty()) {
            $this->error("❌ Group {$group->name} has no members");
            return;
        }

        // Create sample manual assignment data
        $sampleAssignments = [];
        $memberIds = [];
        foreach ($members->take(2) as $member) {
            $user = $member->user;
            $memberIds[] = $user->id;
            $sampleAssignments[] = [
                'user_id' => $user->id,
                'juz_numbers' => [rand(1, 15), rand(16, 30)] // Random Juz assignments
            ];
        }

        if ($specificUserId) {
            $user = User::find($specificUserId);
            if ($user && $group->members()->where('user_id', $user->id)->exists()) {
                $memberIds = [$user->id];
                $sampleAssignments = [
                    [
                        'user_id' => $user->id,
                        'juz_numbers' => [1, 2, 3] // Sample assignment
                    ]
                ];
            }
        }

        $this->line("📝 Manual assignment test for group: {$group->name}");
        $this->line("   Target members: " . implode(', ', $memberIds));
        
        foreach ($sampleAssignments as $assignment) {
            $user = User::find($assignment['user_id']);
            $juzList = implode(', ', $assignment['juz_numbers']);
            $this->line("   {$user->username}: Juz {$juzList}");
        }

        if ($isDryRun) {
            $this->line('🔍 Would dispatch: SendJuzAssignmentNotification(group_id=' . $group->id . ', type=manual, members=' . count($memberIds) . ', assignments=provided)');
            
            // Show what notification content would be generated
            foreach ($sampleAssignments as $assignment) {
                $user = User::find($assignment['user_id']);
                $sampleContent = $this->generateSampleNotificationContent($user, $group, 'manual', $assignment['juz_numbers']);
                $this->line("   Sample for {$user->username}: {$sampleContent['title']}");
            }
        }

        $this->newLine();
    }

    private function liveNotificationTest($specificGroupId, $specificUserId, $assignmentType): void
    {
        $this->info('🚀 Live notification test...');

        $group = $this->getTestGroup($specificGroupId);
        if (!$group) {
            $this->error('❌ No suitable group found for live testing');
            return;
        }

        $this->warn('⚠️  This will send actual notifications');
        if ($specificUserId) {
            $user = User::find($specificUserId);
            $this->warn("⚠️  Limited to user: {$user->username} (ID: {$specificUserId})");
        }

        $confirmation = $this->confirm('Continue with live notification test?');
        if (!$confirmation) {
            $this->line('❌ Live test cancelled');
            return;
        }

        $members = $group->members()->with('user')->get();
        $memberIds = $specificUserId ? [$specificUserId] : $members->pluck('user_id')->take(2)->toArray();

        if (!$assignmentType || $assignmentType === 'auto') {
            $this->line('🔄 Sending auto assignment test notification...');
            try {
                SendJuzAssignmentNotification::dispatch(
                    $group->id,
                    'auto',
                    $memberIds
                );
                $this->line('✅ Auto assignment notification dispatched');
            } catch (\Exception $e) {
                $this->error('❌ Auto assignment notification failed: ' . $e->getMessage());
            }
        }

        if (!$assignmentType || $assignmentType === 'manual') {
            $this->line('✍️ Sending manual assignment test notification...');
            $testAssignments = [];
            foreach ($memberIds as $userId) {
                $testAssignments[] = [
                    'user_id' => $userId,
                    'juz_numbers' => [rand(1, 30)] // Single random Juz for test
                ];
            }

            try {
                SendJuzAssignmentNotification::dispatch(
                    $group->id,
                    'manual',
                    $memberIds,
                    $testAssignments
                );
                $this->line('✅ Manual assignment notification dispatched');
            } catch (\Exception $e) {
                $this->error('❌ Manual assignment notification failed: ' . $e->getMessage());
            }
        }

        $this->line('💡 Check queue logs and user devices for notification delivery');
    }

    private function getTestGroup($specificGroupId): ?Group
    {
        if ($specificGroupId) {
            return Group::where('type', 'khitma')->where('id', $specificGroupId)->first();
        }

        // Get a group with members and device tokens
        return Group::where('type', 'khitma')
            ->whereHas('members.user.deviceTokens')
            ->with(['members.user'])
            ->first();
    }

    private function generateSampleNotificationContent($user, $group, $type, $juzNumbers = null): array
    {
        $firstName = $this->extractFirstName($user->username ?? 'أخي الكريم');
        
        if ($type === 'auto') {
            $title = 'Juz Assigned - ' . $group->name;
            $preview = "Assalamu Alaikum {$firstName}, admin has automatically assigned...";
        } else {
            $title = 'Juz Assigned to You - ' . $group->name;
            $juzList = $juzNumbers ? implode(', ', $juzNumbers) : '1';
            $preview = "Assalamu Alaikum {$firstName}, your assigned Juz: {$juzList}...";
        }

        return [
            'title' => $title,
            'preview' => $preview
        ];
    }

    private function extractFirstName(string $fullName): string
    {
        if (empty(trim($fullName))) {
            return 'أخي الكريم';
        }

        $parts = explode(' ', trim($fullName));
        $firstName = $parts[0];

        if (strlen($firstName) < 2) {
            return 'أخي الكريم';
        }

        return $firstName;
    }
}
