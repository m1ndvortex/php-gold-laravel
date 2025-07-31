<?php

namespace Tests\Feature;

use Tests\TenantTestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BillOfMaterial;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\BarcodeService;
use App\Services\BomService;

class InventoryManagementTest extends TenantTestCase
{
    private $user;
    private $category;
    private $inventoryService;
    private $barcodeService;
    private $bomService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test category
        $this->category = ProductCategory::create([
            'name' => 'Test Category',
            'name_en' => 'Test Category',
            'code' => 'TEST_CAT',
            'type' => 'finished_jewelry',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        
        // Initialize services
        $this->inventoryService = new InventoryService();
        $this->barcodeService = new BarcodeService();
        $this->bomService = new BomService();
    }

    /** @test */
    public function it_can_create_a_product_with_categorization()
    {
        $productData = [
            'name' => 'Test Gold Ring',
            'name_en' => 'Test Gold Ring',
            'category_id' => $this->category->id,
            'type' => 'finished_jewelry',
            'current_stock' => 10,
            'minimum_stock' => 2,
            'unit_price' => 1000000,
            'selling_price' => 1200000,
            'track_stock' => true,
            'is_active' => true,
            'generate_barcode' => true,
        ];

        $product = $this->inventoryService->createProduct($productData);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Gold Ring', $product->name);
        $this->assertEquals($this->category->id, $product->category_id);
        $this->assertEquals('finished_jewelry', $product->type);
        $this->assertEquals(10, $product->current_stock);
        $this->assertNotNull($product->sku);
        $this->assertNotNull($product->barcode);
        
        // Check if initial stock movement was created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'add',
            'quantity' => 10,
            'reason' => 'Initial Stock',
        ]);
    }

    /** @test */
    public function it_can_update_product_stock_with_movement_tracking()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'track_stock' => true,
        ]);

        $movement = $this->inventoryService->updateStock($product, 5, 'add', [
            'reason' => 'Test stock addition',
            'notes' => 'Testing inventory system'
        ]);

        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertEquals(15, $product->fresh()->current_stock);
        $this->assertEquals(5, $movement->quantity);
        $this->assertEquals('add', $movement->type);
        $this->assertEquals('Test stock addition', $movement->reason);
    }

    /** @test */
    public function it_can_generate_barcode_for_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'sku' => 'TEST-001',
            'barcode' => 'TEST-BARCODE-001',
        ]);

        $barcode = $this->barcodeService->generateBarcode($product);

        $this->assertIsArray($barcode);
        $this->assertArrayHasKey('data', $barcode);
        $this->assertArrayHasKey('format', $barcode);
        $this->assertArrayHasKey('product_id', $barcode);
        $this->assertEquals($product->id, $barcode['product_id']);
        $this->assertEquals('TEST-BARCODE-001', $barcode['data']);
    }

    /** @test */
    public function it_can_generate_qr_code_for_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
        ]);

        $qrCode = $this->barcodeService->generateQrCode($product);

        $this->assertIsArray($qrCode);
        $this->assertArrayHasKey('data', $qrCode);
        $this->assertArrayHasKey('raw_data', $qrCode);
        $this->assertArrayHasKey('product_id', $qrCode);
        
        $rawData = $qrCode['raw_data'];
        $this->assertEquals('product', $rawData['type']);
        $this->assertEquals($product->id, $rawData['id']);
        $this->assertEquals('TEST-001', $rawData['sku']);
    }

    /** @test */
    public function it_can_scan_and_decode_barcode()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'barcode' => 'TEST123456',
        ]);

        $result = $this->barcodeService->scanCode('TEST123456');

        $this->assertTrue($result['success']);
        $this->assertEquals('barcode', $result['type']);
        $this->assertEquals('TEST123456', $result['data']);
        $this->assertEquals($product->id, $result['product']->id);
    }

    /** @test */
    public function it_can_create_and_manage_bill_of_materials()
    {
        // Create parent product and components
        $parentProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Gold Ring',
        ]);

        $goldComponent = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => '18K Gold',
            'type' => 'raw_gold',
            'current_stock' => 100,
            'unit_price' => 2800000,
        ]);

        $components = [
            [
                'component_id' => $goldComponent->id,
                'quantity' => 3.5,
                'unit_of_measure' => 'gram',
                'wastage_percentage' => 5.0,
                'notes' => 'Gold for ring',
                'is_active' => true,
            ]
        ];

        $result = $this->bomService->createOrUpdateBom($parentProduct, $components);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['components_count']);
        $this->assertTrue($parentProduct->fresh()->has_bom);
        
        // Check BOM item was created
        $this->assertDatabaseHas('bill_of_materials', [
            'product_id' => $parentProduct->id,
            'component_id' => $goldComponent->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);
    }

    /** @test */
    public function it_can_check_bom_stock_availability()
    {
        // Create parent product and components
        $parentProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'has_bom' => true,
        ]);

        $goldComponent = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10, // Limited stock
            'unit_price' => 2800000,
        ]);

        // Create BOM
        BillOfMaterial::create([
            'product_id' => $parentProduct->id,
            'component_id' => $goldComponent->id,
            'quantity' => 5.0,
            'wastage_percentage' => 10.0, // Total needed: 5.5
        ]);

        // Check availability for 1 unit (should be available)
        $availability1 = $this->bomService->checkStockAvailability($parentProduct, 1);
        $this->assertTrue($availability1['available']);
        $this->assertEmpty($availability1['shortages']);

        // Check availability for 2 units (should not be available: 2 * 5.5 = 11 > 10)
        $availability2 = $this->bomService->checkStockAvailability($parentProduct, 2);
        $this->assertFalse($availability2['available']);
        $this->assertNotEmpty($availability2['shortages']);
    }

    /** @test */
    public function it_can_process_bom_production()
    {
        // Create parent product and components
        $parentProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'has_bom' => true,
            'current_stock' => 0,
            'track_stock' => true,
        ]);

        $goldComponent = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 20,
            'unit_price' => 2800000,
            'track_stock' => true,
        ]);

        // Create BOM
        BillOfMaterial::create([
            'product_id' => $parentProduct->id,
            'component_id' => $goldComponent->id,
            'quantity' => 5.0,
            'wastage_percentage' => 10.0, // Total needed: 5.5
        ]);

        $result = $this->inventoryService->processBomProduction($parentProduct, 2, [
            'batch_number' => 'BATCH001'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['produced_quantity']);
        
        // Check stock levels
        $this->assertEquals(2, $parentProduct->fresh()->current_stock); // 2 produced
        $this->assertEquals(9, $goldComponent->fresh()->current_stock); // 20 - (2 * 5.5) = 9
        
        // Check stock movements
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $goldComponent->id,
            'type' => 'subtract',
            'reason' => 'BOM Production',
            'batch_number' => 'BATCH001',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $parentProduct->id,
            'type' => 'add',
            'reason' => 'BOM Production',
            'batch_number' => 'BATCH001',
        ]);
    }

    /** @test */
    public function it_can_get_low_stock_products()
    {
        // Create products with different stock levels
        $lowStockProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 2,
            'minimum_stock' => 5,
            'track_stock' => true,
        ]);

        $normalStockProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'minimum_stock' => 5,
            'track_stock' => true,
        ]);

        $lowStockProducts = $this->inventoryService->getLowStockProducts();

        $this->assertEquals(1, $lowStockProducts->count());
        $this->assertEquals($lowStockProduct->id, $lowStockProducts->first()->id);
    }

    /** @test */
    public function it_can_calculate_inventory_valuation()
    {
        // Create products with different values
        Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'unit_price' => 1000000,
            'track_stock' => true,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 5,
            'unit_price' => 2000000,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $valuation = $this->inventoryService->getInventoryValuation();

        $this->assertEquals(20000000, $valuation['total_value']); // (10 * 1M) + (5 * 2M)
        $this->assertEquals(15, $valuation['total_quantity']); // 10 + 5
        $this->assertEquals(2, $valuation['total_products']);
    }

    /** @test */
    public function it_prevents_insufficient_stock_operations()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 5,
            'track_stock' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->inventoryService->updateStock($product, 10, 'subtract');
    }

    /** @test */
    public function it_can_adjust_stock_to_specific_level()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'track_stock' => true,
        ]);

        $movement = $this->inventoryService->adjustStock($product, 15, 'Stock count adjustment');

        $this->assertEquals(15, $product->fresh()->current_stock);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(5, $movement->quantity); // Positive adjustment
        $this->assertEquals('Stock count adjustment', $movement->reason);
    }
}