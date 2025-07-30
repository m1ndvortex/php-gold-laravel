<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'description',
        'description_en',
        'type',
        'subtype',
        'parent_id',
        'is_active',
        'is_system',
        'opening_balance',
        'current_balance',
        'normal_balance',
        'level',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'level' => 'integer',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Get the parent account
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Get child accounts
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Get all journal entry details for this account
     */
    public function journalEntryDetails(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class);
    }

    /**
     * Scope to get only active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get accounts by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get accounts by subtype
     */
    public function scopeBySubtype($query, $subtype)
    {
        return $query->where('subtype', $subtype);
    }

    /**
     * Scope to get root accounts (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get system accounts
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to get non-system accounts
     */
    public function scopeNonSystem($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Get localized name based on current locale
     */
    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'en' && $this->name_en ? $this->name_en : $this->name;
    }

    /**
     * Get localized description based on current locale
     */
    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'en' && $this->description_en ? $this->description_en : $this->description;
    }

    /**
     * Get full account path (parent > child)
     */
    public function getFullPathAttribute(): string
    {
        $path = collect();
        $account = $this;

        while ($account) {
            $path->prepend($account->localized_name);
            $account = $account->parent;
        }

        return $path->implode(' > ');
    }

    /**
     * Get full account code path
     */
    public function getFullCodePathAttribute(): string
    {
        $path = collect();
        $account = $this;

        while ($account) {
            $path->prepend($account->code);
            $account = $account->parent;
        }

        return $path->implode('.');
    }

    /**
     * Check if account has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all descendant accounts
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Calculate account balance
     */
    public function calculateBalance(): float
    {
        $debits = $this->journalEntryDetails()
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->sum('debit_amount');

        $credits = $this->journalEntryDetails()
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->sum('credit_amount');

        // Calculate balance based on normal balance type
        if ($this->normal_balance === 'debit') {
            return $this->opening_balance + $debits - $credits;
        } else {
            return $this->opening_balance + $credits - $debits;
        }
    }

    /**
     * Update current balance
     */
    public function updateBalance(): void
    {
        $this->current_balance = $this->calculateBalance();
        $this->save();
    }

    /**
     * Get balance for a specific date range
     */
    public function getBalanceForPeriod($startDate, $endDate): float
    {
        $debits = $this->journalEntryDetails()
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'posted')
                      ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('debit_amount');

        $credits = $this->journalEntryDetails()
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'posted')
                      ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('credit_amount');

        if ($this->normal_balance === 'debit') {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    /**
     * Check if account is a debit account
     */
    public function isDebitAccount(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Check if account is a credit account
     */
    public function isCreditAccount(): bool
    {
        return $this->normal_balance === 'credit';
    }

    /**
     * Check if account is an asset
     */
    public function isAsset(): bool
    {
        return $this->type === 'asset';
    }

    /**
     * Check if account is a liability
     */
    public function isLiability(): bool
    {
        return $this->type === 'liability';
    }

    /**
     * Check if account is equity
     */
    public function isEquity(): bool
    {
        return $this->type === 'equity';
    }

    /**
     * Check if account is revenue
     */
    public function isRevenue(): bool
    {
        return $this->type === 'revenue';
    }

    /**
     * Check if account is expense
     */
    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    /**
     * Generate unique account code
     */
    public static function generateCode(string $type, string $parentCode = null): string
    {
        $prefix = match ($type) {
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
            default => '9',
        };

        if ($parentCode) {
            $baseCode = $parentCode;
        } else {
            $baseCode = $prefix . '000';
        }

        // Find next available code
        $counter = 1;
        do {
            $code = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get account type display name
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'asset' => 'دارایی',
            'liability' => 'بدهی',
            'equity' => 'حقوق صاحبان سهام',
            'revenue' => 'درآمد',
            'expense' => 'هزینه',
            default => $this->type,
        };
    }

    /**
     * Get normal balance display name
     */
    public function getNormalBalanceDisplayAttribute(): string
    {
        return $this->normal_balance === 'debit' ? 'بدهکار' : 'بستانکار';
    }

    /**
     * Boot method to set account level automatically
     */
    protected static function booted()
    {
        static::creating(function ($account) {
            if ($account->parent_id) {
                $parent = self::find($account->parent_id);
                $account->level = $parent ? $parent->level + 1 : 1;
            } else {
                $account->level = 1;
            }
        });

        static::updating(function ($account) {
            if ($account->isDirty('parent_id')) {
                if ($account->parent_id) {
                    $parent = self::find($account->parent_id);
                    $account->level = $parent ? $parent->level + 1 : 1;
                } else {
                    $account->level = 1;
                }
            }
        });
    }
}
