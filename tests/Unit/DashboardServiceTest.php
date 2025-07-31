<?php

namespace Tests\Unit;

use Tests\TenantTestCase;
use App\Services\DashboardService;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\DashboardWidget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardServiceTest extends TenantTestCase
{
    use RefreshDatabase;

    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardService = new DashboardService();
    }

    public function test_get_sales_kpi_calculates_correctly()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['unit_price' => 100]);
        
        // Create invoices for current period
        $currentInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000,
            'status' => 'paid',
            'created_at' => Carbon::now(),
        ]);
        
        // Create invoice items
        InvoiceItem::factory()->create([
            'invoice_id' => $currentInvoice->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        // Create invoices for previous period
        $previousInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 800,
            'status' => 'paid',
            'created_at' => Carbon::yesterday(),
        ]);

        $kpis = $this->dashboardService->getKPIs('today');

        $this->assertArrayHasKey('sales', $kpis);
        $this->assertEquals(1000, $kpis['sales']['value']);
        $this->assertEquals(800, $kpis['sales']['previous_value']);
        $this->assertEquals(25, $kpis['sales']['change_percentage']); // (1000-800)/800 * 100
        $this->assertEquals('up', $kpis['sales']['trend']);
        $this->assertEquals(1, $kpis['sales']['count']);
    }

    public function test_get_customers_kpi_calculates_correctly()
    {
        // Create customers for current period
        Customer::factory()->count(3)->create([
            'created_at' => Carbon::now(),
        ]);

        // Create customers for previous period
        Customer::factory()->count(2)->create([
            'created_at' => Carbon::yesterday(),
        ]);

        $kpis = $this->dashboardService->getKPIs('today');

        $this->assertArrayHasKey('customers', $kpis);
        $this->assertEquals(3, $kpis['customers']['new_customers']);
        $this->assertEquals(5, $kpis['customers']['total_customers']);
        $this->assertEquals(50, $kpis['customers']['change_percentage']); // (3-2)/2 * 100
        $this->assertEquals('up', $kpis['customers']['trend']);
    }

    public function test_get_gold_metrics_calculates_correctly()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'gold_weight' => 5.5, // 5.5 grams per unit
            'unit_price' => 100,
        ]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'created_at' => Carbon::now(),
        ]);
        
        // Create invoice item with 2 units = 11 grams total
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $kpis = $this->dashboardService->getKPIs('today');

        $this->assertArrayHasKey('gold_metrics', $kpis);
        $this->assertEquals(11, $kpis['gold_metrics']['gold_sold_grams']); // 2 * 5.5
    }

    public function test_get_sales_trend_returns_correct_format()
    {
        $customer = Customer::factory()->create();
        
        // Create invoices for different days
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000,
            'status' => 'paid',
            'created_at' => Carbon::now()->subDays(2),
        ]);
        
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1500,
            'status' => 'paid',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $trend = $this->dashboardService->getSalesTrend('7_days');

        $this->assertIsArray($trend->toArray());
        $this->assertNotEmpty($trend);
        
        $firstItem = $trend->first();
        $this->assertArrayHasKey('date', $firstItem);
        $this->assertArrayHasKey('total', $firstItem);
        $this->assertArrayHasKey('count', $firstItem);
    }

    public function test_get_top_products_returns_correct_data()
    {
        $customer = Customer::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Gold Ring', 'sku' => 'GR001']);
        $product2 = Product::factory()->create(['name' => 'Gold Necklace', 'sku' => 'GN001']);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
        ]);
        
        // Product 1: 5 units at 200 each = 1000 revenue
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'unit_price' => 200,
        ]);
        
        // Product 2: 3 units at 300 each = 900 revenue
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 300,
        ]);

        $topProducts = $this->dashboardService->getTopProducts(10);

        $this->assertCount(2, $topProducts);
        
        // Should be ordered by revenue (highest first)
        $this->assertEquals('Gold Ring', $topProducts[0]->name);
        $this->assertEquals(1000, $topProducts[0]->total_revenue);
        $this->assertEquals(5, $topProducts[0]->total_sold);
        
        $this->assertEquals('Gold Necklace', $topProducts[1]->name);
        $this->assertEquals(900, $topProducts[1]->total_revenue);
        $this->assertEquals(3, $topProducts[1]->total_sold);
    }

    public function test_widget_layout_management()
    {
        $user = User::factory()->create();
        
        // Create some widgets
        $widget1 = DashboardWidget::factory()->create([
            'user_id' => $user->id,
            'position_x' => 0,
            'position_y' => 0,
            'width' => 2,
            'height' => 1,
        ]);
        
        $widget2 = DashboardWidget::factory()->create([
            'user_id' => $user->id,
            'position_x' => 2,
            'position_y' => 0,
            'width' => 1,
            'height' => 2,
        ]);

        // Test getting layout
        $layout = $this->dashboardService->getWidgetLayout($user->id);
        $this->assertCount(2, $layout);

        // Test updating layout
        $newLayout = [
            [
                'id' => $widget1->id,
                'position_x' => 1,
                'position_y' => 1,
                'width' => 3,
                'height' => 2,
            ],
            [
                'id' => $widget2->id,
                'position_x' => 0,
                'position_y' => 0,
                'width' => 1,
                'height' => 1,
            ],
        ];

        $result = $this->dashboardService->updateWidgetLayout($user->id, $newLayout);
        $this->assertTrue($result);

        // Verify updates
        $widget1->refresh();
        $widget2->refresh();
        
        $this->assertEquals(1, $widget1->position_x);
        $this->assertEquals(1, $widget1->position_y);
        $this->assertEquals(3, $widget1->width);
        $this->assertEquals(2, $widget1->height);
        
        $this->assertEquals(0, $widget2->position_x);
        $this->assertEquals(0, $widget2->position_y);
        $this->assertEquals(1, $widget2->width);
        $this->assertEquals(1, $widget2->height);
    }

    public function test_inventory_value_calculation()
    {
        Product::factory()->create([
            'current_stock' => 10,
            'unit_price' => 100,
        ]);
        
        Product::factory()->create([
            'current_stock' => 5,
            'unit_price' => 200,
        ]);

        $inventoryValue = $this->dashboardService->getInventoryValue();
        
        $this->assertEquals(2000, $inventoryValue); // (10 * 100) + (5 * 200)
    }

    public function test_pending_payments_calculation()
    {
        $customer = Customer::factory()->create();
        
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 1500,
        ]);
        
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 2500,
        ]);
        
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'total_amount' => 1000,
        ]);

        $pendingPayments = $this->dashboardService->getPendingPayments();
        
        $this->assertEquals(4000, $pendingPayments); // 1500 + 2500 (paid invoice excluded)
    }
}