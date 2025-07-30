<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_en',
        'sku',
        'barcode',
        'category_id',
        'type',
        'description',
        'description_en',
        'gold_weight',
        'stone_weight',
        'total_weight',
        'manufacturing_cost',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'unit_price',
        'selling_price',
        'unit_of_measure',
        'is_active',
        'track_stock',
        'has_bom',
        'images',
        'specifications',
        'tags',
        'location',
    ];

    protected $casts = [
        'gold_weight' => 'decimal:3',
        'stone_weight' => 'decimal:3',
        'total_weight' => 'decimal:3',
        'manufacturing_cost' => 'decimal:2',
        'current_stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
        'has_bom' => 'boolean',
        'images' => 'array',
        'specifications' => 'array',
        'tags' => 'array',
    ];

    /**
     * Get the category that owns the product
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Get all BOM items for this product
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'product_id');
    }

    /**
     * Get all BOM items where this product is a component
     */
    public function bomComponents(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'component_id');
    }

    /**
     * Get all stock movements for this product
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get all invoice items for this product
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get products by category
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to get products by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get products with low stock
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
                    ->whereColumn('current_stock', '<=', 'minimum_stock');
    }

    /**
     * Scope to search products by name, SKU, or barcode
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        });
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
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->track_stock && $this->current_stock <= $this->minimum_stock;
    }

    /**
     * Check if product is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->current_stock <= 0;
    }

    /**
     * Get available stock quantity
     */
    public function getAvailableStockAttribute(): float
    {
        return max(0, $this->current_stock);
    }

    /**
     * Calculate total cost including gold price
     */
    public function calculateTotalCost(float $goldPricePerGram = 0): float
    {
        $goldCost = $this->gold_weight * $goldPricePerGram;
        return $goldCost + $this->manufacturing_cost;
    }

    /**
     * Update stock quantity
     */
    public function updateStock(float $quantity, string $type = 'add', string $reason = null): void
    {
        if (!$this->track_stock) {
            return;
        }

        $oldStock = $this->current_stock;
        
        if ($type === 'add') {
            $this->current_stock += $quantity;
        } else {
            $this->current_stock -= $quantity;
        }

        $this->save();

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $oldStock,
            'stock_after' => $this->current_stock,
            'reason' => $reason ?? ucfirst($type),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Generate unique SKU
     */
    public static function generateSku(string $prefix = 'PRD'): string
    {
        do {
            $sku = $prefix . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }
}
