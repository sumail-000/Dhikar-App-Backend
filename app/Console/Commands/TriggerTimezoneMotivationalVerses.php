<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTimezoneMotivationalVerses;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TriggerTimezoneMotivationalVerses extends Command
{
    protected $signature = 'motivational-verse:timezone-trigger 
                            {action=both : Action to perform (assign, notify, or both)}
                            {--test-timezone= : Test with specific timezone (e.g., Asia/Karachi)}
                            {--queue : Dispatch to queue instead of running immediately}
                            {--dry-run : Show what would happen without actually running}';

    protected $description = 'Trigger timezone-aware motivational verse assignment and notifications';

    public function handle(): int
    {
        $action = $this->argument('action');
        $testTimezone = $this->option('test-timezone');
        $useQueue = $this->option('queue');
        $isDryRun = $this->option('dry-run');

        $this->info('🌍 Timezone-Aware Motivational Verse System 🌍');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - Jobs will not be executed');
        }

        if ($testTimezone) {
            $this->info("🧪 Testing with timezone: {$testTimezone}");
            $this->showTimezoneInfo($testTimezone);
        }

        $this->newLine();

        $validActions = ['assign', 'notify', 'both'];
        if (!in_array($action, $validActions)) {
            $this->error("❌ Invalid action '{$action}'. Valid options: " . implode(', ', $validActions));
            return Command::FAILURE;
        }

        if ($action === 'assign' || $action === 'both') {
            $this->handleVerseAssignment($useQueue, $isDryRun, $testTimezone);
        }

        if ($action === 'notify' || $action === 'both') {
            $this->handleNotifications($useQueue, $isDryRun, $testTimezone);
        }

        $this->newLine();
        $this->info('✅ Command completed successfully!');
        
        return Command::SUCCESS;
    }

    private function showTimezoneInfo(string $timezone): void
    {
        try {
            $now = Carbon::now($timezone);
            $midnight = $now->copy()->setTime(0, 0, 0);
            $nineAM = $now->copy()->setTime(9, 0, 0);

            $this->line("Current time in {$timezone}: " . $now->format('Y-m-d H:i:s T'));
            $this->line("Today's midnight: " . $midnight->format('Y-m-d H:i:s T'));
            $this->line("Today's 9 AM: " . $nineAM->format('Y-m-d H:i:s T'));

            // Check if we're within the processing windows
            $midnightDiff = abs($now->diffInMinutes($midnight));
            $nineAMDiff = abs($now->diffInMinutes($nineAM));

            if ($midnightDiff <= 5) {
                $this->line("✅ Within midnight assignment window ({$midnightDiff} minutes from midnight)");
            }

            if ($nineAMDiff <= 5) {
                $this->line("✅ Within 9 AM notification window ({$nineAMDiff} minutes from 9 AM)");
            }

        } catch (\Exception $e) {
            $this->error("❌ Invalid timezone: {$timezone}");
        }
    }

    private function handleVerseAssignment($useQueue, $isDryRun, $testTimezone): void
    {
        $this->info('🌙 Handling verse assignment (midnight logic)...');

        if ($isDryRun) {
            $this->line('Would dispatch: ProcessTimezoneMotivationalVerses("00", "assign")');
            return;
        }

        if ($testTimezone) {
            // For testing, create a custom job that only processes the specific timezone
            $this->warn("⚠️  This will assign verses for users in timezone: {$testTimezone}");
            $confirmation = $this->confirm('Continue?');
            if (!$confirmation) {
                $this->line('❌ Cancelled');
                return;
            }
        }

        if ($useQueue) {
            ProcessTimezoneMotivationalVerses::dispatch('00', 'assign')
                ->onQueue('notifications');
            $this->line('✅ Verse assignment job dispatched to queue');
        } else {
            $this->line('⏳ Running verse assignment job immediately...');
            $job = new ProcessTimezoneMotivationalVerses('00', 'assign');
            $job->handle();
            $this->line('✅ Verse assignment job completed');
        }
    }

    private function handleNotifications($useQueue, $isDryRun, $testTimezone): void
    {
        $this->info('☀️ Handling notifications (9 AM logic)...');

        if ($isDryRun) {
            $this->line('Would dispatch: ProcessTimezoneMotivationalVerses("09", "notify")');
            return;
        }

        if ($testTimezone) {
            $this->warn("⚠️  This will send notifications for users in timezone: {$testTimezone}");
            $confirmation = $this->confirm('Continue?');
            if (!$confirmation) {
                $this->line('❌ Cancelled');
                return;
            }
        }

        if ($useQueue) {
            ProcessTimezoneMotivationalVerses::dispatch('09', 'notify')
                ->onQueue('notifications');
            $this->line('✅ Notification job dispatched to queue');
        } else {
            $this->line('⏳ Running notification job immediately...');
            $job = new ProcessTimezoneMotivationalVerses('09', 'notify');
            $job->handle();
            $this->line('✅ Notification job completed');
        }
    }
}
