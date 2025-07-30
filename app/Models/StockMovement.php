<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'batch_number',
        'unit_cost',
        'total_cost',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'stock_before' => 'decimal:3',
        'stock_after' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Get the product that owns the stock movement
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created this movement
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
                'purchase' => Purchase::class,
                'adjustment' => StockAdjustment::class,
                default => null,
            };

            if ($modelClass) {
                return $modelClass::find($this->reference_id);
            }
        }

        return null;
    }

    /**
     * Scope to get movements for a specific product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to get movements by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get movements within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get movements by batch
     */
    public function scopeByBatch($query, $batchNumber)
    {
        return $query->where('batch_number', $batchNumber);
    }

    /**
     * Check if this is an inbound movement (increases stock)
     */
    public function isInbound(): bool
    {
        return in_array($this->type, ['add', 'adjustment']) && $this->quantity > 0;
    }

    /**
     * Check if this is an outbound movement (decreases stock)
     */
    public function isOutbound(): bool
    {
        return in_array($this->type, ['subtract', 'adjustment']) && $this->quantity < 0;
    }

    /**
     * Get the absolute quantity (always positive)
     */
    public function getAbsoluteQuantityAttribute(): float
    {
        return abs($this->quantity);
    }

    /**
     * Get movement direction (in/out)
     */
    public function getDirectionAttribute(): string
    {
        return $this->stock_after > $this->stock_before ? 'in' : 'out';
    }

    /**
     * Calculate stock variance
     */
    public function getStockVarianceAttribute(): float
    {
        return $this->stock_after - $this->stock_before;
    }
}
