<?php

namespace Tests\Feature;

use Tests\TenantTestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BillOfMaterial;
use Laravel\Sanctum\Sanctum;

class BomApiTest extends TenantTestCase
{
    private $user;
    private $category;
    private $parentProduct;
    private $componentProduct;

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

        // Create parent product
        $this->parentProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Gold Ring',
            'has_bom' => false,
            'current_stock' => 0, // Start with 0 stock
            'track_stock' => true,
        ]);

        // Create component product
        $this->componentProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => '18K Gold',
            'type' => 'raw_gold',
            'current_stock' => 100,
            'unit_price' => 2800000,
            'track_stock' => true,
        ]);
    }

    /** @test */
    public function it_can_create_bom_via_api()
    {
        $bomData = [
            'components' => [
                [
                    'component_id' => $this->componentProduct->id,
                    'quantity' => 3.5,
                    'unit_of_measure' => 'gram',
                    'wastage_percentage' => 5.0,
                    'notes' => 'Gold for ring',
                    'is_active' => true,
                ]
            ]
        ];

        $response = $this->postJson("/api/bom/products/{$this->parentProduct->id}", $bomData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'BOM created/updated successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'success',
                        'bom_items',
                        'components_count',
                    ]
                ]);

        $this->assertTrue($this->parentProduct->fresh()->has_bom);
    }

    /** @test */
    public function it_can_get_bom_structure_via_api()
    {
        // Create BOM first
        BillOfMaterial::create([
            'product_id' => $this->parentProduct->id,
            'component_id' => $this->componentProduct->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);

        $this->parentProduct->update(['has_bom' => true]);

        $response = $this->getJson("/api/bom/products/{$this->parentProduct->id}");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'product',
                        'components',
                        'total_cost',
                    ]
                ]);
    }

    /** @test */
    public function it_can_calculate_bom_cost_via_api()
    {
        // Create BOM first
        BillOfMaterial::create([
            'product_id' => $this->parentProduct->id,
            'component_id' => $this->componentProduct->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);

        $this->parentProduct->update(['has_bom' => true]);

        $response = $this->getJson("/api/bom/products/{$this->parentProduct->id}/cost?quantity=2");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'total_cost',
                        'component_costs',
                        'quantity',
                        'cost_per_unit',
                    ]
                ]);
    }

    /** @test */
    public function it_can_check_bom_stock_availability_via_api()
    {
        // Create BOM first
        BillOfMaterial::create([
            'product_id' => $this->parentProduct->id,
            'component_id' => $this->componentProduct->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);

        $this->parentProduct->update(['has_bom' => true]);

        $response = $this->getJson("/api/bom/products/{$this->parentProduct->id}/stock-check?quantity=10");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'available',
                        'shortages',
                        'total_components',
                    ]
                ]);
    }

    /** @test */
    public function it_can_process_bom_production_via_api()
    {
        // Create BOM first
        BillOfMaterial::create([
            'product_id' => $this->parentProduct->id,
            'component_id' => $this->componentProduct->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);

        $this->parentProduct->update(['has_bom' => true]);

        $productionData = [
            'quantity' => 2,
            'batch_number' => 'BATCH001',
            'notes' => 'Test production',
        ];

        $response = $this->postJson("/api/bom/products/{$this->parentProduct->id}/produce", $productionData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Production processed successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'success',
                        'movements',
                        'produced_quantity',
                    ]
                ]);

        // Check that stock was updated
        $updatedProduct = $this->parentProduct->fresh();
        $this->assertEquals(2, $updatedProduct->current_stock);
    }

    /** @test */
    public function it_can_get_bom_explosion_via_api()
    {
        // Create BOM first
        BillOfMaterial::create([
            'product_id' => $this->parentProduct->id,
            'component_id' => $this->componentProduct->id,
            'quantity' => 3.5,
            'wastage_percentage' => 5.0,
        ]);

        $this->parentProduct->update(['has_bom' => true]);

        $response = $this->getJson("/api/bom/products/{$this->parentProduct->id}/explosion?quantity=5");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'product',
                        'quantity',
                        'levels',
                        'total_components',
                        'total_cost',
                    ]
                ]);
    }

    /** @test */
    public function it_validates_bom_creation_data()
    {
        $response = $this->postJson("/api/bom/products/{$this->parentProduct->id}", [
            'components' => [
                [
                    'component_id' => 999, // Invalid: non-existent component
                    'quantity' => -1, // Invalid: negative quantity
                ]
            ]
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['components.0.component_id', 'components.0.quantity']);
    }
}