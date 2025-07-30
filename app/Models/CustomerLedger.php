<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'amount',
        'gold_amount',
        'currency',
        'description',
        'reference_type',
        'reference_id',
        'balance_after',
        'gold_balance_after',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gold_amount' => 'decimal:3',
        'balance_after' => 'decimal:2',
        'gold_balance_after' => 'decimal:3',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the customer that owns the ledger entry
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created this entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reference model (polymorphic relationship)
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            $modelClass = match ($this->reference_type) {
                'invoice' => Invoice::class,
                'payment' => Payment::class,
                default => null,
            };

            if ($modelClass) {
                return $modelClass::find($this->reference_id);
            }
        }

        return null;
    }

    /**
     * Scope to get entries for a specific customer
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get entries by transaction type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope to get entries within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Check if this is a debit transaction
     */
    public function isDebit(): bool
    {
        return $this->transaction_type === 'debit';
    }

    /**
     * Check if this is a credit transaction
     */
    public function isCredit(): bool
    {
        return $this->transaction_type === 'credit';
    }
}
