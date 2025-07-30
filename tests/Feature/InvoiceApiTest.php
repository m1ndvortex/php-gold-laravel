<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Gold Ring',
            'sku' => 'GR001',
            'gold_weight' => 5.5,
            'unit_price' => 15000000,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_invoice_via_api()
    {
        $invoiceData = [
            'customer_id' => $this->customer->id,
            'type' => 'sale',
            'invoice_date' => now()->toDateString(),
            'profit_margin_percentage' => 20,
            'vat_percentage' => 9,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => 'Gold Ring',
                    'quantity' => 1,
                    'gold_weight' => 5.5,
                    'manufacturing_fee' => 500000,
                ]
            ]
        ];

        $response = $this->postJson('/api/invoices', $invoiceData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Invoice created successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'invoice_number',
                        'customer_id',
                        'type',
                        'status',
                        'total_amount',
                        'items',
                        'customer',
                    ]
                ]);
    }

    public function test_can_list_invoices_with_filters()
    {
        // Create test invoices
        Invoice::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
        ]);

        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'type' => 'purchase',
        ]);

        $response = $this->getJson('/api/invoices?type=sale');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'invoice_number',
                                'customer_id',
                                'type',
                                'status',
                                'total_amount',
                            ]
                        ]
                    ]
                ]);

        $this->assertEquals(3, count($response->json('data.data')));
    }

    public function test_can_show_specific_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ]
                ]);
    }

    public function test_can_update_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $updateData = [
            'notes' => 'Updated notes',
            'profit_margin_percentage' => 25,
        ];

        $response = $this->putJson("/api/invoices/{$invoice->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Invoice updated successfully',
                ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'notes' => 'Updated notes',
            'profit_margin_percentage' => 25,
        ]);
    }

    public function test_cannot_update_paid_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'paid',
        ]);

        $response = $this->putJson("/api/invoices/{$invoice->id}", [
            'notes' => 'Should not update',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot update a paid invoice',
                ]);
    }

    public function test_can_add_item_to_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'product_name' => 'Gold Necklace',
            'quantity' => 1,
            'gold_weight' => 10.0,
            'manufacturing_fee' => 800000,
        ];

        $response = $this->postJson("/api/invoices/{$invoice->id}/items", $itemData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Item added successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'product_name',
                        'quantity',
                        'gold_weight',
                        'line_total',
                    ]
                ]);
    }

    public function test_can_process_split_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 10000000,
            'balance_due' => 10000000,
            'status' => 'pending',
        ]);

        $paymentData = [
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 6000000,
                ],
                [
                    'payment_method' => 'card',
                    'amount' => 4000000,
                ]
            ]
        ];

        $response = $this->postJson("/api/invoices/{$invoice->id}/payments", $paymentData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'payments' => [
                            '*' => [
                                'id',
                                'payment_method',
                                'amount',
                                'status',
                            ]
                        ],
                        'invoice' => [
                            'id',
                            'status',
                            'balance_due',
                        ]
                    ]
                ]);
    }

    public function test_can_cancel_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/cancel", [
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Invoice cancelled successfully',
                ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_duplicate_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/duplicate");

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Invoice duplicated successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'invoice_number',
                        'customer_id',
                    ]
                ]);

        // Verify the duplicated invoice has a different ID and invoice number
        $duplicatedInvoice = $response->json('data');
        $this->assertNotEquals($invoice->id, $duplicatedInvoice['id']);
        $this->assertNotEquals($invoice->invoice_number, $duplicatedInvoice['invoice_number']);
    }

    public function test_can_get_invoice_statistics()
    {
        // Create test invoices
        Invoice::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'type' => 'sale',
            'total_amount' => 5000000,
            'status' => 'paid',
        ]);

        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'type' => 'purchase',
            'total_amount' => 3000000,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/invoices/statistics');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'total_invoices',
                        'total_amount',
                        'paid_amount',
                        'outstanding_amount',
                        'by_status',
                        'by_type',
                    ]
                ]);
    }

    public function test_validates_invoice_creation_data()
    {
        $invalidData = [
            'customer_id' => 999, // Non-existent customer
            'type' => 'invalid_type',
            'items' => [], // Empty items array
        ];

        $response = $this->postJson('/api/invoices', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id', 'type', 'items']);
    }

    public function test_validates_payment_amount_does_not_exceed_balance()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 5000000,
            'balance_due' => 5000000,
        ]);

        $paymentData = [
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 6000000, // Exceeds balance due
                ]
            ]
        ];

        $response = $this->postJson("/api/invoices/{$invoice->id}/payments", $paymentData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Total payment amount cannot exceed balance due',
                ]);
    }

    public function test_requires_authentication()
    {
        // Remove authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/invoices');

        $response->assertStatus(401);
    }
}
