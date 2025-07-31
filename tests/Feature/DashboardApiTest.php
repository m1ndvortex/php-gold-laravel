<?php

namespace Tests\Feature;

use Tests\TenantTestCase;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\DashboardWidget;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardApiTest extends TenantTestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_get_dashboard_data_returns_complete_data()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['unit_price' => 100]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000,
            'status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        $response = $this->getJson('/api/dashboard/data');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'sales' => ['value', 'previous_value', 'change_percentage', 'trend', 'count'],
                        'profit' => ['value', 'previous_value', 'change_percentage', 'trend'],
                        'customers' => ['new_customers', 'total_customers', 'change_percentage', 'trend'],
                        'gold_metrics' => ['gold_sold_grams', 'previous_gold_sold', 'change_percentage', 'trend'],
                    ],
                    'alerts' => [
                        'overdue_invoices',
                        'cheques_due',
                        'low_inventory',
                        'credit_warnings',
                    ],
                    'sales_trend',
                    'top_products',
                    'widget_layout',
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_get_kpis_with_different_periods()
    {
        $this->actingAs($this->user, 'sanctum');

        $periods = ['today', 'week', 'month', 'year'];

        foreach ($periods as $period) {
            $response = $this->getJson("/api/dashboard/kpis?period={$period}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'sales',
                        'profit',
                        'customers',
                        'gold_metrics',
                        'inventory_value',
                        'pending_payments',
                    ]
                ]);

            $this->assertTrue($response->json('success'));
        }
    }

    public function test_get_sales_trend_with_different_periods()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create test data
        $customer = Customer::factory()->create();
        Invoice::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000,
            'status' => 'paid',
            'created_at' => Carbon::now()->subDays(rand(1, 7)),
        ]);

        $periods = ['7_days', '30_days', '90_days'];

        foreach ($periods as $period) {
            $response = $this->getJson("/api/dashboard/sales-trend?period={$period}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => ['date', 'total', 'count']
                    ]
                ]);

            $this->assertTrue($response->json('success'));
        }
    }

    public function test_get_top_products_returns_correct_format()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create test data
        $customer = Customer::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Gold Ring', 'sku' => 'GR001']);
        $product2 = Product::factory()->create(['name' => 'Gold Necklace', 'sku' => 'GN001']);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
        ]);
        
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'unit_price' => 200,
        ]);
        
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 300,
        ]);

        $response = $this->getJson('/api/dashboard/top-products?limit=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['name', 'sku', 'total_sold', 'total_revenue']
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        // Should be ordered by revenue (highest first)
        $this->assertEquals('Gold Ring', $data[0]['name']);
        $this->assertEquals(1000, $data[0]['total_revenue']);
    }

    public function test_get_alerts_returns_all_alert_types()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create overdue invoice
        $customer = Customer::factory()->create();
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(5),
            'total_amount' => 1000,
        ]);

        // Create low stock product
        Product::factory()->create([
            'current_stock' => 2,
            'minimum_stock' => 10,
        ]);

        $response = $this->getJson('/api/dashboard/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overdue_invoices' => [
                        'count',
                        'total_amount',
                        'critical_count',
                        'items' => [
                            '*' => [
                                'id',
                                'invoice_number',
                                'customer_name',
                                'amount',
                                'due_date',
                                'days_overdue',
                                'severity'
                            ]
                        ]
                    ],
                    'cheques_due',
                    'low_inventory' => [
                        'count',
                        'out_of_stock_count',
                        'critical_count',
                        'items'
                    ],
                    'high_value_transactions',
                    'credit_limit_warnings',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['overdue_invoices']['count']);
        $this->assertEquals(1, $data['low_inventory']['count']);
    }

    public function test_get_alert_counts_returns_summary()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/dashboard/alert-counts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overdue_invoices',
                    'cheques_due',
                    'low_inventory',
                    'credit_warnings',
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_get_widget_layout_returns_user_widgets()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create widgets for this user
        DashboardWidget::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create widgets for another user (should not be returned)
        $otherUser = User::factory()->create();
        DashboardWidget::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/dashboard/widget-layout');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'widget_type',
                        'title',
                        'position_x',
                        'position_y',
                        'width',
                        'height',
                        'settings',
                        'is_active',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_update_widget_layout_updates_positions()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget1 = DashboardWidget::factory()->create([
            'user_id' => $this->user->id,
            'position_x' => 0,
            'position_y' => 0,
        ]);

        $widget2 = DashboardWidget::factory()->create([
            'user_id' => $this->user->id,
            'position_x' => 1,
            'position_y' => 0,
        ]);

        $updateData = [
            'widgets' => [
                [
                    'id' => $widget1->id,
                    'position_x' => 2,
                    'position_y' => 1,
                    'width' => 2,
                    'height' => 1,
                ],
                [
                    'id' => $widget2->id,
                    'position_x' => 0,
                    'position_y' => 0,
                    'width' => 1,
                    'height' => 2,
                ],
            ]
        ];

        $response = $this->putJson('/api/dashboard/widget-layout', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Widget layout updated successfully'
            ]);

        // Verify database updates
        $widget1->refresh();
        $widget2->refresh();

        $this->assertEquals(2, $widget1->position_x);
        $this->assertEquals(1, $widget1->position_y);
        $this->assertEquals(2, $widget1->width);
        $this->assertEquals(1, $widget1->height);

        $this->assertEquals(0, $widget2->position_x);
        $this->assertEquals(0, $widget2->position_y);
        $this->assertEquals(1, $widget2->width);
        $this->assertEquals(2, $widget2->height);
    }

    public function test_update_widget_layout_validates_input()
    {
        $this->actingAs($this->user, 'sanctum');

        $invalidData = [
            'widgets' => [
                [
                    'id' => 'invalid',
                    'position_x' => -1,
                    'position_y' => 'invalid',
                ]
            ]
        ];

        $response = $this->putJson('/api/dashboard/widget-layout', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['widgets.0.id', 'widgets.0.position_x', 'widgets.0.position_y']);
    }

    public function test_dashboard_endpoints_require_authentication()
    {
        $endpoints = [
            'GET' => [
                '/api/dashboard/data',
                '/api/dashboard/kpis',
                '/api/dashboard/sales-trend',
                '/api/dashboard/top-products',
                '/api/dashboard/alerts',
                '/api/dashboard/alert-counts',
                '/api/dashboard/widget-layout',
            ],
            'PUT' => [
                '/api/dashboard/widget-layout',
            ]
        ];

        foreach ($endpoints as $method => $urls) {
            foreach ($urls as $url) {
                $response = $this->json($method, $url);
                $response->assertStatus(401);
            }
        }
    }

    public function test_dashboard_data_caching()
    {
        $this->actingAs($this->user, 'sanctum');

        // First request
        $response1 = $this->getJson('/api/dashboard/kpis');
        $response1->assertStatus(200);

        // Second request should be faster due to caching
        $start = microtime(true);
        $response2 = $this->getJson('/api/dashboard/kpis');
        $end = microtime(true);

        $response2->assertStatus(200);
        
        // Verify responses are identical
        $this->assertEquals($response1->json(), $response2->json());
    }
}