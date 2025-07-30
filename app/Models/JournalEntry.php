<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'description',
        'reference',
        'reference_type',
        'reference_id',
        'total_debit',
        'total_credit',
        'status',
        'is_recurring',
        'recurring_pattern',
        'next_recurring_date',
        'is_system_generated',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $attributes = [
        'total_debit' => 0,
        'total_credit' => 0,
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_recurring' => 'boolean',
        'next_recurring_date' => 'date',
        'is_system_generated' => 'boolean',
        'posted_at' => 'datetime',
    ];

    /**
     * Get all details for this journal entry
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class);
    }

    /**
     * Get the user who created this entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who posted this entry
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
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
                'adjustment' => Adjustment::class,
                default => null,
            };

            if ($modelClass) {
                return $modelClass::find($this->reference_id);
            }
        }

        return null;
    }

    /**
     * Scope to get entries by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get posted entries
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope to get draft entries
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get entries within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get recurring entries
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope to get system generated entries
     */
    public function scopeSystemGenerated($query)
    {
        return $query->where('is_system_generated', true);
    }

    /**
     * Check if entry is balanced (debits = credits)
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Check if entry is posted
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Check if entry is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if entry is reversed
     */
    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    /**
     * Post the journal entry
     */
    public function post(): bool
    {
        if (!$this->isDraft()) {
            return false;
        }

        if (!$this->isBalanced()) {
            throw new \Exception('Journal entry is not balanced. Debits must equal credits.');
        }

        $this->status = 'posted';
        $this->posted_by = auth()->id();
        $this->posted_at = now();
        $this->save();

        // Update account balances
        $this->updateAccountBalances();

        return true;
    }

    /**
     * Reverse the journal entry
     */
    public function reverse(string $reason = null): JournalEntry
    {
        if (!$this->isPosted()) {
            throw new \Exception('Only posted entries can be reversed.');
        }

        // Create reversing entry
        $reversingEntry = new JournalEntry([
            'entry_number' => self::generateEntryNumber(),
            'entry_date' => now()->toDateString(),
            'description' => 'Reversing entry for: ' . $this->description . ($reason ? ' - ' . $reason : ''),
            'reference' => $this->entry_number,
            'reference_type' => 'journal_entry',
            'reference_id' => $this->id,
            'total_debit' => $this->total_credit,
            'total_credit' => $this->total_debit,
            'status' => 'posted',
            'is_system_generated' => true,
            'created_by' => auth()->id(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
        $reversingEntry->save();

        // Create reversing details
        foreach ($this->details as $detail) {
            JournalEntryDetail::create([
                'journal_entry_id' => $reversingEntry->id,
                'account_id' => $detail->account_id,
                'description' => 'Reversing: ' . $detail->description,
                'debit_amount' => $detail->credit_amount,
                'credit_amount' => $detail->debit_amount,
                'cost_center' => $detail->cost_center,
            ]);
        }

        // Update reversing entry totals
        $reversingEntry->recalculateTotals();

        // Mark original entry as reversed
        $this->status = 'reversed';
        $this->save();

        // Update account balances
        $reversingEntry->updateAccountBalances();

        return $reversingEntry;
    }

    /**
     * Recalculate totals from details
     */
    public function recalculateTotals(): void
    {
        $this->load('details'); // Ensure details are loaded
        $this->total_debit = $this->details->sum('debit_amount');
        $this->total_credit = $this->details->sum('credit_amount');
        $this->save();
    }

    /**
     * Update account balances for all affected accounts
     */
    private function updateAccountBalances(): void
    {
        $accountIds = $this->details->pluck('account_id')->unique();
        
        foreach ($accountIds as $accountId) {
            $account = Account::find($accountId);
            if ($account) {
                $account->updateBalance();
            }
        }
    }

    /**
     * Add detail line to journal entry
     */
    public function addDetail(int $accountId, float $debitAmount = 0, float $creditAmount = 0, string $description = null, string $costCenter = null): JournalEntryDetail
    {
        $detail = $this->details()->create([
            'account_id' => $accountId,
            'description' => $description ?? $this->description,
            'debit_amount' => $debitAmount,
            'credit_amount' => $creditAmount,
            'cost_center' => $costCenter,
        ]);

        $this->recalculateTotals();

        return $detail;
    }

    /**
     * Create next recurring entry
     */
    public function createRecurringEntry(): ?JournalEntry
    {
        if (!$this->is_recurring || !$this->next_recurring_date) {
            return null;
        }

        $newEntry = $this->replicate();
        $newEntry->entry_number = self::generateEntryNumber();
        $newEntry->entry_date = $this->next_recurring_date;
        $newEntry->status = 'draft';
        $newEntry->posted_by = null;
        $newEntry->posted_at = null;
        $newEntry->save();

        // Copy details
        foreach ($this->details as $detail) {
            $newDetail = $detail->replicate();
            $newDetail->journal_entry_id = $newEntry->id;
            $newDetail->save();
        }

        // Update next recurring date
        $this->next_recurring_date = $this->calculateNextRecurringDate();
        $this->save();

        return $newEntry;
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
     * Generate unique entry number
     */
    public static function generateEntryNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        do {
            $number = 'JE-' . $year . $month . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('entry_number', $number)->exists());

        return $number;
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'پیش‌نویس',
            'posted' => 'ثبت شده',
            'reversed' => 'برگشت خورده',
            default => $this->status,
        };
    }

    /**
     * Create journal entry from invoice
     */
    public static function createFromInvoice(Invoice $invoice): JournalEntry
    {
        $entry = new JournalEntry([
            'entry_number' => self::generateEntryNumber(),
            'entry_date' => $invoice->invoice_date,
            'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->name}",
            'reference' => $invoice->invoice_number,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
            'is_system_generated' => true,
            'created_by' => auth()->id(),
        ]);
        $entry->save();

        // Debit: Accounts Receivable
        $entry->addDetail(
            accountId: Account::where('code', '1200')->first()->id, // Accounts Receivable
            debitAmount: $invoice->total_amount,
            description: "Invoice {$invoice->invoice_number}"
        );

        // Credit: Sales Revenue
        $entry->addDetail(
            accountId: Account::where('code', '4100')->first()->id, // Sales Revenue
            creditAmount: $invoice->subtotal,
            description: "Sales - Invoice {$invoice->invoice_number}"
        );

        // Credit: VAT Payable (if applicable)
        if ($invoice->tax_amount > 0) {
            $entry->addDetail(
                accountId: Account::where('code', '2300')->first()->id, // VAT Payable
                creditAmount: $invoice->tax_amount,
                description: "VAT - Invoice {$invoice->invoice_number}"
            );
        }

        return $entry;
    }
}
