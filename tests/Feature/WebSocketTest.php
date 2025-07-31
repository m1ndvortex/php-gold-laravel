<?php

namespace Tests\Feature;

use Tests\TenantTestCase;
use App\Services\WebSocketService;
use App\Events\DashboardUpdated;
use App\Events\InventoryUpdated;
use App\Events\NotificationSent;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebSocketTest extends TenantTestCase
{
    use RefreshDatabase;

    protected $webSocketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webSocketService = app(WebSocketService::class);
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

        $this->webSocketService->broadcastDashboardUpdate($data);

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($data) {
            return $event->data === $data && $event->tenantId === $this->tenant->id;
        });
    }

    /** @test */
    public function it_can_broadcast_inventory_updates()
    {
        Event::fake();

        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Test Gold Ring',
            'current_stock' => 10
        ]);

        $this->webSocketService->broadcastInventoryUpdate(
            $product->id,
            $product->name,
            10,
            8,
            'sale'
        );

        Event::assertDispatched(InventoryUpdated::class, function ($event) use ($product) {
            return $event->productId === $product->id &&
                   $event->productName === $product->name &&
                   $event->oldStock === 10 &&
                   $event->newStock === 8 &&
                   $event->changeType === 'sale' &&
                   $event->tenantId === $this->tenant->id;
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

        $this->webSocketService->broadcastNotification($notification);

        Event::assertDispatched(NotificationSent::class, function ($event) use ($notification) {
            return $event->notification === $notification && $event->tenantId === $this->tenant->id;
        });
    }

    /** @test */
    public function it_can_send_alerts_with_different_severities()
    {
        Event::fake();

        $alertTypes = [
            'low_stock' => 'warning',
            'overdue_invoice' => 'warning',
            'system_error' => 'error',
            'payment_received' => 'success',
            'info_message' => 'info'
        ];

        foreach ($alertTypes as $type => $expectedSeverity) {
            $this->webSocketService->sendAlert(
                $type,
                'Test Alert',
                'This is a test alert message',
                ['test' => true]
            );

            Event::assertDispatched(NotificationSent::class, function ($event) use ($type, $expectedSeverity) {
                return $event->notification['type'] === $type &&
                       $event->notification['severity'] === $expectedSeverity;
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

        $this->webSocketService->broadcastKpiUpdate($kpis);

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($kpis) {
            return $event->data['type'] === 'kpi_update' &&
                   $event->data['kpis'] === $kpis;
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

        $this->webSocketService->broadcastAlertUpdate($alerts);

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($alerts) {
            return $event->data['type'] === 'alert_update' &&
                   $event->data['alerts'] === $alerts;
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

        $this->webSocketService->broadcastSalesTrendUpdate($salesData);

        Event::assertDispatched(DashboardUpdated::class, function ($event) use ($salesData) {
            return $event->data['type'] === 'sales_trend_update' &&
                   $event->data['sales_data'] === $salesData;
        });
    }

    /** @test */
    public function websocket_controller_can_get_connection_info()
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/websocket/connection-info');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'tenant_id',
                    'channels' => [
                        'tenant',
                        'user',
                        'dashboard',
                        'inventory',
                        'notifications'
                    ],
                    'broadcast_driver',
                    'pusher_config' => [
                        'key',
                        'cluster',
                        'host',
                        'port',
                        'scheme'
                    ]
                ]
            ]);

        $this->assertEquals($user->id, $response->json('data.user_id'));
        $this->assertEquals($this->tenant->id, $response->json('data.tenant_id'));
    }

    /** @test */
    public function websocket_controller_can_test_connection()
    {
        Event::fake();
        
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/websocket/test-connection');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'WebSocket test completed successfully'
            ]);

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification['type'] === 'connection_test';
        });
    }

    /** @test */
    public function websocket_controller_can_send_custom_notification()
    {
        Event::fake();
        
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $notificationData = [
            'type' => 'custom_alert',
            'title' => 'Custom Alert Title',
            'message' => 'This is a custom alert message',
            'data' => ['custom' => 'data']
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/websocket/send-notification', $notificationData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification sent successfully'
            ]);

        Event::assertDispatched(NotificationSent::class, function ($event) use ($notificationData) {
            return $event->notification['type'] === $notificationData['type'] &&
                   $event->notification['title'] === $notificationData['title'] &&
                   $event->notification['message'] === $notificationData['message'];
        });
    }

    /** @test */
    public function websocket_controller_can_broadcast_dashboard_update()
    {
        Event::fake();
        
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/websocket/broadcast/dashboard', ['period' => 'today']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard update broadcasted successfully'
            ]);

        Event::assertDispatched(DashboardUpdated::class);
    }

    /** @test */
    public function websocket_controller_requires_authentication()
    {
        $response = $this->getJson('/api/websocket/connection-info');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/websocket/test-connection');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/websocket/send-notification', [
            'type' => 'test',
            'title' => 'Test',
            'message' => 'Test message'
        ]);
        $response->assertUnauthorized();
    }

    /** @test */
    public function websocket_controller_validates_notification_data()
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Missing required fields
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/websocket/send-notification', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'title', 'message']);

        // Invalid data types
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/websocket/send-notification', [
                'type' => 123, // Should be string
                'title' => str_repeat('a', 300), // Too long
                'message' => null, // Should be string
                'user_id' => 'invalid' // Should be integer
            ]);

        $response->assertStatus(422);
    }
}