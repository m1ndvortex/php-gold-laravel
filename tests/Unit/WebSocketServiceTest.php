<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WebSocketService;
use App\Events\DashboardUpdated;
use App\Events\InventoryUpdated;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebSocketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $webSocketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webSocketService = new WebSocketService();
    }

    /** @test */
    public function it_can_broadcast_dashboard_updates()
    {
        Event::fake();

        $data = [
            'type' => 'kpi_update',
            'kpis' => [
                'sales' => ['value' => 150000, 'change_percentage' => 12.5],
                'profit' => ['value' => 45000, 'change_percentage' => 8.3],
            ]
        ];

        $this->webSocketService->broadcastDashboardUpdate($data, 'test-tenant-1');

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($data) {
            return $event->data === $data && $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_can_broadcast_inventory_updates()
    {
        Event::fake();

        $this->webSocketService->broadcastInventoryUpdate(
            1,
            'Test Gold Ring',
            10,
            8,
            'sale',
            'test-tenant-1'
        );

        Event::assertDispatched(InventoryUpdated::class, function ($event) {
            return $event->productId === 1 &&
                   $event->productName === 'Test Gold Ring' &&
                   $event->oldStock === 10 &&
                   $event->newStock === 8 &&
                   $event->changeType === 'sale' &&
                   $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_can_broadcast_notifications()
    {
        Event::fake();

        $notification = [
            'id' => 'test-notification-1',
            'type' => 'low_stock',
            'title' => 'Low Stock Alert',
            'message' => 'Product stock is running low',
            'data' => ['product_id' => 1],
            'severity' => 'warning',
            'created_at' => now()->toISOString()
        ];

        $this->webSocketService->broadcastNotification($notification, 1, 'test-tenant-1');

        Event::assertDispatched(NotificationSent::class, function ($event) use ($notification) {
            return $event->notification === $notification &&
                   $event->userId === 1 &&
                   $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_can_send_alerts_with_correct_severity()
    {
        Event::fake();

        $alertTypes = [
            'low_stock' => 'warning',
            'overdue_invoice' => 'warning',
            'cheque_due' => 'warning',
            'system_error' => 'error',
            'security_alert' => 'error',
            'payment_received' => 'success',
            'sale_completed' => 'success',
            'info_message' => 'info'
        ];

        foreach ($alertTypes as $type => $expectedSeverity) {
            $this->webSocketService->sendAlert(
                $type,
                'Test Alert',
                'This is a test alert message',
                ['test' => true],
                1,
                'test-tenant-1'
            );

            Event::assertDispatched(NotificationSent::class, function ($event) use ($type, $expectedSeverity) {
                return $event->notification['type'] === $type &&
                       $event->notification['severity'] === $expectedSeverity &&
                       $event->notification['title'] === 'Test Alert' &&
                       $event->notification['message'] === 'This is a test alert message';
            });
        }
    }

    /** @test */
    public function it_can_broadcast_kpi_updates()
    {
        Event::fake();

        $kpis = [
            'sales' => ['value' => 150000, 'change_percentage' => 12.5, 'trend' => 'up'],
            'profit' => ['value' => 45000, 'change_percentage' => 8.3, 'trend' => 'up'],
            'customers' => ['new_customers' => 5, 'total_customers' => 120],
            'gold_metrics' => ['gold_sold_grams' => 250.5, 'current_gold_price' => 2800000]
        ];

        $this->webSocketService->broadcastKpiUpdate($kpis, 'test-tenant-1');

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($kpis) {
            return $event->data['type'] === 'kpi_update' &&
                   $event->data['kpis'] === $kpis &&
                   $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_can_broadcast_alert_updates()
    {
        Event::fake();

        $alerts = [
            'overdue_invoices' => ['count' => 5, 'total_amount' => 250000],
            'low_inventory' => ['count' => 3, 'critical_count' => 1],
            'cheques_due' => ['count' => 2, 'due_today' => 1]
        ];

        $this->webSocketService->broadcastAlertUpdate($alerts, 'test-tenant-1');

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($alerts) {
            return $event->data['type'] === 'alert_update' &&
                   $event->data['alerts'] === $alerts &&
                   $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_can_broadcast_sales_trend_updates()
    {
        Event::fake();

        $salesData = [
            ['date' => '2024-01-01', 'total' => 50000, 'count' => 5],
            ['date' => '2024-01-02', 'total' => 75000, 'count' => 8],
            ['date' => '2024-01-03', 'total' => 60000, 'count' => 6],
        ];

        $this->webSocketService->broadcastSalesTrendUpdate($salesData, 'test-tenant-1');

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($salesData) {
            return $event->data['type'] === 'sales_trend_update' &&
                   $event->data['sales_data'] === $salesData &&
                   $event->tenantId === 'test-tenant-1';
        });
    }

    /** @test */
    public function it_handles_broadcast_errors_gracefully()
    {
        // This test ensures that broadcasting errors don't break the application
        $this->webSocketService->broadcastDashboardUpdate([], 'invalid-tenant');
        
        // If we reach this point, the method handled the error gracefully
        $this->assertTrue(true);
    }
}