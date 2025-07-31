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

    // Inventory management routes
    Route::prefix('inventory')->group(function () {
        // Product management
        Route::get('/products', [\App\Http\Controllers\Api\InventoryController::class, 'index']);
        Route::post('/products', [\App\Http\Controllers\Api\InventoryController::class, 'store']);
        Route::get('/products/{product}', [\App\Http\Controllers\Api\InventoryController::class, 'show']);
        Route::put('/products/{product}', [\App\Http\Controllers\Api\InventoryController::class, 'update']);
        Route::delete('/products/{product}', [\App\Http\Controllers\Api\InventoryController::class, 'destroy']);
        
        // Stock management
        Route::post('/products/{product}/stock', [\App\Http\Controllers\Api\InventoryController::class, 'updateStock']);
        Route::post('/products/{product}/adjust-stock', [\App\Http\Controllers\Api\InventoryController::class, 'adjustStock']);
        Route::get('/products/{product}/stock-history', [\App\Http\Controllers\Api\InventoryController::class, 'stockHistory']);
        
        // Barcode and QR code management
        Route::post('/products/{product}/barcode', [\App\Http\Controllers\Api\InventoryController::class, 'generateBarcode']);
        Route::post('/products/{product}/qr-code', [\App\Http\Controllers\Api\InventoryController::class, 'generateQrCode']);
        Route::post('/scan-code', [\App\Http\Controllers\Api\InventoryController::class, 'scanCode']);
        
        // Reports and analytics
        Route::get('/low-stock', [\App\Http\Controllers\Api\InventoryController::class, 'lowStock']);
        Route::get('/valuation', [\App\Http\Controllers\Api\InventoryController::class, 'valuation']);
        
        // Categories
        Route::get('/categories', [\App\Http\Controllers\Api\InventoryController::class, 'categories']);
    });

    // Bill of Materials (BOM) routes
    Route::prefix('bom')->group(function () {
        Route::get('/products/{product}', [\App\Http\Controllers\Api\BomController::class, 'show']);
        Route::post('/products/{product}', [\App\Http\Controllers\Api\BomController::class, 'store']);
        Route::get('/products/{product}/cost', [\App\Http\Controllers\Api\BomController::class, 'calculateCost']);
        Route::get('/products/{product}/stock-check', [\App\Http\Controllers\Api\BomController::class, 'checkStock']);
        Route::post('/products/{product}/produce', [\App\Http\Controllers\Api\BomController::class, 'processProduction']);
        Route::get('/products/{product}/explosion', [\App\Http\Controllers\Api\BomController::class, 'explosion']);
        Route::post('/products/{product}/clone', [\App\Http\Controllers\Api\BomController::class, 'cloneBom']);
        Route::post('/products/{product}/compare', [\App\Http\Controllers\Api\BomController::class, 'compare']);
        Route::get('/components/{component}/usage', [\App\Http\Controllers\Api\BomController::class, 'getProductsUsingComponent']);
        
        // BOM item management
        Route::put('/items/{bomItem}', [\App\Http\Controllers\Api\BomController::class, 'updateBomItem']);
        Route::delete('/items/{bomItem}', [\App\Http\Controllers\Api\BomController::class, 'deleteBomItem']);
    });

    // Customer management routes
    Route::prefix('customers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Api\CustomerController::class, 'statistics']);
        Route::get('/birthdays/upcoming', [\App\Http\Controllers\Api\CustomerController::class, 'upcomingBirthdays']);
        Route::get('/birthdays/today', [\App\Http\Controllers\Api\CustomerController::class, 'todaysBirthdays']);
        Route::post('/import', [\App\Http\Controllers\Api\CustomerController::class, 'import']);
        Route::get('/export', [\App\Http\Controllers\Api\CustomerController::class, 'export']);
        
        Route::get('/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);
        Route::put('/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::delete('/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
        
        // Customer ledger management
        Route::get('/{customerId}/ledger', [\App\Http\Controllers\Api\CustomerController::class, 'ledger']);
        Route::post('/{customer}/ledger', [\App\Http\Controllers\Api\CustomerController::class, 'createLedgerEntry']);
    });

    // Customer groups management routes
    Route::prefix('customer-groups')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CustomerGroupController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CustomerGroupController::class, 'store']);
        Route::get('/{customerGroup}', [\App\Http\Controllers\Api\CustomerGroupController::class, 'show']);
        Route::put('/{customerGroup}', [\App\Http\Controllers\Api\CustomerGroupController::class, 'update']);
        Route::delete('/{customerGroup}', [\App\Http\Controllers\Api\CustomerGroupController::class, 'destroy']);
        
        // Group customer management
        Route::get('/{customerGroup}/customers', [\App\Http\Controllers\Api\CustomerGroupController::class, 'customers']);
        Route::post('/{customerGroup}/move-customers', [\App\Http\Controllers\Api\CustomerGroupController::class, 'moveCustomers']);
    });

    // Customer notifications management routes
    Route::prefix('customer-notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'store']);
        Route::get('/pending', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'pending']);
        Route::get('/statistics', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'statistics']);
        Route::post('/create-birthday', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'createBirthdayNotifications']);
        Route::post('/create-overdue', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'createOverdueNotifications']);
        Route::post('/create-credit-limit', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'createCreditLimitNotifications']);
        Route::post('/process-pending', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'processPending']);
        Route::get('/customer/{customerId}/history', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'customerHistory']);
        
        Route::get('/{notification}', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'show']);
        Route::put('/{notification}', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'update']);
        Route::post('/{notification}/cancel', [\App\Http\Controllers\Api\CustomerNotificationController::class, 'cancel']);
    });

    // Accounting and financial management routes
    Route::prefix('accounting')->group(function () {
        // Chart of Accounts
        Route::get('/chart-of-accounts', [\App\Http\Controllers\Api\AccountingController::class, 'chartOfAccounts']);
        Route::post('/accounts', [\App\Http\Controllers\Api\AccountingController::class, 'createAccount']);
        Route::put('/accounts/{account}', [\App\Http\Controllers\Api\AccountingController::class, 'updateAccount']);
        Route::post('/initialize-chart', [\App\Http\Controllers\Api\AccountingController::class, 'initializeChartOfAccounts']);
        
        // Journal Entries
        Route::get('/journal-entries', [\App\Http\Controllers\Api\AccountingController::class, 'journalEntries']);
        Route::post('/journal-entries', [\App\Http\Controllers\Api\AccountingController::class, 'createJournalEntry']);
        Route::post('/journal-entries/{journalEntry}/post', [\App\Http\Controllers\Api\AccountingController::class, 'postJournalEntry']);
        Route::post('/journal-entries/{journalEntry}/reverse', [\App\Http\Controllers\Api\AccountingController::class, 'reverseJournalEntry']);
        Route::post('/recurring-entries/process', [\App\Http\Controllers\Api\AccountingController::class, 'processRecurringEntries']);
        
        // Financial Reports
        Route::get('/reports/trial-balance', [\App\Http\Controllers\Api\AccountingController::class, 'trialBalance']);
        Route::get('/reports/profit-loss', [\App\Http\Controllers\Api\AccountingController::class, 'profitLoss']);
        Route::get('/reports/balance-sheet', [\App\Http\Controllers\Api\AccountingController::class, 'balanceSheet']);
        Route::get('/accounts/{account}/general-ledger', [\App\Http\Controllers\Api\AccountingController::class, 'generalLedger']);
    });

    // Dashboard and Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('/data', [\App\Http\Controllers\Api\DashboardController::class, 'getDashboardData']);
        Route::get('/kpis', [\App\Http\Controllers\Api\DashboardController::class, 'getKPIs']);
        Route::get('/sales-trend', [\App\Http\Controllers\Api\DashboardController::class, 'getSalesTrend']);
        Route::get('/top-products', [\App\Http\Controllers\Api\DashboardController::class, 'getTopProducts']);
        Route::get('/alerts', [\App\Http\Controllers\Api\DashboardController::class, 'getAlerts']);
        Route::get('/alert-counts', [\App\Http\Controllers\Api\DashboardController::class, 'getAlertCounts']);
        Route::get('/widget-layout', [\App\Http\Controllers\Api\DashboardController::class, 'getWidgetLayout']);
        Route::put('/widget-layout', [\App\Http\Controllers\Api\DashboardController::class, 'updateWidgetLayout']);
    });

    // WebSocket and Real-time features
    Route::prefix('websocket')->group(function () {
        Route::get('/connection-info', [\App\Http\Controllers\Api\WebSocketController::class, 'getConnectionInfo']);
        Route::post('/test-connection', [\App\Http\Controllers\Api\WebSocketController::class, 'testConnection']);
        Route::post('/broadcast/dashboard', [\App\Http\Controllers\Api\WebSocketController::class, 'broadcastDashboardUpdate']);
        Route::post('/broadcast/sales-trend', [\App\Http\Controllers\Api\WebSocketController::class, 'broadcastSalesTrend']);
        Route::post('/broadcast/alerts', [\App\Http\Controllers\Api\WebSocketController::class, 'broadcastAlerts']);
        Route::post('/send-notification', [\App\Http\Controllers\Api\WebSocketController::class, 'sendNotification']);
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