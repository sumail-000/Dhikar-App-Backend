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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::delete('auth/delete', [AuthController::class, 'deleteAccount']);
});
