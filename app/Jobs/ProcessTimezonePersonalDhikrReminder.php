<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTimezonePersonalDhikrReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $targetHour; // e.g. '18' for 6 PM local time

    public function __construct(string $targetHour = '18')
    {
        $this->targetHour = $targetHour;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::info("⏰ Starting timezone-aware personal dhikr/wered reminder for {$this->targetHour}:00");

        // Get all unique timezones from device_tokens
        $timezones = DeviceToken::whereNotNull('timezone')
            ->distinct()
            ->pluck('timezone')
            ->filter()
            ->toArray();

        if (empty($timezones)) {
            $timezones = ['UTC'];
        }

        $processedUsers = 0;
        foreach ($timezones as $timezone) {
            $processedUsers += $this->processTimezone($timezone);
        }

        Log::info("✅ Completed timezone-aware personal dhikr/wered reminders: {$processedUsers} users processed across " . count($timezones) . " timezones");
    }

    private function processTimezone(string $timezone): int
    {
        try {
            $now = Carbon::now($timezone);
            $targetTime = $now->copy()->setTime((int) $this->targetHour, 0, 0);

            // Only run within a 5-minute window around the target time
            if (abs($now->diffInMinutes($targetTime)) > 5) {
                Log::debug("Skipping {$timezone} - not at {$this->targetHour}:00 (current: {$now->format('H:i')})");
                return 0;
            }

            Log::info("📍 Processing {$timezone} at {$now->format('Y-m-d H:i T')}");

            $today = $now->toDateString();

            // Determine users in this timezone (based on their device tokens)
            $userIds = DeviceToken::where('timezone', $timezone)
                ->whereNotNull('device_token')
                ->distinct()
                ->pluck('user_id')
                ->filter()
                ->values()
                ->toArray();

            if (empty($userIds)) {
                return 0;
            }

            $eligibleUsers = [];

            foreach ($userIds as $uid) {
                // 1) Personal dhikr marker: user_daily_activity.reading=true for today
                $hasPersonalDhikr = DB::table('user_daily_activity')
                    ->where('user_id', $uid)
                    ->where('activity_date', $today)
                    ->where('reading', true)
                    ->exists();

                if ($hasPersonalDhikr) {
                    continue; // already did personal dhikr/app reading mark today
                }

                // 2) Personal wered/Qur'an: any personal khitma daily progress today
                $hasPersonalWered = DB::table('personal_khitma_daily_progress')
                    ->join('personal_khitma_progress', 'personal_khitma_daily_progress.khitma_id', '=', 'personal_khitma_progress.id')
                    ->where('personal_khitma_progress.user_id', $uid)
                    ->where('personal_khitma_daily_progress.reading_date', $today)
                    ->exists();

                if ($hasPersonalWered) {
                    continue; // already read Qur'an today in personal khitma
                }

                // If neither is true, user is eligible for reminder
                $eligibleUsers[] = $uid;
            }

            if (empty($eligibleUsers)) {
                Log::info("No eligible users for {$timezone} at {$this->targetHour}:00");
                return 0;
            }

            $count = 0;
            $users = User::whereIn('id', $eligibleUsers)->get();

            foreach ($users as $user) {
                $count += $this->sendReminderToUser($user);
            }

            Log::info("🔔 Sent personal dhikr/wered reminders to {$count} users in {$timezone}");
            return $count;
        } catch (\Throwable $e) {
            Log::error("Failed processing timezone {$timezone}: " . $e->getMessage());
            return 0;
        }
    }

    private function sendReminderToUser(User $user): int
    {
        try {
            // Collect tokens for the user
            $deviceTokens = DeviceToken::where('user_id', $user->id)
                ->whereNotNull('device_token')
                ->get();

            if ($deviceTokens->isEmpty()) {
                Log::debug("No device tokens for user {$user->id}");
                return 0;
            }

            // Use the first token's locale for basic localization
            $locale = $deviceTokens->first()->locale ?? 'en';
            $isArabic = in_array($locale, ['ar', 'ar_SA', 'ar_EG', 'ar_AE', 'ar_MA', 'ar_DZ', 'ar_TN', 'ar_LY', 'ar_SD', 'ar_SY', 'ar_LB', 'ar_JO', 'ar_PS', 'ar_IQ', 'ar_KW', 'ar_BH', 'ar_QA', 'ar_OM', 'ar_YE']);

            $firstName = $this->getFirstName($user->username ?? 'Friend');

            if ($isArabic) {
                $title = 'تذكير اليوم';
                $body = "السلام عليكم {$firstName}! لا تنسَ وردك اليوم من القرآن والذِّكر قبل الساعة 6 مساءً.";
            } else {
                $title = 'Today’s reminder';
                $body = "Assalamu Alaikum {$firstName}! Don’t forget your daily wered (Qur’an) and dhikr before 6 PM.";
            }

            // Create in-app notification
            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'individual_reminder',
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'personal_dhikr_wered_reminder',
                    'navigate_to' => 'home'
                ],
            ]);

            // Dispatch push to all devices
            $tokens = $deviceTokens->pluck('device_token')->values()->all();
            if (!empty($tokens)) {
                \App\Jobs\SendPushNotification::dispatch(
                    $tokens,
                    $title,
                    $body,
                    ['type' => 'individual_reminder', 'navigate_to' => 'home']
                )->delay(now()->addSeconds(rand(1, 10)));
            }

            return 1;
        } catch (\Throwable $e) {
            Log::error("Failed to send personal reminder to user {$user->id}: " . $e->getMessage());
            return 0;
        }
    }

    private function getFirstName(string $fullName): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return 'صديقي'; // fallback in Arabic
        }
        $parts = preg_split('/\s+/', $fullName);
        return $parts[0] ?? $fullName;
    }
}

