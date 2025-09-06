<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyMotivationalVerse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TriggerDailyMotivationalVerse extends Command
{
    protected $signature = 'motivational-verse:send 
                            {--queue : Dispatch to queue instead of running immediately}
                            {--delay= : Delay in seconds before execution}';

    protected $description = 'Manually trigger the daily motivational verse notifications';

    public function handle(): int
    {
        $this->info('🌟 Triggering Daily Motivational Verse Notifications 🌟');
        
        $useQueue = $this->option('queue');
        $delay = (int) $this->option('delay', 0);

        if ($useQueue) {
            if ($delay > 0) {
                SendDailyMotivationalVerse::dispatch()->delay(now()->addSeconds($delay));
                $this->info("✅ Job dispatched to queue with {$delay} seconds delay");
            } else {
                SendDailyMotivationalVerse::dispatch();
                $this->info('✅ Job dispatched to queue');
            }
            
            $this->line('💡 Monitor queue processing with: php artisan queue:work');
        } else {
            $this->info('🔄 Running job immediately...');
            
            $job = new SendDailyMotivationalVerse();
            $job->handle();
            
            $this->info('✅ Job completed');
        }

        return Command::SUCCESS;
    }
}
