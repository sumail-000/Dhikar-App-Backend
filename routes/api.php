<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PersonalKhitmaController;
use App\Http\Controllers\UserPreferencesController;

/*
|--------------------------------------------------------------------------
| API Routes (stateless)
|--------------------------------------------------------------------------
| These routes are loaded by the framework and are automatically prefixed
| with /api and assigned the "api" middleware group.
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'laravel' => app()->version(),
    ]);
});

// Auth endpoints
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// Password reset endpoints (OTP flow)
Route::post('password/forgot', [PasswordResetController::class, 'requestReset']);
Route::post('password/verify', [PasswordResetController::class, 'verifyCode']);
Route::post('password/reset', [PasswordResetController::class, 'resetWithCode']);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\CustomDhikrController;
use App\Http\Controllers\DhikrGroupController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\StreakController;
use App\Http\Controllers\MotivationController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\NotificationController;

// Admin Authentication Routes (no middleware)
Route::prefix('admin')->group(function () {
    Route::post('auth/login', [\App\Http\Controllers\Admin\AdminAuthController::class, 'login']);
});


// Admin Protected Routes
Route::prefix('admin')->middleware(['auth:sanctum', \App\Http\Middleware\AdminAuth::class])->group(function () {
    // Admin Auth
    Route::post('auth/logout', [\App\Http\Controllers\Admin\AdminAuthController::class, 'logout']);
    Route::get('auth/me', [\App\Http\Controllers\Admin\AdminAuthController::class, 'me']);
    Route::post('auth/refresh', [\App\Http\Controllers\Admin\AdminAuthController::class, 'refresh']);

    // User Management
    Route::get('users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
    Route::get('users/statistics', [\App\Http\Controllers\Admin\AdminUserController::class, 'statistics']);
    Route::get('users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'show']);
    Route::post('users/{id}/suspend', [\App\Http\Controllers\Admin\AdminUserController::class, 'suspend']);
    Route::post('users/{id}/activate', [\App\Http\Controllers\Admin\AdminUserController::class, 'activate']);
    Route::delete('users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy']);

    // Analytics
    Route::get('analytics/dashboard', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'dashboard']);
    Route::get('analytics/users', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'users']);
    Route::get('analytics/groups', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'groups']);
    Route::get('analytics/activity', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'activity']);
    Route::get('analytics/group-distribution', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'groupDistribution']);
    Route::get('analytics/most-used-verses', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'mostUsedVerses']);

    // Admin Groups listing
    Route::get('groups', [\App\Http\Controllers\Admin\AdminGroupController::class, 'indexKhitma']);
    Route::get('groups/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'showKhitma']);
    Route::get('dhikr-groups', [\App\Http\Controllers\Admin\AdminGroupController::class, 'indexDhikr']);
    Route::get('dhikr-groups/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'showDhikr']);

    // Group Management (enable/disable/delete)
    Route::post('groups/{id}/disable', [\App\Http\Controllers\Admin\AdminGroupController::class, 'disableKhitma']);
    Route::post('groups/{id}/enable', [\App\Http\Controllers\Admin\AdminGroupController::class, 'enableKhitma']);
    Route::delete('groups/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'deleteKhitma']);

    Route::post('dhikr-groups/{id}/disable', [\App\Http\Controllers\Admin\AdminGroupController::class, 'disableDhikr']);
    Route::post('dhikr-groups/{id}/enable', [\App\Http\Controllers\Admin\AdminGroupController::class, 'enableDhikr']);
    Route::delete('dhikr-groups/{id}', [\App\Http\Controllers\Admin\AdminGroupController::class, 'deleteDhikr']);

    // Admin profile
    Route::post('profile', [\App\Http\Controllers\Admin\AdminProfileController::class, 'update']);
    Route::get('profile/avatar', [\App\Http\Controllers\Admin\AdminProfileController::class, 'avatar']);
    Route::delete('profile/avatar', [\App\Http\Controllers\Admin\AdminProfileController::class, 'deleteAvatar']);
    Route::post('profile/password', [\App\Http\Controllers\Admin\AdminProfileController::class, 'changePassword']);

    // Motivational Verses Management (real routes)
    Route::get('motivational-verses', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'index']);
    Route::post('motivational-verses', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'store']);
    Route::get('motivational-verses/{id}', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'show']);
    Route::put('motivational-verses/{id}', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'update']);
    Route::delete('motivational-verses/{id}', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'destroy']);
    Route::post('motivational-verses/{id}/toggle', [\App\Http\Controllers\Admin\AdminMotivationalVerseController::class, 'toggle']);

    // Notification Management (placeholder routes)
    Route::get('notifications/stats', function () {
        $stats = [
            'total_sent' => \App\Models\AppNotification::count(),
            'total_read' => \App\Models\AppNotification::whereNotNull('read_at')->count(),
        ];
        return response()->json(['ok' => true, 'stats' => $stats]);
    });
    Route::post('notifications/send', function (\Illuminate\Http\Request $request) {
        // Placeholder for sending notifications
        return response()->json(['ok' => true, 'message' => 'Notification sent (placeholder)']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Push tracking (client receipts)
    Route::post('push/received', [\App\Http\Controllers\PushTrackingController::class, 'received']);
    Route::post('push/opened', [\App\Http\Controllers\PushTrackingController::class, 'opened']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/delete/check', [AuthController::class, 'checkDeletePassword']);
    Route::delete('auth/delete', [AuthController::class, 'deleteAccount']);

    // Profile
    Route::post('profile', [ProfileController::class, 'update']);
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

    // Groups (Khitma)
    Route::get('groups', [GroupController::class, 'index']);
    Route::get('groups/explore', [GroupController::class, 'explore']);
    Route::post('groups', [GroupController::class, 'store']);
    Route::get('groups/{id}', [GroupController::class, 'show']);
    Route::patch('groups/{id}', [GroupController::class, 'update']);
    Route::delete('groups/{id}', [GroupController::class, 'destroy']);
    Route::get('groups/{id}/invite', [GroupController::class, 'getInvite']);
    Route::post('groups/join', [GroupController::class, 'join']);
    Route::post('groups/{id}/join', [GroupController::class, 'joinPublic']);
    Route::post('groups/{id}/leave', [GroupController::class, 'leave']);
    Route::delete('groups/{id}/members/{userId}', [GroupController::class, 'removeMember']);

    // Dhikr Groups
    Route::get('dhikr-groups', [DhikrGroupController::class, 'index']);
    Route::get('dhikr-groups/explore', [DhikrGroupController::class, 'explore']);
    Route::post('dhikr-groups', [DhikrGroupController::class, 'store']);
    Route::get('dhikr-groups/{id}', [DhikrGroupController::class, 'show']);
    Route::patch('dhikr-groups/{id}', [DhikrGroupController::class, 'update']);
    Route::delete('dhikr-groups/{id}', [DhikrGroupController::class, 'destroy']);
    Route::get('dhikr-groups/{id}/invite', [DhikrGroupController::class, 'getInvite']);
    Route::post('dhikr-groups/join', [DhikrGroupController::class, 'join']);
    Route::post('dhikr-groups/{id}/join', [DhikrGroupController::class, 'joinPublic']);
    Route::post('dhikr-groups/{id}/leave', [DhikrGroupController::class, 'leave']);
    Route::post('dhikr-groups/{id}/progress', [DhikrGroupController::class, 'saveProgress']);
    Route::get('dhikr-groups/{id}/progress', [DhikrGroupController::class, 'getProgress']);
    Route::delete('dhikr-groups/{id}/members/{userId}', [DhikrGroupController::class, 'removeMember']);

    // Dhikr group admin reminders
    Route::post('dhikr-groups/{id}/reminders', [DhikrGroupController::class, 'sendReminder']);
    Route::post('dhikr-groups/{id}/reminders/member', [DhikrGroupController::class, 'sendMemberReminder']);

    // Quran Surah meta (Uthmanic Hafs) - handled by GroupController for now
    // Route::get('quran/surahs', [QuranController::class, 'surahs']);

    // Khitma-specific
    Route::post('groups/{id}/khitma/auto-assign', [GroupController::class, 'autoAssign']);
    Route::post('groups/{id}/khitma/manual-assign', [GroupController::class, 'manualAssign']);
    Route::get('groups/{id}/khitma/assignments', [GroupController::class, 'assignments']);
    Route::patch('groups/{id}/khitma/assignment', [GroupController::class, 'updateAssignment']);
    
    // Group khitma progress tracking
    Route::post('groups/{id}/khitma/progress', [GroupController::class, 'saveKhitmaProgress']);
    Route::get('groups/{id}/khitma/progress', [GroupController::class, 'getKhitmaProgress']);
    
    // User group khitma statistics
    Route::get('user/group-khitma-stats', [GroupController::class, 'getUserGroupKhitmaStats']);

    // Group admin reminders
    Route::post('groups/{id}/reminders', [GroupController::class, 'sendReminder']);
    Route::post('groups/{id}/reminders/member', [GroupController::class, 'sendMemberReminder']);

    // Custom Dhikr (per-user)
    Route::get('custom-dhikr', [CustomDhikrController::class, 'index']);
    Route::post('custom-dhikr', [CustomDhikrController::class, 'store']);
    Route::patch('custom-dhikr/{id}', [CustomDhikrController::class, 'update']);
    Route::delete('custom-dhikr/{id}', [CustomDhikrController::class, 'destroy']);

    // Quran/Juz meta
    Route::get('khitma/juz-pages', [GroupController::class, 'juzPages']);

    // Quran per-page content

    // Activity ping (app open)
    Route::post('activity/ping', [ActivityController::class, 'ping']);
    Route::post('activity/reading', [ActivityController::class, 'reading']);

    // Streak
    Route::get('streak', [StreakController::class, 'get']);

    // Motivational verse
    Route::get('motivation', [MotivationController::class, 'today']);

    // Device tokens (FCM)
    Route::post('devices/register', [DeviceController::class, 'register']);
    Route::post('devices/unregister', [DeviceController::class, 'unregister']);

    // In-app notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

    // User preferences
    Route::get('user/preferences', [UserPreferencesController::class, 'show']);
    Route::put('user/preferences', [UserPreferencesController::class, 'update']);

    // Personal Khitma endpoints
    Route::get('personal-khitma', [PersonalKhitmaController::class, 'index']);
    Route::get('personal-khitma/active', [PersonalKhitmaController::class, 'getActiveKhitma']);
    Route::post('personal-khitma', [PersonalKhitmaController::class, 'store']);
    Route::get('personal-khitma/{id}', [PersonalKhitmaController::class, 'show']);
    Route::post('personal-khitma/{id}/progress', [PersonalKhitmaController::class, 'saveProgress']);
    Route::patch('personal-khitma/{id}/status', [PersonalKhitmaController::class, 'updateStatus']);
    Route::delete('personal-khitma/{id}', [PersonalKhitmaController::class, 'destroy']);
    Route::get('personal-khitma/{id}/statistics', [PersonalKhitmaController::class, 'statistics']);
});
