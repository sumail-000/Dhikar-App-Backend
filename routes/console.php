<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendDailyMotivationalVerse;
use App\Jobs\AssignDailyMotivationalVerses;
use App\Jobs\ProcessTimezoneMotivationalVerses;
use App\Jobs\ProcessTimezonePersonalDhikrReminder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Timezone-Aware Motivational Verse System
 * --------------------------------------
 * 1. We run the process every 5 minutes
 * 2. Each job checks all active timezones
 * 3. For each timezone, it checks if the local time is midnight or 9 AM (within 5 min window)
 * 4. If the condition is met, it processes that timezone's users
 * 
 * This ensures that users receive verses and notifications at their local midnight and 9 AM,
 * not at server time, which provides a consistent experience regardless of user location.
 */

// Every 5 minutes, check for timezones where it's midnight (00:00) for verse assignment
Schedule::job(new ProcessTimezoneMotivationalVerses('00', 'assign'))
    ->everyFiveMinutes()
    ->name('timezone-aware-midnight-verse-assignment')
    ->withoutOverlapping(5) // Prevent overlap but don't block for too long
    ->onOneServer();

// Every 5 minutes, check for timezones where it's 9 AM (09:00) for notifications
Schedule::job(new ProcessTimezoneMotivationalVerses('09', 'notify'))
    ->everyFiveMinutes()
    ->name('timezone-aware-9am-notifications')
    ->withoutOverlapping(5)
    ->onOneServer();

// Every 5 minutes, check for timezones where it's 6 PM (18:00) for personal dhikr/wered reminder
Schedule::job(new ProcessTimezonePersonalDhikrReminder('18'))
    ->everyFiveMinutes()
    ->name('timezone-aware-6pm-personal-reminders')
    ->withoutOverlapping(5)
    ->onOneServer();

/**
 * Legacy scheduler jobs - DISABLED
 * These jobs were running on server time (Europe/Berlin),
 * which did not account for users in different timezones.
 */

// // 🌙 PHASE 1: Midnight (00:00) - Assign new verses to all users for the new day
// Schedule::job(AssignDailyMotivationalVerses::class)
//     ->dailyAt('00:00')
//     ->name('assign-daily-verses-midnight')
//     ->withoutOverlapping(60) // Allow up to 60 minutes to complete
//     ->onOneServer();
// 
// // ☀️ PHASE 2: 9:00 AM - Send notifications with pre-assigned verses
// // This is 9 hours after midnight, ensuring verses are ready
// Schedule::job(SendDailyMotivationalVerse::class)
//     ->dailyAt('09:00')
//     ->name('send-motivational-notifications-9am')
//     ->withoutOverlapping(30)
//     ->onOneServer();
