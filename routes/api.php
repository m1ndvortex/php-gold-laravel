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

    // Invoice management routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\InvoiceController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\InvoiceController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Api\InvoiceController::class, 'statistics']);
        Route::get('/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'show']);
        Route::put('/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'destroy']);
        
        // Invoice items management
        Route::post('/{invoice}/items', [\App\Http\Controllers\Api\InvoiceController::class, 'addItem']);
        Route::put('/{invoice}/items/{item}', [\App\Http\Controllers\Api\InvoiceController::class, 'updateItem']);
        Route::delete('/{invoice}/items/{item}', [\App\Http\Controllers\Api\InvoiceController::class, 'removeItem']);
        
        // Invoice actions
        Route::post('/{invoice}/payments', [\App\Http\Controllers\Api\InvoiceController::class, 'processPayment']);
        Route::patch('/{invoice}/status', [\App\Http\Controllers\Api\InvoiceController::class, 'updateStatus']);
        Route::post('/{invoice}/cancel', [\App\Http\Controllers\Api\InvoiceController::class, 'cancel']);
        Route::post('/{invoice}/duplicate', [\App\Http\Controllers\Api\InvoiceController::class, 'duplicate']);
        Route::get('/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'generatePdf']);
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