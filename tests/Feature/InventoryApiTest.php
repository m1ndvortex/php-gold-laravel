<?php

namespace Tests\Feature;

use Tests\TenantTestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use Laravel\Sanctum\Sanctum;

class InventoryApiTest extends TenantTestCase
{
    private $user;
    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        // Create test category
        $this->category = ProductCategory::create([
            'name' => 'Test Category',
            'name_en' => 'Test Category',
            'code' => 'TEST_CAT',
            'type' => 'finished_jewelry',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /** @test */
    public function it_can_create_product_via_api()
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
            'generate_barcode' => true,
        ];

        $response = $this->postJson('/api/inventory/products', $productData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Product created successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'sku',
                        'barcode',
                        'category_id',
                        'current_stock',
                    ]
                ]);
    }

    /** @test */
    public function it_can_list_products_via_api()
    {
        // Create test products
        Product::factory()->count(3)->create([
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/inventory/products');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'sku',
                                'category',
                                'current_stock',
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_update_stock_via_api()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'track_stock' => true,
        ]);

        $stockData = [
            'type' => 'add',
            'quantity' => 5,
            'reason' => 'Test stock addition',
            'notes' => 'API test',
        ];

        $response = $this->postJson("/api/inventory/products/{$product->id}/stock", $stockData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Stock updated successfully',
                ]);

        $this->assertEquals(15, $product->fresh()->current_stock);
    }

    /** @test */
    public function it_can_generate_barcode_via_api()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'sku' => 'TEST-001',
        ]);

        $response = $this->postJson("/api/inventory/products/{$product->id}/barcode");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'data',
                        'format',
                        'product_id',
                        'image_path',
                    ]
                ]);
    }

    /** @test */
    public function it_can_scan_code_via_api()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'barcode' => 'TEST123456',
        ]);

        $response = $this->postJson('/api/inventory/scan-code', [
            'code_data' => 'TEST123456',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'success' => true,
                        'type' => 'barcode',
                        'data' => 'TEST123456',
                    ]
                ])
                ->assertJsonStructure([
                    'data' => [
                        'product' => [
                            'id',
                            'name',
                            'sku',
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_low_stock_products_via_api()
    {
        // Create low stock product
        Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 2,
            'minimum_stock' => 5,
            'track_stock' => true,
        ]);

        // Create normal stock product
        Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'minimum_stock' => 5,
            'track_stock' => true,
        ]);

        $response = $this->getJson('/api/inventory/low-stock');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'count' => 1,
                ]);
    }

    /** @test */
    public function it_can_get_inventory_valuation_via_api()
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'current_stock' => 10,
            'unit_price' => 1000000,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/inventory/valuation');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'total_value',
                        'total_quantity',
                        'total_products',
                        'category_breakdown',
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_categories_via_api()
    {
        $response = $this->getJson('/api/inventory/categories');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'type',
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_requires_authentication_for_inventory_endpoints()
    {
        // Remove authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/inventory/products');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_product_creation_data()
    {
        $response = $this->postJson('/api/inventory/products', [
            'name' => '', // Invalid: empty name
            'category_id' => 999, // Invalid: non-existent category
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'category_id']);
    }
}