<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\BillOfMaterial;
use App\Services\BomService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BomController extends Controller
{
    public function __construct(
        private BomService $bomService,
        private InventoryService $inventoryService
    ) {}

    /**
     * Get BOM structure for a product
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $bomStructure = $this->bomService->getBomStructure($product);

            return response()->json([
                'success' => true,
                'data' => $bomStructure,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get BOM structure',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create or update BOM for a product
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'components' => 'required|array|min:1',
            'components.*.component_id' => 'required|exists:products,id',
            'components.*.quantity' => 'required|numeric|min:0.001',
            'components.*.unit_of_measure' => 'nullable|string|max:50',
            'components.*.wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'components.*.notes' => 'nullable|string',
            'components.*.is_active' => 'boolean',
        ]);

        try {
            $result = $this->bomService->createOrUpdateBom($product, $request->components);

            return response()->json([
                'success' => true,
                'message' => 'BOM created/updated successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create/update BOM',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Calculate BOM cost for production
     */
    public function calculateCost(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $quantity = $request->get('quantity', 1);
            $costCalculation = $this->bomService->calculateBomCost($product, $quantity);

            return response()->json([
                'success' => true,
                'data' => $costCalculation,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate BOM cost',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check stock availability for BOM production
     */
    public function checkStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $quantity = $request->get('quantity', 1);
            $stockCheck = $this->bomService->checkStockAvailability($product, $quantity);

            return response()->json([
                'success' => true,
                'data' => $stockCheck,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check stock availability',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process BOM production
     */
    public function processProduction(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'batch_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = $this->inventoryService->processBomProduction(
                $product,
                $request->quantity,
                $request->only(['batch_number', 'notes'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Production processed successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process production',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get products that use a specific component
     */
    public function getProductsUsingComponent(Product $component): JsonResponse
    {
        try {
            $products = $this->bomService->getProductsUsingComponent($component);

            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => $products->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get products using component',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Clone BOM from one product to another
     */
    public function cloneBom(Request $request, Product $sourceProduct): JsonResponse
    {
        $request->validate([
            'target_product_id' => 'required|exists:products,id',
        ]);

        try {
            $targetProduct = Product::findOrFail($request->target_product_id);
            $result = $this->bomService->cloneBom($sourceProduct, $targetProduct);

            return response()->json([
                'success' => true,
                'message' => 'BOM cloned successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone BOM',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get BOM explosion report
     */
    public function explosion(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $quantity = $request->get('quantity', 1);
            $explosion = $this->bomService->getBomExplosion($product, $quantity);

            return response()->json([
                'success' => true,
                'data' => $explosion,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get BOM explosion',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Compare BOMs between two products
     */
    public function compare(Request $request, Product $product1): JsonResponse
    {
        $request->validate([
            'product2_id' => 'required|exists:products,id',
        ]);

        try {
            $product2 = Product::findOrFail($request->product2_id);
            $comparison = $this->bomService->compareBoms($product1, $product2);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare BOMs',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a specific BOM item
     */
    public function updateBomItem(Request $request, BillOfMaterial $bomItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'sometimes|required|numeric|min:0.001',
            'unit_of_measure' => 'nullable|string|max:50',
            'wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        try {
            $bomItem->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'BOM item updated successfully',
                'data' => $bomItem->fresh(['component']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update BOM item',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a BOM item
     */
    public function deleteBomItem(BillOfMaterial $bomItem): JsonResponse
    {
        try {
            $bomItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'BOM item deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete BOM item',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}