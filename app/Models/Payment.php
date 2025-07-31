<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'payment_number',
        'payment_method',
        'amount',
        'payment_date',
        'status',
        'notes',
        'reference_number',
        'bank_name',
        'cheque_date',
        'cheque_due_date',
        'cheque_number',
        'payment_details',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'cheque_due_date' => 'date',
        'payment_details' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the invoice that owns the payment
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the customer that owns the payment
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who processed this payment
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope to get payments by method
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope to get payments by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get payments within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is by cheque
     */
    public function isCheque(): bool
    {
        return $this->payment_method === 'cheque';
    }

    /**
     * Check if payment is cash
     */
    public function isCash(): bool
    {
        return $this->payment_method === 'cash';
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->processed_at = now();
        $this->processed_by = auth()->id();
        $this->save();

        // Update invoice payment status
        $this->invoice->updatePaymentStatus();

        // Update customer ledger
        $this->updateCustomerLedger();
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->status = 'failed';
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Failed: " . $reason;
        }
        $this->save();
    }

    /**
     * Update customer ledger
     */
    private function updateCustomerLedger(): void
    {
        if (!$this->isCompleted()) {
            return;
        }

        CustomerLedger::create([
            'customer_id' => $this->customer_id,
            'transaction_type' => 'credit',
            'amount' => $this->amount,
            'currency' => $this->invoice->currency,
            'description' => "Payment for Invoice {$this->invoice->invoice_number}",
            'reference_type' => 'payment',
            'reference_id' => $this->id,
            'balance_after' => $this->customer->current_balance - $this->amount,
            'transaction_date' => $this->payment_date,
            'created_by' => $this->processed_by,
        ]);

        // Update customer balance
        $this->customer->updateBalance($this->amount, 'debit');
    }

    /**
     * Get payment method display name
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'نقد',
            'card' => 'کارت',
            'cheque' => 'چک',
            'credit' => 'اعتبار',
            'bank_transfer' => 'انتقال بانکی',
            default => $this->payment_method,
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'در انتظار',
            'completed' => 'تکمیل شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده',
            default => $this->status,
        };
    }

    /**
     * Generate unique payment number
     */
    public static function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-' . date('Ym') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('payment_number', $number)->exists());

        return $number;
    }
}
