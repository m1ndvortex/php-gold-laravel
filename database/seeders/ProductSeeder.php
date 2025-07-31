<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BillOfMaterial;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $rawGold18K = ProductCategory::where('code', 'GOLD_18K')->first();
        $rawGold21K = ProductCategory::where('code', 'GOLD_21K')->first();
        $rings = ProductCategory::where('code', 'RINGS')->first();
        $necklaces = ProductCategory::where('code', 'NECKLACES')->first();
        $baharAzadi = ProductCategory::where('code', 'BAHAR_AZADI')->first();
        $diamond = ProductCategory::where('code', 'DIAMOND')->first();

        // Raw Gold Products
        $gold18K = Product::create([
            'name' => 'طلای ۱۸ عیار',
            'name_en' => '18K Gold Bar',
            'sku' => 'RG-000001',
            'barcode' => '2000000000001',
            'category_id' => $rawGold18K->id,
            'type' => 'raw_gold',
            'description' => 'شمش طلای ۱۸ عیار با خلوص ۷۵٪',
            'description_en' => '18K gold bar with 75% purity',
            'gold_weight' => 10.0,
            'stone_weight' => 0.0,
            'total_weight' => 10.0,
            'manufacturing_cost' => 0.0,
            'current_stock' => 100.0,
            'minimum_stock' => 20.0,
            'maximum_stock' => 500.0,
            'unit_price' => 2800000.0, // Per gram
            'selling_price' => 2850000.0,
            'unit_of_measure' => 'gram',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => false,
            'specifications' => [
                'purity' => '75%',
                'karat' => 18,
                'alloy' => 'copper_silver',
            ],
            'tags' => ['raw_material', 'gold', '18k'],
            'location' => 'Vault-A1',
        ]);

        $gold21K = Product::create([
            'name' => 'طلای ۲۱ عیار',
            'name_en' => '21K Gold Bar',
            'sku' => 'RG-000002',
            'barcode' => '2000000000002',
            'category_id' => $rawGold21K->id,
            'type' => 'raw_gold',
            'description' => 'شمش طلای ۲۱ عیار با خلوص ۸۷.۵٪',
            'description_en' => '21K gold bar with 87.5% purity',
            'gold_weight' => 10.0,
            'stone_weight' => 0.0,
            'total_weight' => 10.0,
            'manufacturing_cost' => 0.0,
            'current_stock' => 80.0,
            'minimum_stock' => 15.0,
            'maximum_stock' => 300.0,
            'unit_price' => 3200000.0, // Per gram
            'selling_price' => 3250000.0,
            'unit_of_measure' => 'gram',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => false,
            'specifications' => [
                'purity' => '87.5%',
                'karat' => 21,
                'alloy' => 'copper_silver',
            ],
            'tags' => ['raw_material', 'gold', '21k'],
            'location' => 'Vault-A2',
        ]);

        // Diamond
        $diamond1ct = Product::create([
            'name' => 'الماس ۱ قیراط',
            'name_en' => '1 Carat Diamond',
            'sku' => 'ST-000001',
            'barcode' => '2000000000003',
            'category_id' => $diamond->id,
            'type' => 'stones',
            'description' => 'الماس طبیعی ۱ قیراط درجه H رنگ VS1 کلاریتی',
            'description_en' => '1 carat natural diamond H color VS1 clarity',
            'gold_weight' => 0.0,
            'stone_weight' => 0.2, // 1 carat = 0.2 grams
            'total_weight' => 0.2,
            'manufacturing_cost' => 0.0,
            'current_stock' => 5.0,
            'minimum_stock' => 2.0,
            'maximum_stock' => 20.0,
            'unit_price' => 25000000.0, // Per piece
            'selling_price' => 28000000.0,
            'unit_of_measure' => 'piece',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => false,
            'specifications' => [
                'carat' => 1.0,
                'color' => 'H',
                'clarity' => 'VS1',
                'cut' => 'Round Brilliant',
                'certification' => 'GIA',
            ],
            'tags' => ['diamond', 'precious_stone', 'certified'],
            'location' => 'Safe-B1',
        ]);

        // Finished Jewelry Products
        $simpleRing = Product::create([
            'name' => 'انگشتر ساده طلا',
            'name_en' => 'Simple Gold Ring',
            'sku' => 'FJ-000001',
            'barcode' => '2000000000004',
            'category_id' => $rings->id,
            'type' => 'finished_jewelry',
            'description' => 'انگشتر ساده طلای ۱۸ عیار',
            'description_en' => 'Simple 18K gold ring',
            'gold_weight' => 3.5,
            'stone_weight' => 0.0,
            'total_weight' => 3.5,
            'manufacturing_cost' => 500000.0,
            'current_stock' => 10.0,
            'minimum_stock' => 3.0,
            'maximum_stock' => 50.0,
            'unit_price' => 12000000.0, // Per piece
            'selling_price' => 15000000.0,
            'unit_of_measure' => 'piece',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => true,
            'specifications' => [
                'size' => 7,
                'width' => '4mm',
                'thickness' => '2mm',
                'finish' => 'polished',
            ],
            'tags' => ['ring', 'simple', 'wedding'],
            'location' => 'Display-C1',
        ]);

        $diamondRing = Product::create([
            'name' => 'انگشتر الماس',
            'name_en' => 'Diamond Ring',
            'sku' => 'FJ-000002',
            'barcode' => '2000000000005',
            'category_id' => $rings->id,
            'type' => 'finished_jewelry',
            'description' => 'انگشتر طلای ۱۸ عیار با الماس ۱ قیراط',
            'description_en' => '18K gold ring with 1 carat diamond',
            'gold_weight' => 4.0,
            'stone_weight' => 0.2,
            'total_weight' => 4.2,
            'manufacturing_cost' => 2000000.0,
            'current_stock' => 2.0,
            'minimum_stock' => 1.0,
            'maximum_stock' => 10.0,
            'unit_price' => 40000000.0, // Per piece
            'selling_price' => 50000000.0,
            'unit_of_measure' => 'piece',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => true,
            'specifications' => [
                'size' => 7,
                'width' => '6mm',
                'setting_type' => 'prong',
                'diamond_carat' => 1.0,
            ],
            'tags' => ['ring', 'diamond', 'engagement', 'luxury'],
            'location' => 'Safe-C1',
        ]);

        $goldNecklace = Product::create([
            'name' => 'گردنبند طلا',
            'name_en' => 'Gold Necklace',
            'sku' => 'FJ-000003',
            'barcode' => '2000000000006',
            'category_id' => $necklaces->id,
            'type' => 'finished_jewelry',
            'description' => 'گردنبند زنجیری طلای ۱۸ عیار',
            'description_en' => '18K gold chain necklace',
            'gold_weight' => 8.0,
            'stone_weight' => 0.0,
            'total_weight' => 8.0,
            'manufacturing_cost' => 800000.0,
            'current_stock' => 5.0,
            'minimum_stock' => 2.0,
            'maximum_stock' => 20.0,
            'unit_price' => 25000000.0, // Per piece
            'selling_price' => 30000000.0,
            'unit_of_measure' => 'piece',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => true,
            'specifications' => [
                'length' => '18 inches',
                'chain_type' => 'cable',
                'clasp_type' => 'spring_ring',
                'width' => '2mm',
            ],
            'tags' => ['necklace', 'chain', 'classic'],
            'location' => 'Display-C2',
        ]);

        // Coin
        $baharAzadiCoin = Product::create([
            'name' => 'سکه بهار آزادی',
            'name_en' => 'Bahar Azadi Gold Coin',
            'sku' => 'CN-000001',
            'barcode' => '2000000000007',
            'category_id' => $baharAzadi->id,
            'type' => 'coins',
            'description' => 'سکه طلای بهار آزادی ۹۰٪ خلوص',
            'description_en' => 'Bahar Azadi gold coin 90% purity',
            'gold_weight' => 8.136,
            'stone_weight' => 0.0,
            'total_weight' => 8.136,
            'manufacturing_cost' => 0.0,
            'current_stock' => 20.0,
            'minimum_stock' => 5.0,
            'maximum_stock' => 100.0,
            'unit_price' => 28000000.0, // Per piece
            'selling_price' => 29000000.0,
            'unit_of_measure' => 'piece',
            'is_active' => true,
            'track_stock' => true,
            'has_bom' => false,
            'specifications' => [
                'year' => '1403',
                'mint' => 'Central Bank of Iran',
                'diameter' => '25mm',
                'purity' => '90%',
            ],
            'tags' => ['coin', 'investment', 'bahar_azadi'],
            'location' => 'Vault-D1',
        ]);

        // Create BOM for Simple Ring
        BillOfMaterial::create([
            'product_id' => $simpleRing->id,
            'component_id' => $gold18K->id,
            'quantity' => 3.8, // Slightly more than final weight for wastage
            'unit_of_measure' => 'gram',
            'wastage_percentage' => 8.0, // 8% wastage during manufacturing
            'notes' => 'Gold required for ring manufacturing',
            'is_active' => true,
        ]);

        // Create BOM for Diamond Ring
        BillOfMaterial::create([
            'product_id' => $diamondRing->id,
            'component_id' => $gold18K->id,
            'quantity' => 4.3, // Slightly more than final weight for wastage
            'unit_of_measure' => 'gram',
            'wastage_percentage' => 7.0, // 7% wastage during manufacturing
            'notes' => 'Gold required for diamond ring setting',
            'is_active' => true,
        ]);

        BillOfMaterial::create([
            'product_id' => $diamondRing->id,
            'component_id' => $diamond1ct->id,
            'quantity' => 1.0,
            'unit_of_measure' => 'piece',
            'wastage_percentage' => 0.0, // No wastage for stones
            'notes' => 'Center diamond for engagement ring',
            'is_active' => true,
        ]);

        // Create BOM for Gold Necklace
        BillOfMaterial::create([
            'product_id' => $goldNecklace->id,
            'component_id' => $gold18K->id,
            'quantity' => 8.5, // Slightly more than final weight for wastage
            'unit_of_measure' => 'gram',
            'wastage_percentage' => 6.0, // 6% wastage during chain manufacturing
            'notes' => 'Gold required for necklace chain',
            'is_active' => true,
        ]);
    }
}