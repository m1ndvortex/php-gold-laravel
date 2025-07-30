<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;
    protected InvoicePdfService $pdfService;

    public function __construct(InvoiceService $invoiceService, InvoicePdfService $pdfService)
    {
        $this->invoiceService = $invoiceService;
        $this->pdfService = $pdfService;
    }

    /**
     * Display a listing of invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'items', 'payments'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', Rule::in(['sale', 'purchase', 'trade'])],
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'gold_price_per_gram' => 'nullable|numeric|min:0',
            'manufacturing_fee' => 'nullable|numeric|min:0',
            'profit_margin_percentage' => 'nullable|numeric|min:0|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
            'terms_conditions' => 'nullable|string|max:2000',
            'custom_fields' => 'nullable|array',
            'is_recurring' => 'nullable|boolean',
            'recurring_pattern' => 'nullable|in:monthly,quarterly,yearly',
            'next_recurring_date' => 'nullable|date|after:invoice_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.gold_weight' => 'nullable|numeric|min:0',
            'items.*.stone_weight' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.manufacturing_fee' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.custom_attributes' => 'nullable|array',
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice(
                $validated,
                $validated['items']
            );

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'data' => $invoice->load(['customer', 'items.product', 'payments']),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'items.product', 'payments', 'creator']);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        // Prevent updating paid invoices
        if ($invoice->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a paid invoice',
            ], 422);
        }

        $validated = $request->validate([
            'customer_id' => 'sometimes|exists:customers,id',
            'type' => ['sometimes', Rule::in(['sale', 'purchase', 'trade'])],
            'invoice_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'gold_price_per_gram' => 'nullable|numeric|min:0',
            'manufacturing_fee' => 'nullable|numeric|min:0',
            'profit_margin_percentage' => 'nullable|numeric|min:0|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
            'terms_conditions' => 'nullable|string|max:2000',
            'custom_fields' => 'nullable|array',
            'is_recurring' => 'nullable|boolean',
            'recurring_pattern' => 'nullable|in:monthly,quarterly,yearly',
            'next_recurring_date' => 'nullable|date|after:invoice_date',
        ]);

        try {
            $invoice->update($validated);
            $invoice->recalculateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'data' => $invoice->fresh(['customer', 'items.product', 'payments']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        // Prevent deleting paid invoices
        if ($invoice->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a paid invoice',
            ], 422);
        }

        try {
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add item to invoice
     */
    public function addItem(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add items to a paid invoice',
            ], 422);
        }

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_name' => 'required|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'quantity' => 'required|numeric|min:0.001',
            'gold_weight' => 'nullable|numeric|min:0',
            'stone_weight' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'manufacturing_fee' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'custom_attributes' => 'nullable|array',
        ]);

        try {
            $item = $this->invoiceService->addItemToInvoice($invoice, $validated);
            $invoice->recalculateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item added successfully',
                'data' => $item->load('product'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to add item to invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice item
     */
    public function updateItem(Request $request, Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        if ($invoice->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update items in a paid invoice',
            ], 422);
        }

        if ($item->invoice_id !== $invoice->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item does not belong to this invoice',
            ], 422);
        }

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'quantity' => 'sometimes|numeric|min:0.001',
            'gold_weight' => 'nullable|numeric|min:0',
            'stone_weight' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'manufacturing_fee' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'custom_attributes' => 'nullable|array',
        ]);

        try {
            $updatedItem = $this->invoiceService->updateInvoiceItem($item, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => $updatedItem->load('product'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update invoice item', [
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove item from invoice
     */
    public function removeItem(Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        if ($invoice->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove items from a paid invoice',
            ], 422);
        }

        if ($item->invoice_id !== $invoice->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item does not belong to this invoice',
            ], 422);
        }

        try {
            $item->delete();
            $invoice->recalculateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove invoice item', [
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment for invoice
     */
    public function processPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => ['required', Rule::in(['cash', 'card', 'cheque', 'credit', 'bank_transfer'])],
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_date' => 'nullable|date',
            'payments.*.reference_number' => 'nullable|string|max:100',
            'payments.*.bank_name' => 'nullable|string|max:100',
            'payments.*.cheque_date' => 'nullable|date',
            'payments.*.notes' => 'nullable|string|max:500',
            'payments.*.payment_details' => 'nullable|array',
        ]);

        // Validate total payment amount
        $totalPaymentAmount = array_sum(array_column($validated['payments'], 'amount'));
        if ($totalPaymentAmount > $invoice->balance_due) {
            return response()->json([
                'success' => false,
                'message' => 'Total payment amount cannot exceed balance due',
            ], 422);
        }

        try {
            $payments = $this->invoiceService->processSplitPayment($invoice, $validated['payments']);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payments' => $payments,
                    'invoice' => $invoice->fresh(['customer', 'payments']),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process payment', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending', 'partial', 'paid', 'overdue', 'cancelled'])],
        ]);

        try {
            $updatedInvoice = $this->invoiceService->updateInvoiceStatus($invoice, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice status updated successfully',
                'data' => $updatedInvoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $cancelledInvoice = $this->invoiceService->cancelInvoice($invoice, $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Invoice cancelled successfully',
                'data' => $cancelledInvoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate invoice
     */
    public function duplicate(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        try {
            $duplicatedInvoice = $this->invoiceService->duplicateInvoice($invoice, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Invoice duplicated successfully',
                'data' => $duplicatedInvoice,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to duplicate invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate PDF for invoice
     */
    public function generatePdf(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'format' => 'nullable|in:A4,A5,letter',
            'orientation' => 'nullable|in:portrait,landscape',
            'language' => 'nullable|in:fa,en',
            'action' => 'nullable|in:download,stream,save',
        ]);

        $options = array_merge([
            'format' => 'A4',
            'orientation' => 'portrait',
            'language' => 'fa',
        ], $validated);

        try {
            $action = $validated['action'] ?? 'stream';

            switch ($action) {
                case 'download':
                    return $this->pdfService->downloadPdf($invoice, $options);
                case 'save':
                    $filename = $this->pdfService->savePdf($invoice, $options);
                    return response()->json([
                        'success' => true,
                        'message' => 'PDF saved successfully',
                        'data' => ['filename' => $filename],
                    ]);
                default:
                    return $this->pdfService->streamPdf($invoice, $options);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'type' => 'nullable|in:sale,purchase,trade',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        try {
            $statistics = $this->invoiceService->getInvoiceStatistics($validated);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice statistics', [
                'error' => $e->getMessage(),
                'filters' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}
