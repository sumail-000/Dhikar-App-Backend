<?php

namespace App\Traits;

use App\Models\User;

trait PersonalizedReminderTrait
{
    /**
     * Extract first name from a full name, handling various edge cases
     */
    protected function getFirstName(string $fullName): string
    {
        if (empty(trim($fullName))) {
            return 'Friend'; // Fallback for empty names
        }

        // Clean and split the name
        $nameParts = array_filter(explode(' ', trim($fullName)));
        
        if (empty($nameParts)) {
            return 'Friend'; // Fallback for invalid names
        }

        $firstName = trim($nameParts[0]);
        
        // Return first name or fallback if empty
        return !empty($firstName) ? $firstName : 'Friend';
    }

    /**
     * Generate personalized reminder message for Khitma group members
     */
    protected function generateKhitmaReminderMessage($targetUser, $group, ?string $customMessage = null): array
    {
        $userName = $targetUser->username ?? $targetUser->name ?? '';
        $firstName = $this->getFirstName($userName);
        
        if ($customMessage) {
            // If admin provided custom message, just add the greeting
            $message = "Salam {$firstName}! {$customMessage}";
        } else {
            // Generate default message
            $message = "Salam {$firstName}! You are a member of '{$group->name}' but you haven't contributed to your assigned Juz yet. Please update your reading progress to help the group complete the Quran together.";
        }

        $title = "Khitma Group Reminder - {$group->name}";

        return [
            'message' => $message,
            'title' => $title
        ];
    }

    /**
     * Generate personalized reminder message for Dhikr group members
     */
    protected function generateDhikrReminderMessage($targetUser, $group, ?string $customMessage = null): array
    {
        $userName = $targetUser->username ?? $targetUser->name ?? '';
        $firstName = $this->getFirstName($userName);
        
        if ($customMessage) {
            // If admin provided custom message, just add the greeting
            $message = "Salam {$firstName}! {$customMessage}";
        } else {
            // Generate default message
            $message = "Salam {$firstName}! You are a member of '{$group->name}' but you haven't participated in the group dhikr yet. Please contribute to help achieve our collective spiritual goal.";
        }

        $title = "Dhikr Group Reminder - {$group->name}";

        return [
            'message' => $message,
            'title' => $title
        ];
    }

    /**
     * Safely get user by ID with error handling
     */
    protected function getUserSafely(int $userId): ?User
    {
        try {
            return User::find($userId);
        } catch (\Exception $e) {
            \Log::warning("Failed to find user with ID {$userId}: " . $e->getMessage());
            return null;
        }
    }
}
