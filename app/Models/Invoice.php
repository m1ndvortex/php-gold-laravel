<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'type',
        'status',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'total_gold_weight',
        'gold_price_per_gram',
        'manufacturing_fee',
        'profit_margin_percentage',
        'vat_percentage',
        'currency',
        'notes',
        'terms_conditions',
        'custom_fields',
        'is_recurring',
        'recurring_pattern',
        'next_recurring_date',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_gold_weight' => 'decimal:3',
        'gold_price_per_gram' => 'decimal:2',
        'manufacturing_fee' => 'decimal:2',
        'profit_margin_percentage' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'custom_fields' => 'array',
        'is_recurring' => 'boolean',
        'next_recurring_date' => 'date',
    ];

    /**
     * Get the customer that owns the invoice
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items for this invoice
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get all payments for this invoice
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the user who created this invoice
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get invoices by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get invoices by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereIn('status', ['pending', 'partial']);
    }

    /**
     * Scope to get invoices within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get recurring invoices
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Calculate total gold cost for all items
     */
    public function calculateGoldCost(): float
    {
        return $this->total_gold_weight * $this->gold_price_per_gram;
    }

    /**
     * Calculate total manufacturing cost
     */
    public function calculateManufacturingCost(): float
    {
        return $this->items->sum('manufacturing_fee');
    }

    /**
     * Calculate profit amount
     */
    public function calculateProfitAmount(): float
    {
        $baseCost = $this->calculateGoldCost() + $this->calculateManufacturingCost();
        return $baseCost * ($this->profit_margin_percentage / 100);
    }

    /**
     * Calculate VAT amount
     */
    public function calculateVatAmount(): float
    {
        $taxableAmount = $this->subtotal - $this->discount_amount;
        return $taxableAmount * ($this->vat_percentage / 100);
    }

    /**
     * Recalculate invoice totals
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->total_gold_weight = $this->items->sum('gold_weight');
        $this->tax_amount = $this->calculateVatAmount();
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->balance_due = $this->total_amount - $this->paid_amount;
        $this->save();
    }

    /**
     * Add payment to invoice
     */
    public function addPayment(float $amount, string $method, array $details = []): Payment
    {
        $payment = $this->payments()->create([
            'customer_id' => $this->customer_id,
            'payment_number' => $this->generatePaymentNumber(),
            'payment_method' => $method,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'status' => 'completed',
            'payment_details' => $details,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $this->updatePaymentStatus();
        return $payment;
    }

    /**
     * Update invoice payment status based on payments
     */
    public function updatePaymentStatus(): void
    {
        $this->paid_amount = $this->payments()->where('status', 'completed')->sum('amount');
        $this->balance_due = $this->total_amount - $this->paid_amount;

        if ($this->balance_due <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'pending';
        }

        // Check if overdue
        if ($this->due_date && $this->due_date->isPast() && $this->balance_due > 0) {
            $this->status = 'overdue';
        }

        $this->save();
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->balance_due > 0;
    }

    /**
     * Check if invoice is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->balance_due <= 0;
    }

    /**
     * Get days until due date
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(string $type = 'sale'): string
    {
        $prefix = match ($type) {
            'sale' => 'INV',
            'purchase' => 'PUR',
            'trade' => 'TRD',
            default => 'INV',
        };

        $year = date('Y');
        $month = date('m');

        do {
            $number = $prefix . '-' . $year . $month . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('invoice_number', $number)->exists());

        return $number;
    }

    /**
     * Generate unique payment number
     */
    private function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-' . date('Ym') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Payment::where('payment_number', $number)->exists());

        return $number;
    }

    /**
     * Create next recurring invoice
     */
    public function createRecurringInvoice(): ?Invoice
    {
        if (!$this->is_recurring || !$this->next_recurring_date) {
            return null;
        }

        $newInvoice = $this->replicate();
        $newInvoice->invoice_number = self::generateInvoiceNumber($this->type);
        $newInvoice->invoice_date = $this->next_recurring_date;
        $newInvoice->due_date = $this->calculateNextDueDate($this->next_recurring_date);
        $newInvoice->status = 'draft';
        $newInvoice->paid_amount = 0;
        $newInvoice->balance_due = $newInvoice->total_amount;
        $newInvoice->save();

        // Copy invoice items
        foreach ($this->items as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }

        // Update next recurring date
        $this->next_recurring_date = $this->calculateNextRecurringDate();
        $this->save();

        return $newInvoice;
    }

    /**
     * Calculate next recurring date
     */
    private function calculateNextRecurringDate(): Carbon
    {
        return match ($this->recurring_pattern) {
            'monthly' => $this->next_recurring_date->addMonth(),
            'quarterly' => $this->next_recurring_date->addMonths(3),
            'yearly' => $this->next_recurring_date->addYear(),
            default => $this->next_recurring_date->addMonth(),
        };
    }

    /**
     * Calculate due date based on invoice date
     */
    private function calculateNextDueDate(Carbon $invoiceDate): Carbon
    {
        // Default to 30 days from invoice date
        return $invoiceDate->copy()->addDays(30);
    }
}
