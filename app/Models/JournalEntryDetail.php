<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'description',
        'debit_amount',
        'credit_amount',
        'cost_center',
        'additional_data',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'additional_data' => 'array',
    ];

    /**
     * Get the journal entry that owns the detail
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account associated with this detail
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope to get details with debit amounts
     */
    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope to get details with credit amounts
     */
    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Scope to get details for a specific account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to get details by cost center
     */
    public function scopeByCostCenter($query, $costCenter)
    {
        return $query->where('cost_center', $costCenter);
    }

    /**
     * Check if this is a debit entry
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if this is a credit entry
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get the amount (debit or credit)
     */
    public function getAmountAttribute(): float
    {
        return $this->debit_amount > 0 ? $this->debit_amount : $this->credit_amount;
    }

    /**
     * Get the entry type (debit or credit)
     */
    public function getTypeAttribute(): string
    {
        return $this->debit_amount > 0 ? 'debit' : 'credit';
    }

    /**
     * Get type display name
     */
    public function getTypeDisplayAttribute(): string
    {
        return $this->debit_amount > 0 ? 'بدهکار' : 'بستانکار';
    }

    /**
     * Validate that either debit or credit is set, but not both
     */
    protected static function booted()
    {
        static::saving(function ($detail) {
            // Ensure only one of debit or credit is set
            if ($detail->debit_amount > 0 && $detail->credit_amount > 0) {
                throw new \Exception('A journal entry detail cannot have both debit and credit amounts.');
            }

            if ($detail->debit_amount <= 0 && $detail->credit_amount <= 0) {
                throw new \Exception('A journal entry detail must have either a debit or credit amount.');
            }
        });
    }
}
