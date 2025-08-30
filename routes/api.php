<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/delete/check', [AuthController::class, 'checkDeletePassword']);
    Route::delete('auth/delete', [AuthController::class, 'deleteAccount']);

    // Profile
    Route::post('profile', [ProfileController::class, 'update']);
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

    // Groups
    Route::get('groups', [GroupController::class, 'index']);
    Route::get('groups/explore', [GroupController::class, 'explore']);
    Route::post('groups', [GroupController::class, 'store']);
    Route::get('groups/{id}', [GroupController::class, 'show']);
    Route::delete('groups/{id}', [GroupController::class, 'destroy']);
    Route::get('groups/{id}/invite', [GroupController::class, 'getInvite']);
    Route::post('groups/join', [GroupController::class, 'join']);
    Route::post('groups/{id}/join', [GroupController::class, 'joinPublic']);
    Route::post('groups/{id}/leave', [GroupController::class, 'leave']);
    Route::delete('groups/{id}/members/{userId}', [GroupController::class, 'removeMember']);

    // Khitma-specific
    Route::post('groups/{id}/khitma/auto-assign', [GroupController::class, 'autoAssign']);
    Route::post('groups/{id}/khitma/manual-assign', [GroupController::class, 'manualAssign']);
    Route::get('groups/{id}/khitma/assignments', [GroupController::class, 'assignments']);
    Route::patch('groups/{id}/khitma/assignment', [GroupController::class, 'updateAssignment']);
});
