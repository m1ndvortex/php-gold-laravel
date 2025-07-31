<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebSocketService;
use App\Services\DashboardService;
use App\Services\AlertService;
use App\Models\Product;
use App\Models\Invoice;

class TestWebSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:test {--type=all : Type of test to run (dashboard|inventory|notification|all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WebSocket broadcasting functionality';

    protected $webSocketService;
    protected $dashboardService;
    protected $alertService;

    public function __construct(
        WebSocketService $webSocketService,
        DashboardService $dashboardService,
        AlertService $alertService
    ) {
        parent::__construct();
        $this->webSocketService = $webSocketService;
        $this->dashboardService = $dashboardService;
        $this->alertService = $alertService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');

        $this->info('Starting WebSocket tests...');

        switch ($type) {
            case 'dashboard':
                $this->testDashboardBroadcast();
                break;
            case 'inventory':
                $this->testInventoryBroadcast();
                break;
            case 'notification':
                $this->testNotificationBroadcast();
                break;
            case 'all':
            default:
                $this->testDashboardBroadcast();
                $this->testInventoryBroadcast();
                $this->testNotificationBroadcast();
                break;
        }

        $this->info('WebSocket tests completed!');
    }

    private function testDashboardBroadcast()
    {
        $this->info('Testing dashboard broadcast...');

        try {
            // Test KPI update broadcast
            $kpis = [
                'sales' => ['value' => 150000, 'change_percentage' => 12.5, 'trend' => 'up'],
                'profit' => ['value' => 45000, 'change_percentage' => 8.3, 'trend' => 'up'],
                'customers' => ['new_customers' => 5, 'total_customers' => 120],
                'gold_metrics' => ['gold_sold_grams' => 250.5, 'current_gold_price' => 2800000]
            ];

            $this->webSocketService->broadcastKpiUpdate($kpis);
            $this->line('✓ KPI update broadcast sent');

            // Test sales trend update
            $salesData = [
                ['date' => '2024-01-01', 'total' => 50000, 'count' => 5],
                ['date' => '2024-01-02', 'total' => 75000, 'count' => 8],
                ['date' => '2024-01-03', 'total' => 60000, 'count' => 6],
            ];

            $this->webSocketService->broadcastSalesTrendUpdate($salesData);
            $this->line('✓ Sales trend update broadcast sent');

        } catch (\Exception $e) {
            $this->error('Dashboard broadcast test failed: ' . $e->getMessage());
        }
    }

    private function testInventoryBroadcast()
    {
        $this->info('Testing inventory broadcast...');

        try {
            // Get a sample product or create test data
            $product = Product::first();
            
            if (!$product) {
                $this->warn('No products found. Creating test product...');
                $product = Product::create([
                    'name' => 'Test Gold Ring',
                    'sku' => 'TEST-001',
                    'category_id' => 1,
                    'type' => 'finished_jewelry',
                    'gold_weight' => 5.5,
                    'current_stock' => 10,
                    'minimum_stock' => 2,
                    'unit_price' => 15000000,
                ]);
            }

            // Test inventory update broadcast
            $this->webSocketService->broadcastInventoryUpdate(
                $product->id,
                $product->name,
                10,
                8,
                'sale'
            );

            $this->line('✓ Inventory update broadcast sent for product: ' . $product->name);

        } catch (\Exception $e) {
            $this->error('Inventory broadcast test failed: ' . $e->getMessage());
        }
    }

    private function testNotificationBroadcast()
    {
        $this->info('Testing notification broadcast...');

        try {
            // Test different types of notifications
            $notifications = [
                [
                    'type' => 'low_stock',
                    'title' => 'موجودی کم',
                    'message' => 'موجودی محصول حلقه طلا کمتر از حد مجاز است',
                    'data' => ['product_id' => 1, 'current_stock' => 1, 'minimum_stock' => 5]
                ],
                [
                    'type' => 'overdue_invoice',
                    'title' => 'فاکتور معوقه',
                    'message' => 'فاکتور شماره INV-001 معوقه شده است',
                    'data' => ['invoice_id' => 1, 'days_overdue' => 15]
                ],
                [
                    'type' => 'payment_received',
                    'title' => 'پرداخت دریافت شد',
                    'message' => 'پرداخت 5,000,000 تومان از مشتری احمد احمدی دریافت شد',
                    'data' => ['amount' => 5000000, 'customer_name' => 'احمد احمدی']
                ],
                [
                    'type' => 'system_error',
                    'title' => 'خطای سیستم',
                    'message' => 'خطا در اتصال به سرویس قیمت طلا',
                    'data' => ['service' => 'gold_price_api', 'error_code' => 'CONNECTION_TIMEOUT']
                ]
            ];

            foreach ($notifications as $notification) {
                $this->webSocketService->sendAlert(
                    $notification['type'],
                    $notification['title'],
                    $notification['message'],
                    $notification['data']
                );

                $this->line('✓ ' . $notification['type'] . ' notification sent: ' . $notification['title']);
                
                // Small delay between notifications
                usleep(500000); // 0.5 seconds
            }

        } catch (\Exception $e) {
            $this->error('Notification broadcast test failed: ' . $e->getMessage());
        }
    }
}