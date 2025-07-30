<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillOfMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'component_id',
        'quantity',
        'unit_of_measure',
        'wastage_percentage',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'wastage_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the component product
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    /**
     * Scope to get only active BOM items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get BOM items for a specific product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Calculate total quantity needed including wastage
     */
    public function getTotalQuantityNeededAttribute(): float
    {
        $wastageMultiplier = 1 + ($this->wastage_percentage / 100);
        return $this->quantity * $wastageMultiplier;
    }

    /**
     * Calculate wastage quantity
     */
    public function getWastageQuantityAttribute(): float
    {
        return $this->quantity * ($this->wastage_percentage / 100);
    }

    /**
     * Check if component has sufficient stock
     */
    public function hasSufficientStock(int $productionQuantity = 1): bool
    {
        $requiredQuantity = $this->total_quantity_needed * $productionQuantity;
        return $this->component->current_stock >= $requiredQuantity;
    }

    /**
     * Get required quantity for production
     */
    public function getRequiredQuantity(int $productionQuantity = 1): float
    {
        return $this->total_quantity_needed * $productionQuantity;
    }

    /**
     * Calculate component cost for this BOM item
     */
    public function calculateComponentCost(int $productionQuantity = 1): float
    {
        $requiredQuantity = $this->getRequiredQuantity($productionQuantity);
        return $requiredQuantity * $this->component->unit_price;
    }
}
