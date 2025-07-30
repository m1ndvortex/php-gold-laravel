<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_sku',
        'description',
        'quantity',
        'gold_weight',
        'stone_weight',
        'unit_price',
        'gold_price_per_gram',
        'manufacturing_fee',
        'profit_amount',
        'discount_percentage',
        'discount_amount',
        'line_total',
        'custom_attributes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'gold_weight' => 'decimal:3',
        'stone_weight' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'gold_price_per_gram' => 'decimal:2',
        'manufacturing_fee' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'custom_attributes' => 'array',
    ];

    /**
     * Get the invoice that owns the item
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product associated with this item
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate gold cost for this item
     */
    public function calculateGoldCost(): float
    {
        return $this->gold_weight * $this->gold_price_per_gram;
    }

    /**
     * Calculate base cost (gold + manufacturing)
     */
    public function calculateBaseCost(): float
    {
        return $this->calculateGoldCost() + $this->manufacturing_fee;
    }

    /**
     * Calculate total cost including profit
     */
    public function calculateTotalCost(): float
    {
        return $this->calculateBaseCost() + $this->profit_amount;
    }

    /**
     * Calculate line total with discount
     */
    public function calculateLineTotal(): float
    {
        $baseTotal = $this->unit_price * $this->quantity;
        return $baseTotal - $this->discount_amount;
    }

    /**
     * Recalculate line total and update
     */
    public function recalculateLineTotal(): void
    {
        $this->line_total = $this->calculateLineTotal();
        $this->save();
    }

    /**
     * Apply discount percentage
     */
    public function applyDiscountPercentage(float $percentage): void
    {
        $this->discount_percentage = $percentage;
        $baseTotal = $this->unit_price * $this->quantity;
        $this->discount_amount = $baseTotal * ($percentage / 100);
        $this->recalculateLineTotal();
    }

    /**
     * Apply discount amount
     */
    public function applyDiscountAmount(float $amount): void
    {
        $this->discount_amount = $amount;
        $baseTotal = $this->unit_price * $this->quantity;
        $this->discount_percentage = $baseTotal > 0 ? ($amount / $baseTotal) * 100 : 0;
        $this->recalculateLineTotal();
    }

    /**
     * Get effective unit price after discount
     */
    public function getEffectiveUnitPriceAttribute(): float
    {
        if ($this->quantity <= 0) {
            return 0;
        }

        return $this->line_total / $this->quantity;
    }

    /**
     * Get total weight (gold + stone)
     */
    public function getTotalWeightAttribute(): float
    {
        return $this->gold_weight + $this->stone_weight;
    }

    /**
     * Check if item has discount
     */
    public function hasDiscount(): bool
    {
        return $this->discount_amount > 0 || $this->discount_percentage > 0;
    }

    /**
     * Get profit margin percentage for this item
     */
    public function getProfitMarginPercentageAttribute(): float
    {
        $baseCost = $this->calculateBaseCost();
        if ($baseCost <= 0) {
            return 0;
        }

        return ($this->profit_amount / $baseCost) * 100;
    }

    /**
     * Update product stock when item is saved/updated
     */
    protected static function booted()
    {
        static::created(function ($item) {
            $item->updateProductStock('subtract');
        });

        static::updated(function ($item) {
            if ($item->isDirty('quantity')) {
                $oldQuantity = $item->getOriginal('quantity');
                $newQuantity = $item->quantity;
                $difference = $newQuantity - $oldQuantity;

                if ($difference > 0) {
                    $item->updateProductStock('subtract', $difference);
                } else {
                    $item->updateProductStock('add', abs($difference));
                }
            }
        });

        static::deleted(function ($item) {
            $item->updateProductStock('add');
        });
    }

    /**
     * Update product stock
     */
    private function updateProductStock(string $type, float $quantity = null): void
    {
        if (!$this->product || !$this->product->track_stock) {
            return;
        }

        $quantity = $quantity ?? $this->quantity;
        $reason = "Invoice {$this->invoice->invoice_number}";

        $this->product->updateStock($quantity, $type, $reason);
    }
}
