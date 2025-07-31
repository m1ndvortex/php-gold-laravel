<?php

namespace App\Services;

use App\Models\Product;
use App\Models\BillOfMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class BomService
{
    /**
     * Create or update BOM for a product
     */
    public function createOrUpdateBom(Product $product, array $components): array
    {
        DB::beginTransaction();
        
        try {
            // Mark product as having BOM
            $product->update(['has_bom' => true]);
            
            // Remove existing BOM items
            $product->bomItems()->delete();
            
            $bomItems = [];
            $totalCost = 0;
            
            foreach ($components as $componentData) {
                // Validate component exists
                $component = Product::findOrFail($componentData['component_id']);
                
                // Prevent circular references
                if ($component->id === $product->id) {
                    throw new Exception('Cannot add product as component of itself');
                }
                
                // Check for circular dependencies
                if ($this->hasCircularDependency($product->id, $component->id)) {
                    throw new Exception("Circular dependency detected with component: {$component->name}");
                }
                
                $bomItem = BillOfMaterial::create([
                    'product_id' => $product->id,
                    'component_id' => $component->id,
                    'quantity' => $componentData['quantity'],
                    'unit_of_measure' => $componentData['unit_of_measure'] ?? 'piece',
                    'wastage_percentage' => $componentData['wastage_percentage'] ?? 0,
                    'notes' => $componentData['notes'] ?? null,
                    'is_active' => $componentData['is_active'] ?? true,
                ]);
                
                $bomItems[] = $bomItem->load('component');
                $totalCost += $bomItem->calculateComponentCost();
            }
            
            // Update product manufacturing cost based on BOM
            $product->update(['manufacturing_cost' => $totalCost]);
            
            DB::commit();
            
            return [
                'success' => true,
                'bom_items' => $bomItems,
                'total_cost' => $totalCost,
                'components_count' => count($bomItems),
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get BOM structure for a product
     */
    public function getBomStructure(Product $product, int $depth = 0, int $maxDepth = 5): array
    {
        if ($depth > $maxDepth) {
            return ['error' => 'Maximum depth exceeded'];
        }
        
        $bomItems = $product->bomItems()
            ->active()
            ->with(['component.category'])
            ->orderBy('quantity', 'desc')
            ->get();
        
        $structure = [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'has_bom' => $product->has_bom,
                'current_stock' => $product->current_stock,
                'unit_price' => $product->unit_price,
            ],
            'components' => [],
            'total_cost' => 0,
            'depth' => $depth,
        ];
        
        foreach ($bomItems as $bomItem) {
            $componentCost = $bomItem->calculateComponentCost();
            $structure['total_cost'] += $componentCost;
            
            $componentData = [
                'bom_item_id' => $bomItem->id,
                'component' => [
                    'id' => $bomItem->component->id,
                    'name' => $bomItem->component->name,
                    'sku' => $bomItem->component->sku,
                    'type' => $bomItem->component->type,
                    'category' => $bomItem->component->category->name,
                    'current_stock' => $bomItem->component->current_stock,
                    'unit_price' => $bomItem->component->unit_price,
                ],
                'quantity' => $bomItem->quantity,
                'unit_of_measure' => $bomItem->unit_of_measure,
                'wastage_percentage' => $bomItem->wastage_percentage,
                'total_quantity_needed' => $bomItem->total_quantity_needed,
                'component_cost' => $componentCost,
                'has_sufficient_stock' => $bomItem->hasSufficientStock(),
                'notes' => $bomItem->notes,
            ];
            
            // Recursively get sub-components if the component has its own BOM
            if ($bomItem->component->has_bom) {
                $componentData['sub_components'] = $this->getBomStructure(
                    $bomItem->component, 
                    $depth + 1, 
                    $maxDepth
                );
            }
            
            $structure['components'][] = $componentData;
        }
        
        return $structure;
    }
    
    /**
     * Calculate total BOM cost for production
     */
    public function calculateBomCost(Product $product, int $quantity = 1): array
    {
        if (!$product->has_bom) {
            return [
                'total_cost' => 0,
                'component_costs' => [],
                'quantity' => $quantity,
            ];
        }
        
        $bomItems = $product->bomItems()->active()->with('component')->get();
        $componentCosts = [];
        $totalCost = 0;
        
        foreach ($bomItems as $bomItem) {
            $componentCost = $bomItem->calculateComponentCost($quantity);
            $totalCost += $componentCost;
            
            $componentCosts[] = [
                'component_id' => $bomItem->component->id,
                'component_name' => $bomItem->component->name,
                'quantity_needed' => $bomItem->getRequiredQuantity($quantity),
                'unit_cost' => $bomItem->component->unit_price,
                'total_cost' => $componentCost,
                'wastage_cost' => $bomItem->wastage_quantity * $bomItem->component->unit_price * $quantity,
            ];
        }
        
        return [
            'total_cost' => $totalCost,
            'component_costs' => $componentCosts,
            'quantity' => $quantity,
            'cost_per_unit' => $quantity > 0 ? $totalCost / $quantity : 0,
        ];
    }
    
    /**
     * Check stock availability for BOM production
     */
    public function checkStockAvailability(Product $product, int $quantity = 1): array
    {
        if (!$product->has_bom) {
            return [
                'available' => true,
                'shortages' => [],
                'total_components' => 0,
            ];
        }
        
        $bomItems = $product->bomItems()->active()->with('component')->get();
        $shortages = [];
        
        foreach ($bomItems as $bomItem) {
            $requiredQuantity = $bomItem->getRequiredQuantity($quantity);
            $availableStock = $bomItem->component->current_stock;
            
            if ($availableStock < $requiredQuantity) {
                $shortages[] = [
                    'component_id' => $bomItem->component->id,
                    'component_name' => $bomItem->component->name,
                    'component_sku' => $bomItem->component->sku,
                    'required_quantity' => $requiredQuantity,
                    'available_stock' => $availableStock,
                    'shortage_quantity' => $requiredQuantity - $availableStock,
                    'unit_of_measure' => $bomItem->unit_of_measure,
                ];
            }
        }
        
        return [
            'available' => empty($shortages),
            'shortages' => $shortages,
            'total_components' => $bomItems->count(),
            'components_with_shortage' => count($shortages),
        ];
    }
    
    /**
     * Get all products that use a specific component
     */
    public function getProductsUsingComponent(Product $component): Collection
    {
        return Product::whereHas('bomItems', function ($query) use ($component) {
            $query->where('component_id', $component->id)->where('is_active', true);
        })->with(['category', 'bomItems' => function ($query) use ($component) {
            $query->where('component_id', $component->id);
        }])->get();
    }
    
    /**
     * Clone BOM from one product to another
     */
    public function cloneBom(Product $sourceProduct, Product $targetProduct): array
    {
        if (!$sourceProduct->has_bom) {
            throw new Exception('Source product does not have a BOM');
        }
        
        DB::beginTransaction();
        
        try {
            $sourceBomItems = $sourceProduct->bomItems()->active()->get();
            $clonedItems = [];
            
            foreach ($sourceBomItems as $sourceBomItem) {
                $clonedItem = BillOfMaterial::create([
                    'product_id' => $targetProduct->id,
                    'component_id' => $sourceBomItem->component_id,
                    'quantity' => $sourceBomItem->quantity,
                    'unit_of_measure' => $sourceBomItem->unit_of_measure,
                    'wastage_percentage' => $sourceBomItem->wastage_percentage,
                    'notes' => $sourceBomItem->notes,
                    'is_active' => true,
                ]);
                
                $clonedItems[] = $clonedItem->load('component');
            }
            
            $targetProduct->update(['has_bom' => true]);
            
            DB::commit();
            
            return [
                'success' => true,
                'cloned_items' => $clonedItems,
                'items_count' => count($clonedItems),
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Check for circular dependencies in BOM
     */
    public function hasCircularDependency(int $productId, int $componentId, array $visited = []): bool
    {
        if (in_array($componentId, $visited)) {
            return true;
        }
        
        $visited[] = $componentId;
        
        $subComponents = BillOfMaterial::where('product_id', $componentId)
            ->where('is_active', true)
            ->pluck('component_id')
            ->toArray();
        
        foreach ($subComponents as $subComponentId) {
            if ($subComponentId === $productId) {
                return true;
            }
            
            if ($this->hasCircularDependency($productId, $subComponentId, $visited)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get BOM explosion report (all levels)
     */
    public function getBomExplosion(Product $product, int $quantity = 1): array
    {
        $explosion = [
            'product' => $product->only(['id', 'name', 'sku']),
            'quantity' => $quantity,
            'levels' => [],
            'total_components' => 0,
            'total_cost' => 0,
        ];
        
        $this->explodeBom($product, $quantity, $explosion, 1);
        
        return $explosion;
    }
    
    /**
     * Recursive function to explode BOM
     */
    private function explodeBom(Product $product, int $quantity, array &$explosion, int $level): void
    {
        if (!$product->has_bom || $level > 10) { // Prevent infinite recursion
            return;
        }
        
        $bomItems = $product->bomItems()->active()->with('component')->get();
        
        if (!isset($explosion['levels'][$level])) {
            $explosion['levels'][$level] = [];
        }
        
        foreach ($bomItems as $bomItem) {
            $requiredQuantity = $bomItem->getRequiredQuantity($quantity);
            $componentCost = $bomItem->calculateComponentCost($quantity);
            
            $componentData = [
                'component_id' => $bomItem->component->id,
                'component_name' => $bomItem->component->name,
                'component_sku' => $bomItem->component->sku,
                'quantity_per_unit' => $bomItem->quantity,
                'total_quantity' => $requiredQuantity,
                'unit_cost' => $bomItem->component->unit_price,
                'total_cost' => $componentCost,
                'unit_of_measure' => $bomItem->unit_of_measure,
                'wastage_percentage' => $bomItem->wastage_percentage,
                'level' => $level,
            ];
            
            $explosion['levels'][$level][] = $componentData;
            $explosion['total_components']++;
            $explosion['total_cost'] += $componentCost;
            
            // Recursively explode sub-components
            if ($bomItem->component->has_bom) {
                $this->explodeBom($bomItem->component, $requiredQuantity, $explosion, $level + 1);
            }
        }
    }
    
    /**
     * Generate BOM comparison report between two products
     */
    public function compareBoms(Product $product1, Product $product2): array
    {
        $bom1 = $this->getBomStructure($product1);
        $bom2 = $this->getBomStructure($product2);
        
        $comparison = [
            'product1' => $bom1['product'],
            'product2' => $bom2['product'],
            'common_components' => [],
            'unique_to_product1' => [],
            'unique_to_product2' => [],
            'cost_difference' => $bom1['total_cost'] - $bom2['total_cost'],
        ];
        
        $components1 = collect($bom1['components'])->keyBy('component.id');
        $components2 = collect($bom2['components'])->keyBy('component.id');
        
        // Find common components
        foreach ($components1 as $componentId => $component1) {
            if ($components2->has($componentId)) {
                $component2 = $components2->get($componentId);
                $comparison['common_components'][] = [
                    'component' => $component1['component'],
                    'quantity_product1' => $component1['quantity'],
                    'quantity_product2' => $component2['quantity'],
                    'quantity_difference' => $component1['quantity'] - $component2['quantity'],
                    'cost_product1' => $component1['component_cost'],
                    'cost_product2' => $component2['component_cost'],
                    'cost_difference' => $component1['component_cost'] - $component2['component_cost'],
                ];
            } else {
                $comparison['unique_to_product1'][] = $component1;
            }
        }
        
        // Find components unique to product2
        foreach ($components2 as $componentId => $component2) {
            if (!$components1->has($componentId)) {
                $comparison['unique_to_product2'][] = $component2;
            }
        }
        
        return $comparison;
    }
}