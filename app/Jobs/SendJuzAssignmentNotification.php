<?php

namespace App\Jobs;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendJuzAssignmentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $groupId;
    private string $assignmentType; // 'auto' or 'manual'
    private array $affectedUserIds;
    private ?array $specificAssignments; // For manual assignments with specific Juz details

    public function __construct(int $groupId, string $assignmentType, array $affectedUserIds, ?array $specificAssignments = null)
    {
        $this->groupId = $groupId;
        $this->assignmentType = $assignmentType;
        $this->affectedUserIds = $affectedUserIds;
        $this->specificAssignments = $specificAssignments;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            Log::info("🕌 Processing Juz assignment notifications for group {$this->groupId} ({$this->assignmentType})");

            $group = Group::with(['creator'])->find($this->groupId);
            if (!$group || $group->type !== 'khitma') {
                Log::warning("Group {$this->groupId} not found or not a Khitma group");
                return;
            }

            $admin = $group->creator;
            $adminName = $admin ? $this->getFirstName($admin->username ?? 'Admin') : 'Admin';

            if ($this->assignmentType === 'auto') {
                $this->handleAutoAssignmentNotifications($group, $adminName);
            } else {
                $this->handleManualAssignmentNotifications($group, $adminName);
            }

            Log::info("✅ Juz assignment notifications sent for group {$this->groupId}");

        } catch (\Exception $e) {
            Log::error("Failed to send Juz assignment notifications for group {$this->groupId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function handleAutoAssignmentNotifications(Group $group, string $adminName): void
    {
        Log::info("📢 Sending auto-assignment notifications to " . count($this->affectedUserIds) . " users");

        // For auto assignment, send a general notification to all group members
        $users = User::whereIn('id', $this->affectedUserIds)->get();

        foreach ($users as $user) {
            $this->sendNotificationToUser($user, $group, $adminName, 'auto');
        }
    }

    private function handleManualAssignmentNotifications(Group $group, string $adminName): void
    {
        Log::info("📝 Sending manual assignment notifications to " . count($this->affectedUserIds) . " users");

        // For manual assignment, send specific Juz information if available
        $users = User::whereIn('id', $this->affectedUserIds)->get();

        foreach ($users as $user) {
            $userAssignments = $this->getUserSpecificAssignments($user->id);
            $this->sendNotificationToUser($user, $group, $adminName, 'manual', $userAssignments);
        }
    }

    private function sendNotificationToUser(User $user, Group $group, string $adminName, string $type, ?array $userAssignments = null): void
    {
        try {
            // Get user's device tokens for localization
            $deviceTokens = DeviceToken::where('user_id', $user->id)
                ->whereNotNull('device_token')
                ->get();

            if ($deviceTokens->isEmpty()) {
                Log::warning("No device tokens found for user {$user->id}");
                return;
            }

            // Create notification content with Islamic greeting
            $notificationData = $this->createNotificationContent(
                $user, 
                $group, 
                $adminName, 
                $type, 
                $userAssignments,
                $deviceTokens->first()
            );

            // Create in-app notification
            $appNotification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'juz_assignment',
                'title' => $notificationData['title'],
                'body' => $notificationData['body'],
                'data' => [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'assignment_type' => $type,
                    'admin_name' => $adminName,
                    'assignments' => $userAssignments,
                    'navigate_to' => 'group_khitma_assignments'
                ],
                'read_at' => null,
            ]);

            // Send push notifications to all user's devices at once
            $deviceTokenStrings = $deviceTokens->pluck('device_token')->toArray();
            if (!empty($deviceTokenStrings)) {
                SendPushNotification::dispatch(
                    $deviceTokenStrings,
                    $notificationData['title'],
                    $notificationData['body'],
                    $notificationData['data']
                )->delay(now()->addSeconds(rand(1, 10)));
            }

            Log::info("📱 Juz assignment notification sent to user {$user->id} ({$user->username})");

        } catch (\Exception $e) {
            Log::error("Failed to send notification to user {$user->id}: " . $e->getMessage());
        }
    }

    private function createNotificationContent(User $user, Group $group, string $adminName, string $type, ?array $userAssignments, DeviceToken $deviceToken): array
    {
        $locale = $deviceToken->locale ?? 'en';
        $isArabic = in_array($locale, ['ar', 'ar_SA', 'ar_EG', 'ar_AE', 'ar_MA', 'ar_DZ', 'ar_TN', 'ar_LY', 'ar_SD', 'ar_SY', 'ar_LB', 'ar_JO', 'ar_PS', 'ar_IQ', 'ar_KW', 'ar_BH', 'ar_QA', 'ar_OM', 'ar_YE']);

        $firstName = $this->getFirstName($user->username ?? 'أخي الكريم');

        if ($type === 'auto') {
            return $this->createAutoAssignmentContent($firstName, $group, $adminName, $isArabic);
        } else {
            return $this->createManualAssignmentContent($firstName, $group, $adminName, $userAssignments, $isArabic);
        }
    }

    private function createAutoAssignmentContent(string $firstName, Group $group, string $adminName, bool $isArabic): array
    {
        if ($isArabic) {
            $title = 'تم توزيع الأجزاء - ' . $group->name;
            $body = "السلام عليكم {$firstName}! تفقد الجزء المخصص لك في المجموعة";
        } else {
            $title = 'Juz Assigned - ' . $group->name;
            $body = "Assalamu Alaikum {$firstName}! Check your assigned Juz in the group";
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'juz_assignment_auto',
                'group_id' => (string) $group->id,
                'group_name' => $group->name,
                'assignment_type' => 'auto',
                'navigate_to' => 'group_khitma_assignments'
            ]
        ];
    }

    private function createManualAssignmentContent(string $firstName, Group $group, string $adminName, ?array $userAssignments, bool $isArabic): array
    {
        $juzDetails = '';
        if ($userAssignments && !empty($userAssignments)) {
            $juzNumbers = array_column($userAssignments, 'juz_number');
            $juzList = implode(', ', $juzNumbers);
            
            if ($isArabic) {
                $juzDetails = count($juzNumbers) > 1 
                    ? "الأجزاء المخصصة لك: {$juzList}" 
                    : "الجزء المخصص لك: {$juzList}";
            } else {
                $juzDetails = count($juzNumbers) > 1 
                    ? "Your assigned Juz: {$juzList}" 
                    : "Your assigned Juz: {$juzList}";
            }
        }

        if ($isArabic) {
            $title = 'تخصيص جديد - ' . $group->name;
            if ($juzDetails) {
                $body = "السلام عليكم {$firstName}! {$juzDetails}. ابدأ القراءة!";
            } else {
                $body = "السلام عليكم {$firstName}! تم تخصيص أجزاء لك. ابدأ القراءة!";
            }
        } else {
            $title = 'New Juz Assignment - ' . $group->name;
            if ($juzDetails) {
                $body = "Assalamu Alaikum {$firstName}! {$juzDetails}. Start reading!";
            } else {
                $body = "Assalamu Alaikum {$firstName}! You got new Juz assigned. Start reading!";
            }
        }

        // Convert assignments to FCM-compatible format (flat string values only)
        $assignedJuzList = '';
        if ($userAssignments && !empty($userAssignments)) {
            $juzNumbers = array_column($userAssignments, 'juz_number');
            $assignedJuzList = implode(',', $juzNumbers);
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'juz_assignment_manual',
                'group_id' => (string) $group->id,
                'group_name' => $group->name,
                'assignment_type' => 'manual',
                'assigned_juz' => $assignedJuzList,
                'navigate_to' => 'group_khitma_assignments'
            ]
        ];
    }

    private function getUserSpecificAssignments(int $userId): ?array
    {
        if (!$this->specificAssignments) {
            return null;
        }

        $userAssignments = [];
        foreach ($this->specificAssignments as $assignment) {
            if ($assignment['user_id'] == $userId) {
                foreach ($assignment['juz_numbers'] as $juzNumber) {
                    $userAssignments[] = [
                        'juz_number' => $juzNumber,
                        'user_id' => $userId
                    ];
                }
            }
        }

        return empty($userAssignments) ? null : $userAssignments;
    }

    private function getFirstName(string $fullName): string
    {
        if (empty(trim($fullName))) {
            return 'أخي الكريم'; // "Dear brother" in Arabic
        }

        $parts = explode(' ', trim($fullName));
        $firstName = $parts[0];

        // Handle common Islamic names and provide respectful alternatives
        if (strlen($firstName) < 2) {
            return 'أخي الكريم';
        }

        return $firstName;
    }
}
