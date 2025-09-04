<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PersonalKhitmaController;

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

Route::middleware('auth:sanctum')->group(function () {
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
