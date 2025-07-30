<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class InvoicePdfService
{
    /**
     * Generate PDF for an invoice
     */
    public function generatePdf(Invoice $invoice, array $options = []): string
    {
        $options = array_merge([
            'format' => 'A4',
            'orientation' => 'portrait',
            'language' => 'fa',
            'include_branding' => true,
            'include_terms' => true,
        ], $options);

        // Load invoice with relationships
        $invoice->load(['customer', 'items.product', 'payments', 'creator']);

        // Prepare data for the view
        $data = [
            'invoice' => $invoice,
            'options' => $options,
            'tenant' => app('tenant'),
            'totals' => $this->calculateInvoiceTotals($invoice),
            'payment_summary' => $this->getPaymentSummary($invoice),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('invoices.pdf', $data)
            ->setPaper($options['format'], $options['orientation'])
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
                'defaultPaperSize' => $options['format'],
            ]);

        return $pdf->output();
    }

    /**
     * Save PDF to storage
     */
    public function savePdf(Invoice $invoice, array $options = []): string
    {
        $pdfContent = $this->generatePdf($invoice, $options);
        
        $filename = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($filename, $pdfContent);
        
        return $filename;
    }

    /**
     * Download PDF
     */
    public function downloadPdf(Invoice $invoice, array $options = []): \Illuminate\Http\Response
    {
        $filename = "{$invoice->invoice_number}.pdf";
        
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice->load(['customer', 'items.product', 'payments', 'creator']),
            'options' => $options,
            'tenant' => app('tenant'),
            'totals' => $this->calculateInvoiceTotals($invoice),
            'payment_summary' => $this->getPaymentSummary($invoice),
        ])
        ->setPaper($options['format'] ?? 'A4', $options['orientation'] ?? 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Stream PDF to browser
     */
    public function streamPdf(Invoice $invoice, array $options = []): \Illuminate\Http\Response
    {
        $filename = "{$invoice->invoice_number}.pdf";
        
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice->load(['customer', 'items.product', 'payments', 'creator']),
            'options' => $options,
            'tenant' => app('tenant'),
            'totals' => $this->calculateInvoiceTotals($invoice),
            'payment_summary' => $this->getPaymentSummary($invoice),
        ])
        ->setPaper($options['format'] ?? 'A4', $options['orientation'] ?? 'portrait');

        return $pdf->stream($filename);
    }

    /**
     * Calculate detailed invoice totals
     */
    protected function calculateInvoiceTotals(Invoice $invoice): array
    {
        $subtotal = $invoice->items->sum('line_total');
        $totalGoldWeight = $invoice->items->sum('gold_weight');
        $totalStoneWeight = $invoice->items->sum('stone_weight');
        $totalManufacturingFee = $invoice->items->sum('manufacturing_fee');
        $totalProfitAmount = $invoice->items->sum('profit_amount');
        
        $goldValue = $totalGoldWeight * $invoice->gold_price_per_gram;
        $discountAmount = $invoice->discount_amount;
        $taxableAmount = $subtotal - $discountAmount;
        $vatAmount = $taxableAmount * ($invoice->vat_percentage / 100);
        $totalAmount = $taxableAmount + $vatAmount;

        return [
            'subtotal' => $subtotal,
            'total_gold_weight' => $totalGoldWeight,
            'total_stone_weight' => $totalStoneWeight,
            'total_manufacturing_fee' => $totalManufacturingFee,
            'total_profit_amount' => $totalProfitAmount,
            'gold_value' => $goldValue,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $invoice->paid_amount,
            'balance_due' => $totalAmount - $invoice->paid_amount,
        ];
    }

    /**
     * Get payment summary
     */
    protected function getPaymentSummary(Invoice $invoice): array
    {
        $payments = $invoice->payments()->completed()->get();
        
        $summary = [
            'total_payments' => $payments->count(),
            'total_paid' => $payments->sum('amount'),
            'by_method' => [],
        ];

        foreach ($payments->groupBy('payment_method') as $method => $methodPayments) {
            $summary['by_method'][$method] = [
                'count' => $methodPayments->count(),
                'amount' => $methodPayments->sum('amount'),
                'payments' => $methodPayments->toArray(),
            ];
        }

        return $summary;
    }

    /**
     * Get invoice type display name in Persian
     */
    public function getInvoiceTypeDisplay(string $type): string
    {
        return match ($type) {
            'sale' => 'فاکتور فروش',
            'purchase' => 'فاکتور خرید',
            'trade' => 'فاکتور معاوضه',
            default => 'فاکتور',
        };
    }

    /**
     * Get payment method display name in Persian
     */
    public function getPaymentMethodDisplay(string $method): string
    {
        return match ($method) {
            'cash' => 'نقد',
            'card' => 'کارت',
            'cheque' => 'چک',
            'credit' => 'اعتبار',
            'bank_transfer' => 'انتقال بانکی',
            default => $method,
        };
    }

    /**
     * Format number for Persian display
     */
    public function formatPersianNumber(float $number, int $decimals = 0): string
    {
        $formatted = number_format($number, $decimals);
        
        // Convert to Persian digits
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($englishDigits, $persianDigits, $formatted);
    }

    /**
     * Format currency for display
     */
    public function formatCurrency(float $amount, string $currency = 'IRR'): string
    {
        $formatted = $this->formatPersianNumber($amount);
        
        return match ($currency) {
            'IRR' => $formatted . ' ریال',
            'USD' => '$' . number_format($amount, 2),
            'EUR' => '€' . number_format($amount, 2),
            default => $formatted . ' ' . $currency,
        };
    }
}