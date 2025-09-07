<?php

namespace App\Jobs;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\DeviceToken;
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

class ProcessTimezoneMotivationalVerses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $targetHour; // '00' for midnight, '09' for 9 AM
    private string $action; // 'assign' or 'notify'

    public function __construct(string $targetHour, string $action)
    {
        $this->targetHour = $targetHour;
        $this->action = $action;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::info("🌍 Starting timezone-aware motivational verse {$this->action} for {$this->targetHour}:00");

        // Get all unique timezones from device_tokens
        $timezones = DeviceToken::whereNotNull('timezone')
            ->distinct()
            ->pluck('timezone')
            ->filter()
            ->toArray();

        if (empty($timezones)) {
            // Fallback: process users without timezone (assume UTC)
            $timezones = ['UTC'];
        }

        Log::info("Found timezones: " . implode(', ', $timezones));

        $processedUsers = 0;
        foreach ($timezones as $timezone) {
            $processedUsers += $this->processTimezone($timezone);
        }

        Log::info("✅ Completed timezone-aware {$this->action}: {$processedUsers} users processed across " . count($timezones) . " timezones");
    }

    private function processTimezone(string $timezone): int
    {
        try {
            $currentTime = Carbon::now($timezone);
            $targetTime = $currentTime->copy()->setTime((int)$this->targetHour, 0, 0);
            
            // Check if it's the right time (within 5 minutes window)
            $timeDiff = abs($currentTime->diffInMinutes($targetTime));
            
            if ($timeDiff > 5) {
                Log::debug("Skipping {$timezone} - not the right time (current: {$currentTime->format('H:i')}, target: {$this->targetHour}:00)");
                return 0;
            }

            Log::info("📍 Processing {$timezone} at {$currentTime->format('Y-m-d H:i T')}");

            if ($this->action === 'assign') {
                return $this->assignVersesForTimezone($timezone, $currentTime);
            } else {
                return $this->sendNotificationsForTimezone($timezone, $currentTime);
            }

        } catch (\Exception $e) {
            Log::error("Failed to process timezone {$timezone}: " . $e->getMessage());
            return 0;
        }
    }

    private function assignVersesForTimezone(string $timezone, Carbon $currentTime): int
    {
        // Get users in this timezone (via their device tokens)
        $userIds = DeviceToken::where('timezone', $timezone)
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            return 0;
        }

        $users = User::whereIn('id', $userIds)->get();
        $processedCount = 0;

        foreach ($users as $user) {
            try {
                $this->assignTodaysVerseToUser($user, $currentTime->toDateString());
                $processedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to assign verse to user {$user->id} in {$timezone}: " . $e->getMessage());
            }
        }

        Log::info("🌙 Assigned verses for {$processedCount} users in {$timezone}");
        return $processedCount;
    }

    private function sendNotificationsForTimezone(string $timezone, Carbon $currentTime): int
    {
        // Get eligible users for notifications in this timezone
        $eligibleUsers = $this->getEligibleUsersForTimezone($timezone, $currentTime);

        $processedCount = 0;
        foreach ($eligibleUsers as $user) {
            try {
                $this->sendMotivationalVerseToUser($user, $currentTime->toDateString());
                $processedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to send notification to user {$user['id']} in {$timezone}: " . $e->getMessage());
            }
        }

        Log::info("☀️ Sent notifications to {$processedCount} users in {$timezone}");
        return $processedCount;
    }

    private function getEligibleUsersForTimezone(string $timezone, Carbon $currentTime): array
    {
        $today = $currentTime->toDateString();
        $nineAM = $currentTime->copy()->setTime(9, 0, 0);

        // Get users in this timezone who haven't opened app before 9 AM today
        $eligibleUserIds = DB::table('users')
            ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->leftJoin('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->where('device_tokens.timezone', $timezone)
            ->where(function ($query) use ($nineAM) {
                $query->whereNull('user_daily_activity.first_opened_at')
                      ->orWhere('user_daily_activity.first_opened_at', '>=', $nineAM);
            })
            ->whereNotNull('device_tokens.device_token')
            ->distinct()
            ->pluck('users.id')
            ->toArray();

        return User::whereIn('id', $eligibleUserIds)->get()->toArray();
    }

    private function assignTodaysVerseToUser($user, string $today): void
    {
        // Check if already assigned today
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user->id)
            ->where('shown_date', $today)
            ->first();

        if ($existing) {
            return; // Already assigned
        }

        // Get or assign a new verse
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
            // Reset cycle
            DB::table('motivational_verse_user')->where('user_id', $user->id)->delete();
            $remaining = $activeIds;
            Log::info("Reset verse cycle for user {$user->id}");
        }

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

    private function sendMotivationalVerseToUser($user, string $today): void
    {
        // Get today's verse for this user
        $verse = $this->getTodaysVerseForUser($user, $today);
        if (!$verse) {
            Log::warning('No verse found for user: ' . $user['id']);
            return;
        }

        // Respect user preference for motivational notifications
        if (!\App\Models\UserNotificationPreference::allowsMotivation((int)$user['id'])) {
            return;
        }

        // Get user's device tokens
        $deviceTokens = DeviceToken::where('user_id', $user['id'])
            ->whereNotNull('device_token')
            ->get();

        if ($deviceTokens->isEmpty()) {
            Log::warning('No device tokens for user: ' . $user['id']);
            return;
        }

        // Create localized notification content
        $notificationData = $this->createNotificationContent($verse, $deviceTokens->first());

        // Create in-app notification record
        $appNotification = AppNotification::create([
            'user_id' => $user['id'],
            'type' => 'motivational',
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'data' => json_encode([
                'verse_id' => $verse['id'],
                'surah_name' => $verse['surah_name'],
                'surah_name_ar' => $verse['surah_name_ar'],
                'surah_number' => $verse['surah_number'],
                'ayah_number' => $verse['ayah_number'],
                'arabic_text' => $verse['arabic_text'],
                'translation' => $verse['translation'],
            ]),
            'is_read' => false,
        ]);

        // Send push notifications to all user's devices
        foreach ($deviceTokens as $deviceToken) {
            SendPushNotification::dispatch(
                $deviceToken->device_token,
                $notificationData['title'],
                $notificationData['body'],
                $notificationData['data'],
                $user['id'],
                $appNotification->id
            )->delay(now()->addSeconds(rand(1, 30)));
        }

        Log::info('Sent motivational verse notification to user: ' . $user['id']);
    }

    private function getTodaysVerseForUser($user, string $today): ?array
    {
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user['id'])
            ->where('shown_date', $today)
            ->first();

        if ($existing) {
            $verse = MotivationalVerse::where('is_active', true)->find($existing->verse_id);
            return $verse ? $verse->toArray() : null;
        }

        Log::warning("No pre-assigned verse found for user {$user['id']} on {$today}");
        return null;
    }

    private function createNotificationContent($verse, $deviceToken): array
    {
        $locale = $deviceToken->locale ?? 'en';
        $isArabic = in_array($locale, ['ar', 'ar_SA', 'ar_EG', 'ar_AE']);

        $title = $isArabic ? 'آية تحفيزية اليوم' : 'Motivational verse today';

        if ($isArabic) {
            $body = $verse['arabic_text'];
            $surahInfo = $verse['surah_name_ar'] . ' - ' . $verse['ayah_number'];
        } else {
            $body = $verse['translation'];
            $surahInfo = $verse['surah_name'] . ' - ' . $verse['ayah_number'];
        }

        $body = $body . "\n— " . $surahInfo;

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'motivational_verse',
                'verse_id' => $verse['id'],
                'navigate_to' => 'home'
            ]
        ];
    }
}
