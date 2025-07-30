<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'tax_id',
        'customer_group_id',
        'credit_limit',
        'current_balance',
        'birth_date',
        'notes',
        'tags',
        'city',
        'postal_code',
        'national_id',
        'customer_type',
        'is_active',
        'last_transaction_at',
        'contact_preferences',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'last_transaction_at' => 'datetime',
        'tags' => 'array',
        'contact_preferences' => 'array',
    ];

    /**
     * Get the customer group that owns the customer
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /**
     * Get all invoices for this customer
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all ledger entries for this customer
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }

    /**
     * Scope to get only active customers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by customer group
     */
    public function scopeInGroup($query, $groupId)
    {
        return $query->where('customer_group_id', $groupId);
    }

    /**
     * Scope to search customers by name, phone, or email
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * Check if customer has exceeded credit limit
     */
    public function hasExceededCreditLimit(): bool
    {
        return $this->current_balance > $this->credit_limit;
    }

    /**
     * Get available credit amount
     */
    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->current_balance);
    }

    /**
     * Check if customer's birthday is today
     */
    public function isBirthdayToday(): bool
    {
        if (!$this->birth_date) {
            return false;
        }

        return $this->birth_date->format('m-d') === Carbon::today()->format('m-d');
    }

    /**
     * Check if customer's birthday is within next N days
     */
    public function isBirthdayWithinDays(int $days = 7): bool
    {
        if (!$this->birth_date) {
            return false;
        }

        $today = Carbon::today();
        $birthday = $this->birth_date->setYear($today->year);
        
        // If birthday has passed this year, check next year
        if ($birthday->lt($today)) {
            $birthday->addYear();
        }

        return $birthday->diffInDays($today) <= $days;
    }

    /**
     * Update customer balance
     */
    public function updateBalance(float $amount, string $type = 'credit'): void
    {
        if ($type === 'credit') {
            $this->current_balance += $amount;
        } else {
            $this->current_balance -= $amount;
        }

        $this->last_transaction_at = now();
        $this->save();
    }

    /**
     * Get customer's total purchases amount
     */
    public function getTotalPurchasesAttribute(): float
    {
        return $this->invoices()
            ->where('type', 'sale')
            ->where('status', 'paid')
            ->sum('total_amount');
    }

    /**
     * Get customer's last purchase date
     */
    public function getLastPurchaseDateAttribute(): ?Carbon
    {
        $lastInvoice = $this->invoices()
            ->where('type', 'sale')
            ->where('status', 'paid')
            ->latest('created_at')
            ->first();

        return $lastInvoice ? $lastInvoice->created_at : null;
    }
}
