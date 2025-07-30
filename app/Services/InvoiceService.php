<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceService
{
    protected GoldPriceService $goldPriceService;

    public function __construct(GoldPriceService $goldPriceService)
    {
        $this->goldPriceService = $goldPriceService;
    }

    /**
     * Create a new invoice with items
     */
    public function createInvoice(array $invoiceData, array $items = []): Invoice
    {
        return DB::transaction(function () use ($invoiceData, $items) {
            // Generate invoice number if not provided
            if (!isset($invoiceData['invoice_number'])) {
                $invoiceData['invoice_number'] = Invoice::generateInvoiceNumber($invoiceData['type'] ?? 'sale');
            }

            // Set default values
            $invoiceData = array_merge([
                'invoice_date' => now()->toDateString(),
                'status' => 'draft',
                'currency' => 'IRR',
                'created_by' => auth()->id(),
                'gold_price_per_gram' => $this->goldPriceService->getCurrentGoldPrice(),
            ], $invoiceData);

            // Create the invoice
            $invoice = Invoice::create($invoiceData);

            // Add items if provided
            if (!empty($items)) {
                $this->addItemsToInvoice($invoice, $items);
            }

            // Calculate totals
            $invoice->recalculateTotals();

            Log::info('Invoice created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'total_amount' => $invoice->total_amount,
            ]);

            return $invoice->fresh(['items', 'customer', 'payments']);
        });
    }

    /**
     * Add items to an invoice
     */
    public function addItemsToInvoice(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            $this->addItemToInvoice($invoice, $itemData);
        }
    }

    /**
     * Add a single item to an invoice
     */
    public function addItemToInvoice(Invoice $invoice, array $itemData): InvoiceItem
    {
        // Get product information if product_id is provided
        if (isset($itemData['product_id']) && $itemData['product_id']) {
            $product = Product::find($itemData['product_id']);
            if ($product) {
                $itemData = array_merge([
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'gold_weight' => $product->gold_weight ?? 0,
                    'stone_weight' => $product->stone_weight ?? 0,
                    'unit_price' => $product->unit_price ?? 0,
                    'manufacturing_fee' => $product->manufacturing_cost ?? 0,
                ], $itemData);
            }
        }

        // Calculate gold pricing
        $goldPricePerGram = $itemData['gold_price_per_gram'] ?? $invoice->gold_price_per_gram;
        $goldWeight = $itemData['gold_weight'] ?? 0;
        $manufacturingFee = $itemData['manufacturing_fee'] ?? 0;
        $profitMarginPercentage = $itemData['profit_margin_percentage'] ?? $invoice->profit_margin_percentage;

        // Calculate unit price using gold pricing formula
        if (!isset($itemData['unit_price']) || $itemData['unit_price'] == 0) {
            $itemData['unit_price'] = $this->calculateGoldBasedPrice(
                $goldWeight,
                $goldPricePerGram,
                $manufacturingFee,
                $profitMarginPercentage,
                $invoice->vat_percentage
            );
        }

        // Calculate profit amount
        $baseCost = ($goldWeight * $goldPricePerGram) + $manufacturingFee;
        $itemData['profit_amount'] = $baseCost * ($profitMarginPercentage / 100);

        // Set defaults
        $itemData = array_merge([
            'quantity' => 1,
            'gold_price_per_gram' => $goldPricePerGram,
            'discount_percentage' => 0,
            'discount_amount' => 0,
        ], $itemData);

        // Calculate line total
        $baseTotal = $itemData['unit_price'] * $itemData['quantity'];
        $itemData['line_total'] = $baseTotal - $itemData['discount_amount'];

        return $invoice->items()->create($itemData);
    }

    /**
     * Calculate gold-based pricing
     * Formula: Gold Weight × (Daily Gold Price + Manufacturing Fee + Jeweler's Profit + VAT)
     */
    public function calculateGoldBasedPrice(
        float $goldWeight,
        float $goldPricePerGram,
        float $manufacturingFee,
        float $profitMarginPercentage,
        float $vatPercentage = 0
    ): float {
        // Base cost: gold + manufacturing
        $baseCost = ($goldWeight * $goldPricePerGram) + $manufacturingFee;
        
        // Add profit margin
        $profitAmount = $baseCost * ($profitMarginPercentage / 100);
        $priceBeforeVat = $baseCost + $profitAmount;
        
        // Add VAT
        $vatAmount = $priceBeforeVat * ($vatPercentage / 100);
        
        return $priceBeforeVat + $vatAmount;
    }

    /**
     * Update invoice item
     */
    public function updateInvoiceItem(InvoiceItem $item, array $data): InvoiceItem
    {
        return DB::transaction(function () use ($item, $data) {
            // Recalculate pricing if relevant fields changed
            if (isset($data['gold_weight']) || isset($data['gold_price_per_gram']) || 
                isset($data['manufacturing_fee']) || isset($data['quantity'])) {
                
                $goldWeight = $data['gold_weight'] ?? $item->gold_weight;
                $goldPricePerGram = $data['gold_price_per_gram'] ?? $item->gold_price_per_gram;
                $manufacturingFee = $data['manufacturing_fee'] ?? $item->manufacturing_fee;
                $quantity = $data['quantity'] ?? $item->quantity;

                // Recalculate profit amount
                $baseCost = ($goldWeight * $goldPricePerGram) + $manufacturingFee;
                $data['profit_amount'] = $baseCost * ($item->invoice->profit_margin_percentage / 100);

                // Recalculate line total
                $baseTotal = ($data['unit_price'] ?? $item->unit_price) * $quantity;
                $discountAmount = $data['discount_amount'] ?? $item->discount_amount;
                $data['line_total'] = $baseTotal - $discountAmount;
            }

            $item->update($data);
            $item->invoice->recalculateTotals();

            return $item->fresh();
        });
    }

    /**
     * Process split payment for an invoice
     */
    public function processSplitPayment(Invoice $invoice, array $payments): array
    {
        return DB::transaction(function () use ($invoice, $payments) {
            $processedPayments = [];
            $totalPaymentAmount = 0;

            foreach ($payments as $paymentData) {
                $payment = $this->processPayment($invoice, $paymentData);
                $processedPayments[] = $payment;
                
                if ($payment->status === 'completed') {
                    $totalPaymentAmount += $payment->amount;
                }
            }

            // Update invoice payment status
            $invoice->updatePaymentStatus();

            Log::info('Split payment processed', [
                'invoice_id' => $invoice->id,
                'total_payment_amount' => $totalPaymentAmount,
                'payment_count' => count($processedPayments),
            ]);

            return $processedPayments;
        });
    }

    /**
     * Process a single payment
     */
    public function processPayment(Invoice $invoice, array $paymentData): Payment
    {
        // Validate payment amount
        if ($paymentData['amount'] > $invoice->balance_due) {
            throw new \InvalidArgumentException('Payment amount cannot exceed balance due');
        }

        // Set defaults
        $paymentData = array_merge([
            'customer_id' => $invoice->customer_id,
            'payment_number' => Payment::generatePaymentNumber(),
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
            'processed_by' => auth()->id(),
        ], $paymentData);

        $payment = $invoice->payments()->create($paymentData);

        // Auto-complete cash payments
        if ($paymentData['payment_method'] === 'cash') {
            $payment->markAsCompleted();
        }

        // Handle cheque-specific data
        if ($paymentData['payment_method'] === 'cheque') {
            $this->processChequePayment($payment, $paymentData);
        }

        return $payment;
    }

    /**
     * Process cheque-specific payment details
     */
    private function processChequePayment(Payment $payment, array $paymentData): void
    {
        $chequeDetails = [
            'cheque_number' => $paymentData['reference_number'] ?? null,
            'bank_name' => $paymentData['bank_name'] ?? null,
            'cheque_date' => $paymentData['cheque_date'] ?? null,
            'account_holder' => $paymentData['account_holder'] ?? null,
        ];

        $payment->update([
            'payment_details' => array_merge($payment->payment_details ?? [], $chequeDetails),
            'status' => 'pending', // Cheques start as pending
        ]);
    }

    /**
     * Update invoice status
     */
    public function updateInvoiceStatus(Invoice $invoice, string $status): Invoice
    {
        $validStatuses = ['draft', 'pending', 'partial', 'paid', 'overdue', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $invoice->update(['status' => $status]);

        Log::info('Invoice status updated', [
            'invoice_id' => $invoice->id,
            'old_status' => $invoice->getOriginal('status'),
            'new_status' => $status,
        ]);

        return $invoice->fresh();
    }

    /**
     * Cancel an invoice
     */
    public function cancelInvoice(Invoice $invoice, string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            // Cannot cancel paid invoices
            if ($invoice->isPaid()) {
                throw new \InvalidArgumentException('Cannot cancel a paid invoice');
            }

            // Cancel pending payments
            $invoice->payments()->where('status', 'pending')->update([
                'status' => 'cancelled',
                'notes' => $reason ? "Cancelled: {$reason}" : 'Invoice cancelled',
            ]);

            // Update invoice status
            $invoice->update([
                'status' => 'cancelled',
                'notes' => $invoice->notes . ($reason ? "\nCancelled: {$reason}" : "\nInvoice cancelled"),
            ]);

            // Restore product stock for cancelled items
            foreach ($invoice->items as $item) {
                if ($item->product && $item->product->track_stock) {
                    $item->product->updateStock($item->quantity, 'add', "Invoice {$invoice->invoice_number} cancelled");
                }
            }

            Log::info('Invoice cancelled', [
                'invoice_id' => $invoice->id,
                'reason' => $reason,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Duplicate an invoice
     */
    public function duplicateInvoice(Invoice $invoice, array $overrides = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $overrides) {
            // Prepare invoice data
            $invoiceData = array_merge([
                'customer_id' => $invoice->customer_id,
                'type' => $invoice->type,
                'invoice_date' => now()->toDateString(),
                'due_date' => $invoice->due_date ? now()->addDays(30)->toDateString() : null,
                'gold_price_per_gram' => $this->goldPriceService->getCurrentGoldPrice(),
                'manufacturing_fee' => $invoice->manufacturing_fee,
                'profit_margin_percentage' => $invoice->profit_margin_percentage,
                'vat_percentage' => $invoice->vat_percentage,
                'currency' => $invoice->currency,
                'notes' => $invoice->notes,
                'terms_conditions' => $invoice->terms_conditions,
                'custom_fields' => $invoice->custom_fields,
            ], $overrides);

            // Create new invoice
            $newInvoice = $this->createInvoice($invoiceData);

            // Copy items
            foreach ($invoice->items as $item) {
                $itemData = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'gold_weight' => $item->gold_weight,
                    'stone_weight' => $item->stone_weight,
                    'unit_price' => $item->unit_price,
                    'manufacturing_fee' => $item->manufacturing_fee,
                    'custom_attributes' => $item->custom_attributes,
                ];

                $this->addItemToInvoice($newInvoice, $itemData);
            }

            return $newInvoice->fresh(['items', 'customer']);
        });
    }

    /**
     * Get invoice statistics
     */
    public function getInvoiceStatistics(array $filters = []): array
    {
        $query = Invoice::query();

        // Apply filters
        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'paid_amount' => $query->sum('paid_amount'),
            'outstanding_amount' => $query->sum('balance_due'),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count, sum(total_amount) as amount')
                ->get()
                ->keyBy('status'),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, count(*) as count, sum(total_amount) as amount')
                ->get()
                ->keyBy('type'),
        ];
    }
}