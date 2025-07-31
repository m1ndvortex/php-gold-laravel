<?php

namespace Tests\Unit;

use Tests\TenantTestCase;
use App\Services\AlertService;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\ProductCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AlertServiceTest extends TenantTestCase
{
    use RefreshDatabase;

    private AlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alertService = new AlertService();
    }

    public function test_get_overdue_invoices_returns_correct_data()
    {
        $customer = Customer::factory()->create(['name' => 'Test Customer']);
        
        // Create overdue invoice
        $overdueInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(10),
            'total_amount' => 1500,
        ]);
        
        // Create current invoice (not overdue)
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(5),
            'total_amount' => 1000,
        ]);

        $alerts = $this->alertService->getAllAlerts();
        $overdueAlerts = $alerts['overdue_invoices'];

        $this->assertEquals(1, $overdueAlerts['count']);
        $this->assertEquals(1500, $overdueAlerts['total_amount']);
        $this->assertEquals(0, $overdueAlerts['critical_count']); // Not 30+ days overdue
        
        $this->assertCount(1, $overdueAlerts['items']);
        $firstItem = $overdueAlerts['items'][0];
        $this->assertEquals('INV-001', $firstItem['invoice_number']);
        $this->assertEquals('Test Customer', $firstItem['customer_name']);
        $this->assertEquals(1500, $firstItem['amount']);
        $this->assertEquals(10, $firstItem['days_overdue']);
        $this->assertEquals('medium', $firstItem['severity']);
    }

    public function test_get_cheques_due_returns_correct_data()
    {
        $customer = Customer::factory()->create(['name' => 'Test Customer']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
        
        // Create cheque due tomorrow
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'cheque',
            'status' => 'pending',
            'cheque_number' => 'CHQ-001',
            'cheque_due_date' => Carbon::now()->addDay(),
            'amount' => 2000,
        ]);

        $alerts = $this->alertService->getAllAlerts();
        $chequesAlerts = $alerts['cheques_due'];

        $this->assertEquals(1, $chequesAlerts['count']);
        $this->assertEquals(2000, $chequesAlerts['total_amount']);
        $this->assertEquals(0, $chequesAlerts['due_today']);
        
        $this->assertCount(1, $chequesAlerts['items']);
        $firstItem = $chequesAlerts['items'][0];
        $this->assertEquals('CHQ-001', $firstItem['cheque_number']);
        $this->assertEquals('Test Customer', $firstItem['customer_name']);
        $this->assertEquals(2000, $firstItem['amount']);
        $this->assertEquals(1, $firstItem['days_until_due']);
        $this->assertEquals('high', $firstItem['severity']);
    }

    public function test_get_low_inventory_alerts_returns_correct_data()
    {
        $category = ProductCategory::factory()->create(['name' => 'Gold Rings']);
        
        // Create low stock product
        $lowStockProduct = Product::factory()->create([
            'name' => 'Gold Ring 18K',
            'sku' => 'GR18K-001',
            'category_id' => $category->id,
            'current_stock' => 2,
            'minimum_stock' => 10,
        ]);
        
        // Create out of stock product
        $outOfStockProduct = Product::factory()->create([
            'name' => 'Gold Necklace',
            'sku' => 'GN-001',
            'category_id' => $category->id,
            'current_stock' => 0,
            'minimum_stock' => 5,
        ]);
        
        // Create normal stock product
        Product::factory()->create([
            'current_stock' => 20,
            'minimum_stock' => 10,
        ]);

        $alerts = $this->alertService->getAllAlerts();
        $inventoryAlerts = $alerts['low_inventory'];

        $this->assertEquals(2, $inventoryAlerts['count']);
        $this->assertEquals(1, $inventoryAlerts['out_of_stock_count']);
        
        $this->assertCount(2, $inventoryAlerts['items']);
        
        // Check out of stock item (should be first due to ordering by current_stock)
        $outOfStockItem = collect($inventoryAlerts['items'])->firstWhere('sku', 'GN-001');
        $this->assertEquals('Gold Necklace', $outOfStockItem['name']);
        $this->assertEquals(0, $outOfStockItem['current_stock']);
        $this->assertEquals(5, $outOfStockItem['minimum_stock']);
        $this->assertEquals('critical', $outOfStockItem['severity']);
        
        // Check low stock item
        $lowStockItem = collect($inventoryAlerts['items'])->firstWhere('sku', 'GR18K-001');
        $this->assertEquals('Gold Ring 18K', $lowStockItem['name']);
        $this->assertEquals(2, $lowStockItem['current_stock']);
        $this->assertEquals(10, $lowStockItem['minimum_stock']);
        $this->assertEquals(20, $lowStockItem['stock_percentage']);
        $this->assertEquals('high', $lowStockItem['severity']);
    }

    public function test_get_credit_limit_warnings_returns_correct_data()
    {
        // Create customer with credit limit
        $customer = Customer::factory()->create([
            'name' => 'High Credit Customer',
            'credit_limit' => 10000,
        ]);
        
        // Create pending invoices that use 85% of credit limit
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 5000,
        ]);
        
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 3500,
        ]);
        
        // Create paid invoice (should not count towards credit usage)
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'total_amount' => 2000,
        ]);

        $alerts = $this->alertService->getAllAlerts();
        $creditAlerts = $alerts['credit_limit_warnings'];

        $this->assertEquals(1, $creditAlerts['count']);
        
        $this->assertCount(1, $creditAlerts['items']);
        $firstItem = $creditAlerts['items'][0];
        $this->assertEquals('High Credit Customer', $firstItem['name']);
        $this->assertEquals(10000, $firstItem['credit_limit']);
        $this->assertEquals(8500, $firstItem['used_amount']); // 5000 + 3500
        $this->assertEquals(85, $firstItem['used_percentage']);
        $this->assertEquals(1500, $firstItem['available_credit']);
        $this->assertEquals('medium', $firstItem['severity']); // 80-90% range
    }

    public function test_get_high_value_transactions_returns_correct_data()
    {
        $customer = Customer::factory()->create(['name' => 'VIP Customer']);
        
        // Create high value invoice (above 50000 threshold)
        $highValueInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-HIGH-001',
            'total_amount' => 75000,
            'type' => 'sale',
            'created_at' => Carbon::now()->subDays(2),
        ]);
        
        // Create normal value invoice
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 25000,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $alerts = $this->alertService->getAllAlerts();
        $highValueAlerts = $alerts['high_value_transactions'];

        $this->assertEquals(1, $highValueAlerts['count']);
        $this->assertEquals(75000, $highValueAlerts['total_amount']);
        
        $this->assertCount(1, $highValueAlerts['items']);
        $firstItem = $highValueAlerts['items'][0];
        $this->assertEquals('INV-HIGH-001', $firstItem['invoice_number']);
        $this->assertEquals('VIP Customer', $firstItem['customer_name']);
        $this->assertEquals(75000, $firstItem['amount']);
        $this->assertEquals('sale', $firstItem['type']);
    }

    public function test_get_alert_counts_returns_summary()
    {
        $customer = Customer::factory()->create();
        
        // Create overdue invoice
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(5),
        ]);
        
        // Create low stock product
        Product::factory()->create([
            'current_stock' => 1,
            'minimum_stock' => 10,
        ]);

        $alertCounts = $this->alertService->getAlertCounts();

        $this->assertArrayHasKey('overdue_invoices', $alertCounts);
        $this->assertArrayHasKey('cheques_due', $alertCounts);
        $this->assertArrayHasKey('low_inventory', $alertCounts);
        $this->assertArrayHasKey('credit_warnings', $alertCounts);
        
        $this->assertEquals(1, $alertCounts['overdue_invoices']);
        $this->assertEquals(0, $alertCounts['cheques_due']);
        $this->assertEquals(1, $alertCounts['low_inventory']);
        $this->assertEquals(0, $alertCounts['credit_warnings']);
    }

    public function test_severity_calculations()
    {
        // Test overdue severity
        $this->assertEquals('critical', $this->callPrivateMethod('getOverdueSeverity', Carbon::now()->subDays(35)));
        $this->assertEquals('high', $this->callPrivateMethod('getOverdueSeverity', Carbon::now()->subDays(20)));
        $this->assertEquals('medium', $this->callPrivateMethod('getOverdueSeverity', Carbon::now()->subDays(10)));
        $this->assertEquals('low', $this->callPrivateMethod('getOverdueSeverity', Carbon::now()->subDays(3)));
        
        // Test cheque severity
        $this->assertEquals('critical', $this->callPrivateMethod('getChequeSeverity', Carbon::now()->subDay()));
        $this->assertEquals('high', $this->callPrivateMethod('getChequeSeverity', Carbon::now()->addDay()));
        $this->assertEquals('medium', $this->callPrivateMethod('getChequeSeverity', Carbon::now()->addDays(2)));
        $this->assertEquals('low', $this->callPrivateMethod('getChequeSeverity', Carbon::now()->addDays(5)));
        
        // Test stock severity
        $this->assertEquals('critical', $this->callPrivateMethod('getStockSeverity', 0, 10));
        $this->assertEquals('high', $this->callPrivateMethod('getStockSeverity', 2, 10)); // 20%
        $this->assertEquals('medium', $this->callPrivateMethod('getStockSeverity', 4, 10)); // 40%
        $this->assertEquals('low', $this->callPrivateMethod('getStockSeverity', 8, 10)); // 80%
    }

    private function callPrivateMethod(string $methodName, ...$args)
    {
        $reflection = new \ReflectionClass($this->alertService);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->alertService, $args);
    }
}