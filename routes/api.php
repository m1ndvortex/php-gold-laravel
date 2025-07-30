<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TwoFactorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('login.anomaly');
    Route::post('verify-2fa', [AuthController::class, 'verifyTwoFactor'])->middleware('login.anomaly');
});

// Protected routes with tenant context
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('sessions', [AuthController::class, 'sessions']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('logout-session', [AuthController::class, 'logoutSession']);
    });

    // Session management routes
    Route::prefix('sessions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SessionController::class, 'index']);
        Route::delete('/{sessionId}', [\App\Http\Controllers\Api\SessionController::class, 'destroy']);
        Route::post('/logout-others', [\App\Http\Controllers\Api\SessionController::class, 'destroyOthers']);
        Route::post('/logout-all', [\App\Http\Controllers\Api\SessionController::class, 'destroyAll']);
        Route::get('/timeout', [\App\Http\Controllers\Api\SessionController::class, 'timeout']);
        Route::get('/anomalies', [\App\Http\Controllers\Api\SessionController::class, 'anomalies']);
    });

    // Two-factor authentication routes
    Route::prefix('2fa')->group(function () {
        Route::get('status', [TwoFactorController::class, 'status']);
        Route::post('enable', [TwoFactorController::class, 'enable']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::post('regenerate-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });

    // Legacy user route for compatibility
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role.permissions');
    });
});

// Test routes without tenant middleware (for testing purposes)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/test/user', function (Request $request) {
        return $request->user()->load('role.permissions');
    });
    
    Route::post('/test/logout', [AuthController::class, 'logout']);
});