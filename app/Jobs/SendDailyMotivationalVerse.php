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

class SendDailyMotivationalVerse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        // Use notifications queue to match existing worker
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::info('☀️ Starting 9 AM motivational verse notifications (verses pre-assigned at midnight)');
        
        // Get all users who haven't opened the app today before 9 AM
        $eligibleUsers = $this->getEligibleUsers();
        
        Log::info('Found ' . count($eligibleUsers) . ' users eligible for motivational verse notification');
        
        foreach ($eligibleUsers as $user) {
            $this->sendMotivationalVerseToUser($user);
        }
        
        Log::info('✅ Completed 9 AM motivational verse notifications');
    }
    
    private function getEligibleUsers(): array
    {
        $today = Carbon::today()->toDateString();
        $nineAM = Carbon::today()->setTime(9, 0, 0);
        
        // Get users who either:
        // 1. Haven't opened the app today at all, OR
        // 2. Opened the app today but AFTER 9 AM
        $eligibleUserIds = DB::table('users')
            ->leftJoin('user_daily_activity', function ($join) use ($today) {
                $join->on('users.id', '=', 'user_daily_activity.user_id')
                     ->where('user_daily_activity.activity_date', '=', $today);
            })
            ->leftJoin('device_tokens', 'users.id', '=', 'device_tokens.user_id')
            ->where(function ($query) use ($nineAM) {
                $query->whereNull('user_daily_activity.first_opened_at') // Never opened today
                      ->orWhere('user_daily_activity.first_opened_at', '>=', $nineAM); // Opened after 9 AM
            })
            ->whereNotNull('device_tokens.device_token') // Only users with registered devices
            ->distinct()
            ->pluck('users.id')
            ->toArray();
        
        return User::whereIn('id', $eligibleUserIds)->get()->toArray();
    }
    
    private function sendMotivationalVerseToUser($user): void
    {
        try {
            // Get today's motivational verse for this user
            $verse = $this->getTodaysVerseForUser($user);
            
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
                'type' => 'motivational_verse',
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
            $deviceTokenStrings = $deviceTokens->pluck('device_token')->toArray();
            if (!empty($deviceTokenStrings)) {
                SendPushNotification::dispatch(
                    $deviceTokenStrings,
                    $notificationData['title'],
                    $notificationData['body'],
                    $notificationData['data']
                )->delay(now()->addSeconds(rand(1, 30))); // Slight random delay to spread load
            }
            
            Log::info('Sent motivational verse notification to user: ' . $user['id']);
            
        } catch (\Exception $e) {
            Log::error('Failed to send motivational verse to user ' . $user['id'] . ': ' . $e->getMessage());
        }
    }
    
    private function getTodaysVerseForUser($user): ?array
    {
        // Verses are now pre-assigned at midnight, so just fetch the existing assignment
        $tz = $user['timezone'] ?? 'UTC';
        $today = Carbon::now($tz)->toDateString();
        
        // Get today's pre-assigned verse
        $existing = DB::table('motivational_verse_user')
            ->where('user_id', $user['id'])
            ->where('shown_date', $today)
            ->first();
            
        if ($existing) {
            $verse = MotivationalVerse::where('is_active', true)->find($existing->verse_id);
            return $verse ? $verse->toArray() : null;
        }
        
        // This should not happen if midnight job worked correctly
        Log::warning("No pre-assigned verse found for user {$user['id']} on {$today} - midnight job may have failed");
        return null;
    }
    
    private function createNotificationContent($verse, $deviceToken): array
    {
        $locale = $deviceToken->locale ?? 'en';
        $isArabic = in_array($locale, ['ar', 'ar_SA', 'ar_EG', 'ar_AE']);
        
        // Localized notification title
        $title = $isArabic ? 'آية تحفيزية اليوم' : 'Motivational verse today';
        
        // Notification body with verse content
        if ($isArabic) {
            $body = $verse['arabic_text'];
            $surahInfo = $verse['surah_name_ar'] . ' - ' . $verse['ayah_number'];
        } else {
            $body = $verse['translation'];
            $surahInfo = $verse['surah_name'] . ' - ' . $verse['ayah_number'];
        }
        
        // Add surah info to the body
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
