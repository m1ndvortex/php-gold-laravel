<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BillOfMaterial;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    protected $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Create a new product with category validation
     */
    public function createProduct(array $data): Product
    {
        DB::beginTransaction();
        
        try {
            // Validate category exists and is active
            $category = ProductCategory::active()->findOrFail($data['category_id']);
            
            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateSku($category->type);
            }
            
            // Generate barcode if not provided
            if (empty($data['barcode']) && ($data['generate_barcode'] ?? false)) {
                $data['barcode'] = $this->generateBarcode();
            }
            
            // Calculate total weight
            $data['total_weight'] = ($data['gold_weight'] ?? 0) + ($data['stone_weight'] ?? 0);
            
            $product = Product::create($data);
            
            // Create initial stock movement if stock is provided
            if (($data['current_stock'] ?? 0) > 0) {
                $this->createStockMovement($product, [
                    'type' => 'add',
                    'quantity' => $data['current_stock'],
                    'stock_before' => 0,
                    'stock_after' => $data['current_stock'],
                    'reason' => 'Initial Stock',
                    'notes' => 'Initial stock entry during product creation',
                ]);
            }
            
            DB::commit();
            return $product->fresh();
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Update product stock with movement tracking
     */
    public function updateStock(Product $product, float $quantity, string $type = 'add', array $options = []): StockMovement
    {
        if (!$product->track_stock) {
            throw new Exception('Stock tracking is disabled for this product');
        }
        
        DB::beginTransaction();
        
        try {
            $stockBefore = $product->current_stock;
            
            // Calculate new stock level
            if ($type === 'add') {
                $newStock = $stockBefore + $quantity;
            } elseif ($type === 'subtract') {
                if ($stockBefore < $quantity && !($options['allow_negative'] ?? false)) {
                    throw new Exception('Insufficient stock. Available: ' . $stockBefore . ', Required: ' . $quantity);
                }
                $newStock = $stockBefore - $quantity;
            } else {
                // For adjustments, quantity can be positive or negative
                $newStock = $stockBefore + $quantity;
            }
            
            // Update product stock
            $product->update(['current_stock' => $newStock]);
            
            // Create stock movement record
            $movement = $this->createStockMovement($product, [
                'type' => $type,
                'quantity' => $type === 'subtract' ? -$quantity : $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reason' => $options['reason'] ?? ucfirst($type),
                'notes' => $options['notes'] ?? null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'batch_number' => $options['batch_number'] ?? null,
                'unit_cost' => $options['unit_cost'] ?? null,
                'total_cost' => $options['total_cost'] ?? null,
            ]);
            
            // Broadcast inventory update to all connected users
            $this->webSocketService->broadcastInventoryUpdate(
                $product->id,
                $product->name,
                $stockBefore,
                $newStock,
                $type
            );
            
            DB::commit();
            return $movement;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Create stock movement record
     */
    public function createStockMovement(Product $product, array $data): StockMovement
    {
        $data['product_id'] = $product->id;
        $data['created_by'] = auth()->id();
        
        // Ensure required fields are set
        if (!isset($data['stock_before'])) {
            $data['stock_before'] = $product->current_stock;
        }
        
        if (!isset($data['stock_after'])) {
            $data['stock_after'] = $product->current_stock;
        }
        
        // Calculate total cost if unit cost is provided
        if (isset($data['unit_cost']) && !isset($data['total_cost'])) {
            $data['total_cost'] = abs($data['quantity']) * $data['unit_cost'];
        }
        
        return StockMovement::create($data);
    }
    
    /**
     * Get products with low stock
     */
    public function getLowStockProducts(): Collection
    {
        return Product::lowStock()
            ->with(['category'])
            ->orderBy('current_stock', 'asc')
            ->get();
    }
    
    /**
     * Get stock movement history for a product
     */
    public function getStockHistory(Product $product, array $filters = []): Collection
    {
        $query = $product->stockMovements()
            ->with(['creator'])
            ->orderBy('created_at', 'desc');
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }
    
    /**
     * Generate unique SKU based on category type
     */
    public function generateSku(string $categoryType): string
    {
        $prefix = match ($categoryType) {
            'raw_gold' => 'RG',
            'finished_jewelry' => 'FJ',
            'coins' => 'CN',
            'stones' => 'ST',
            default => 'PR',
        };
        
        do {
            $sku = $prefix . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Product::where('sku', $sku)->exists());
        
        return $sku;
    }
    
    /**
     * Generate unique barcode
     */
    public function generateBarcode(): string
    {
        do {
            // Generate EAN-13 compatible barcode
            $barcode = '2' . str_pad(mt_rand(1, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists());
        
        return $barcode;
    }
    
    /**
     * Check if sufficient stock is available for BOM production
     */
    public function checkBomStockAvailability(Product $product, int $quantity = 1): array
    {
        if (!$product->has_bom) {
            return ['available' => true, 'shortages' => []];
        }
        
        $shortages = [];
        $bomItems = $product->bomItems()->active()->with('component')->get();
        
        foreach ($bomItems as $bomItem) {
            $requiredQuantity = $bomItem->getRequiredQuantity($quantity);
            $availableStock = $bomItem->component->current_stock;
            
            if ($availableStock < $requiredQuantity) {
                $shortages[] = [
                    'component' => $bomItem->component,
                    'required' => $requiredQuantity,
                    'available' => $availableStock,
                    'shortage' => $requiredQuantity - $availableStock,
                ];
            }
        }
        
        return [
            'available' => empty($shortages),
            'shortages' => $shortages,
        ];
    }
    
    /**
     * Process BOM production - consume components and add finished product
     */
    public function processBomProduction(Product $product, int $quantity, array $options = []): array
    {
        if (!$product->has_bom) {
            throw new Exception('Product does not have a Bill of Materials');
        }
        
        // Check stock availability first
        $stockCheck = $this->checkBomStockAvailability($product, $quantity);
        if (!$stockCheck['available']) {
            throw new Exception('Insufficient component stock for production');
        }
        
        DB::beginTransaction();
        
        try {
            $movements = [];
            $bomItems = $product->bomItems()->active()->with('component')->get();
            
            // Consume components
            foreach ($bomItems as $bomItem) {
                $requiredQuantity = $bomItem->getRequiredQuantity($quantity);
                
                $movement = $this->updateStock($bomItem->component, $requiredQuantity, 'subtract', [
                    'reason' => 'BOM Production',
                    'notes' => "Used in production of {$quantity} x {$product->name}",
                    'reference_type' => 'production',
                    'reference_id' => $product->id,
                    'batch_number' => $options['batch_number'] ?? null,
                ]);
                
                $movements[] = $movement;
            }
            
            // Add finished product to stock
            $finishedMovement = $this->updateStock($product, $quantity, 'add', [
                'reason' => 'BOM Production',
                'notes' => "Produced from BOM components",
                'reference_type' => 'production',
                'batch_number' => $options['batch_number'] ?? null,
            ]);
            
            $movements[] = $finishedMovement;
            
            DB::commit();
            
            return [
                'success' => true,
                'movements' => $movements,
                'produced_quantity' => $quantity,
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Perform stock adjustment with reason
     */
    public function adjustStock(Product $product, float $newStock, string $reason, string $notes = null): StockMovement
    {
        $currentStock = $product->current_stock;
        $adjustment = $newStock - $currentStock;
        
        return $this->updateStock($product, abs($adjustment), 'adjustment', [
            'quantity' => $adjustment, // Can be positive or negative
            'reason' => $reason,
            'notes' => $notes,
            'stock_after' => $newStock,
        ]);
    }
    
    /**
     * Get inventory valuation report
     */
    public function getInventoryValuation(array $filters = []): array
    {
        $query = Product::with(['category'])
            ->where('track_stock', true)
            ->where('is_active', true);
        
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        $products = $query->get();
        
        $totalValue = 0;
        $totalQuantity = 0;
        $categoryBreakdown = [];
        
        foreach ($products as $product) {
            $stockValue = $product->current_stock * $product->unit_price;
            $totalValue += $stockValue;
            $totalQuantity += $product->current_stock;
            
            $categoryName = $product->category->localized_name;
            if (!isset($categoryBreakdown[$categoryName])) {
                $categoryBreakdown[$categoryName] = [
                    'quantity' => 0,
                    'value' => 0,
                    'products' => 0,
                ];
            }
            
            $categoryBreakdown[$categoryName]['quantity'] += $product->current_stock;
            $categoryBreakdown[$categoryName]['value'] += $stockValue;
            $categoryBreakdown[$categoryName]['products']++;
        }
        
        return [
            'total_value' => $totalValue,
            'total_quantity' => $totalQuantity,
            'total_products' => $products->count(),
            'category_breakdown' => $categoryBreakdown,
            'products' => $products,
        ];
    }
}