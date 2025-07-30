<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\InvoiceService;
use App\Services\GoldPriceService;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;
    protected GoldPriceService $goldPriceService;
    protected User $user;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->goldPriceService = $this->app->make(GoldPriceService::class);
        $this->invoiceService = new InvoiceService($this->goldPriceService);
        
        // Create test user
        $this->user = User::factory()->create();
        Auth::login($this->user);
        
        // Create test customer
        $this->customer = Customer::factory()->create();
        
        // Create test product
        $this->product = Product::factory()->create([
            'name' => 'Gold Ring',
            'sku' => 'GR001',
            'gold_weight' => 5.5,
            'stone_weight' => 0.5,
            'unit_price' => 15000000,
            'manufacturing_cost' => 500000,
        ]);
    }

    public function test_can_create_invoice_with_items()
    {
        $invoiceData = [
            'customer_id' => $this->customer->id,
            'type' => 'sale',
            'invoice_date' => now()->toDateString(),
            'profit_margin_percentage' => 20,
            'vat_percentage' => 9,
        ];

        $items = [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'gold_weight' => 5.5,
                'manufacturing_fee' => 500000,
            ]
        ];

        $invoice = $this->invoiceService->createInvoice($invoiceData, $items);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals('sale', $invoice->type);
        $this->assertEquals(1, $invoice->items->count());
        $this->assertGreaterThan(0, $invoice->total_amount);
    }

    public function test_can_calculate_gold_based_price()
    {
        $goldWeight = 5.0;
        $goldPricePerGram = 2500000;
        $manufacturingFee = 500000;
        $profitMarginPercentage = 20;
        $vatPercentage = 9;

        $price = $this->invoiceService->calculateGoldBasedPrice(
            $goldWeight,
            $goldPricePerGram,
            $manufacturingFee,
            $profitMarginPercentage,
            $vatPercentage
        );

        // Expected calculation:
        // Base cost: (5 * 2,500,000) + 500,000 = 13,000,000
        // Profit: 13,000,000 * 0.20 = 2,600,000
        // Before VAT: 13,000,000 + 2,600,000 = 15,600,000
        // VAT: 15,600,000 * 0.09 = 1,404,000
        // Final: 15,600,000 + 1,404,000 = 17,004,000

        $this->assertEquals(17004000, $price);
    }

    public function test_can_process_split_payment()
    {
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);
        
        // Update the invoice with specific amounts
        $invoice->update([
            'total_amount' => 10000000,
            'balance_due' => 10000000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $payments = [
            [
                'payment_method' => 'cash',
                'amount' => 6000000,
            ],
            [
                'payment_method' => 'cash', // Change to cash so it auto-completes
                'amount' => 4000000,
            ]
        ];

        $processedPayments = $this->invoiceService->processSplitPayment($invoice, $payments);

        $this->assertCount(2, $processedPayments);
        $this->assertEquals(6000000, $processedPayments[0]->amount);
        $this->assertEquals(4000000, $processedPayments[1]->amount);
        
        $invoice->refresh();
        $this->assertEquals(10000000, $invoice->paid_amount);
        $this->assertLessThanOrEqual(0, $invoice->balance_due);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_can_add_item_to_existing_invoice()
    {
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'gold_weight' => 3.0,
            'manufacturing_fee' => 300000,
        ];

        $item = $this->invoiceService->addItemToInvoice($invoice, $itemData);

        $this->assertEquals($invoice->id, $item->invoice_id);
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(3.0, $item->gold_weight);
    }

    public function test_can_cancel_invoice()
    {
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);
        
        // Ensure invoice is not paid
        $invoice->update([
            'status' => 'pending',
            'paid_amount' => 0,
            'balance_due' => 1000000, // Ensure there's a positive balance
        ]);

        $cancelledInvoice = $this->invoiceService->cancelInvoice($invoice, 'Customer request');

        $this->assertEquals('cancelled', $cancelledInvoice->status);
        $this->assertStringContainsString('Customer request', $cancelledInvoice->notes);
    }

    public function test_cannot_cancel_paid_invoice()
    {
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
            'status' => 'paid',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel a paid invoice');

        $this->invoiceService->cancelInvoice($invoice);
    }

    public function test_can_duplicate_invoice()
    {
        $originalInvoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
            'notes' => 'Original invoice',
        ], [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'gold_weight' => 5.0,
            ]
        ]);

        $duplicatedInvoice = $this->invoiceService->duplicateInvoice($originalInvoice);

        $this->assertNotEquals($originalInvoice->id, $duplicatedInvoice->id);
        $this->assertNotEquals($originalInvoice->invoice_number, $duplicatedInvoice->invoice_number);
        $this->assertEquals($originalInvoice->customer_id, $duplicatedInvoice->customer_id);
        $this->assertEquals($originalInvoice->type, $duplicatedInvoice->type);
        $this->assertEquals($originalInvoice->items->count(), $duplicatedInvoice->items->count());
    }

    public function test_can_get_invoice_statistics()
    {
        // Create multiple invoices
        $invoice1 = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);
        $invoice1->update([
            'total_amount' => 5000000,
            'status' => 'paid',
        ]);

        $invoice2 = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'purchase',
        ]);
        $invoice2->update([
            'total_amount' => 3000000,
            'status' => 'pending',
        ]);

        $statistics = $this->invoiceService->getInvoiceStatistics();

        $this->assertEquals(2, $statistics['total_invoices']);
        $this->assertEquals(8000000, $statistics['total_amount']);
        $this->assertArrayHasKey('by_status', $statistics);
        $this->assertArrayHasKey('by_type', $statistics);
    }

    public function test_validates_payment_amount_against_balance_due()
    {
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);
        
        $invoice->update([
            'total_amount' => 5000000,
            'balance_due' => 5000000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount cannot exceed balance due');

        $this->invoiceService->processPayment($invoice, [
            'payment_method' => 'cash',
            'amount' => 6000000, // More than balance due
        ]);
    }
}
