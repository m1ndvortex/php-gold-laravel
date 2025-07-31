<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\InventoryService;
use App\Services\BarcodeService;
use App\Services\BomService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private BarcodeService $barcodeService,
        private BomService $bomService
    ) {}

    /**
     * Get all products with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category'])
            ->where('is_active', true);

        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('low_stock')) {
            $query->lowStock();
        }

        if ($request->filled('track_stock')) {
            $query->where('track_stock', $request->boolean('track_stock'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create a new product
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'type' => ['required', Rule::in(['raw_gold', 'finished_jewelry', 'coins', 'stones', 'other'])],
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'gold_weight' => 'nullable|numeric|min:0',
            'stone_weight' => 'nullable|numeric|min:0',
            'manufacturing_cost' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:50',
            'track_stock' => 'boolean',
            'generate_barcode' => 'boolean',
            'specifications' => 'nullable|array',
            'tags' => 'nullable|array',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $product = $this->inventoryService->createProduct($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load(['category']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a specific product
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'bomItems.component', 'stockMovements' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update a product
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'category_id' => 'sometimes|required|exists:product_categories,id',
            'type' => ['sometimes', 'required', Rule::in(['raw_gold', 'finished_jewelry', 'coins', 'stones', 'other'])],
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'sku' => ['nullable', 'string', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', Rule::unique('products', 'barcode')->ignore($product->id)],
            'gold_weight' => 'nullable|numeric|min:0',
            'stone_weight' => 'nullable|numeric|min:0',
            'manufacturing_cost' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:50',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'specifications' => 'nullable|array',
            'tags' => 'nullable|array',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            // Calculate total weight if weights are updated
            if ($request->has('gold_weight') || $request->has('stone_weight')) {
                $goldWeight = $request->get('gold_weight', $product->gold_weight);
                $stoneWeight = $request->get('stone_weight', $product->stone_weight);
                $request->merge(['total_weight' => $goldWeight + $stoneWeight]);
            }

            $product->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->fresh(['category']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a product
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            // Check if product is used in any BOM
            $usedInBom = $this->bomService->getProductsUsingComponent($product);
            if ($usedInBom->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product as it is used as a component in other products',
                    'used_in' => $usedInBom->pluck('name'),
                ], 422);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update product stock
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:add,subtract,adjustment',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'batch_number' => 'nullable|string|max:100',
            'unit_cost' => 'nullable|numeric|min:0',
            'allow_negative' => 'boolean',
        ]);

        try {
            $movement = $this->inventoryService->updateStock(
                $product,
                $request->quantity,
                $request->type,
                $request->only(['reason', 'notes', 'batch_number', 'unit_cost', 'allow_negative'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => [
                    'movement' => $movement,
                    'product' => $product->fresh(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Adjust product stock to specific level
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'new_stock' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $movement = $this->inventoryService->adjustStock(
                $product,
                $request->new_stock,
                $request->reason,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'movement' => $movement,
                    'product' => $product->fresh(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stock movement history for a product
     */
    public function stockHistory(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $movements = $this->inventoryService->getStockHistory(
            $product,
            $request->only(['type', 'date_from', 'date_to'])
        );

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedMovements = $movements->slice($offset, $perPage)->values();
        $total = $movements->count();

        return response()->json([
            'success' => true,
            'data' => [
                'movements' => $paginatedMovements,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * Get products with low stock
     */
    public function lowStock(): JsonResponse
    {
        $products = $this->inventoryService->getLowStockProducts();

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count(),
        ]);
    }

    /**
     * Get inventory valuation report
     */
    public function valuation(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:product_categories,id',
            'type' => 'nullable|in:raw_gold,finished_jewelry,coins,stones,other',
        ]);

        $valuation = $this->inventoryService->getInventoryValuation(
            $request->only(['category_id', 'type'])
        );

        return response()->json([
            'success' => true,
            'data' => $valuation,
        ]);
    }

    /**
     * Generate barcode for product
     */
    public function generateBarcode(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'format' => 'nullable|in:CODE128,EAN13,EAN8,CODE39',
        ]);

        try {
            $barcode = $this->barcodeService->generateBarcode(
                $product,
                $request->get('format', 'CODE128')
            );

            return response()->json([
                'success' => true,
                'data' => $barcode,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate barcode',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate QR code for product
     */
    public function generateQrCode(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'size' => 'nullable|integer|min:50|max:500',
            'include_stock' => 'boolean',
            'include_specs' => 'boolean',
        ]);

        try {
            $qrCode = $this->barcodeService->generateQrCode(
                $product,
                $request->only(['size', 'include_stock', 'include_specs'])
            );

            return response()->json([
                'success' => true,
                'data' => $qrCode,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Scan barcode or QR code
     */
    public function scanCode(Request $request): JsonResponse
    {
        $request->validate([
            'code_data' => 'required|string',
            'type' => 'nullable|in:barcode,qr_code,auto',
        ]);

        try {
            $result = $this->barcodeService->scanCode(
                $request->code_data,
                $request->get('type', 'auto')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scan code',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get product categories
     */
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::active()
            ->with(['parent', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}