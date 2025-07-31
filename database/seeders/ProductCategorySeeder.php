<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Raw Gold Categories
            [
                'name' => 'طلای خام',
                'name_en' => 'Raw Gold',
                'description' => 'طلای خام و آلیاژهای طلا',
                'description_en' => 'Raw gold and gold alloys',
                'code' => 'RAW_GOLD',
                'type' => 'raw_gold',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'track_purity' => true,
                    'default_unit' => 'gram',
                    'requires_assay' => true,
                ],
            ],
            [
                'name' => 'طلای ۱۸ عیار',
                'name_en' => '18K Gold',
                'description' => 'طلای ۱۸ عیار (۷۵٪ خلوص)',
                'description_en' => '18 Karat Gold (75% purity)',
                'code' => 'GOLD_18K',
                'type' => 'raw_gold',
                'parent_id' => 1,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'purity_percentage' => 75,
                    'karat' => 18,
                ],
            ],
            [
                'name' => 'طلای ۲۱ عیار',
                'name_en' => '21K Gold',
                'description' => 'طلای ۲۱ عیار (۸۷.۵٪ خلوص)',
                'description_en' => '21 Karat Gold (87.5% purity)',
                'code' => 'GOLD_21K',
                'type' => 'raw_gold',
                'parent_id' => 1,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [
                    'purity_percentage' => 87.5,
                    'karat' => 21,
                ],
            ],
            [
                'name' => 'طلای ۲۴ عیار',
                'name_en' => '24K Gold',
                'description' => 'طلای ۲۴ عیار (۹۹.۹٪ خلوص)',
                'description_en' => '24 Karat Gold (99.9% purity)',
                'code' => 'GOLD_24K',
                'type' => 'raw_gold',
                'parent_id' => 1,
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'purity_percentage' => 99.9,
                    'karat' => 24,
                ],
            ],

            // Finished Jewelry Categories
            [
                'name' => 'جواهرات آماده',
                'name_en' => 'Finished Jewelry',
                'description' => 'جواهرات و زیورآلات آماده',
                'description_en' => 'Finished jewelry and ornaments',
                'code' => 'FINISHED_JEWELRY',
                'type' => 'finished_jewelry',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [
                    'requires_sizing' => true,
                    'has_warranty' => true,
                ],
            ],
            [
                'name' => 'انگشتر',
                'name_en' => 'Rings',
                'description' => 'انگشترهای طلا و جواهر',
                'description_en' => 'Gold and jeweled rings',
                'code' => 'RINGS',
                'type' => 'finished_jewelry',
                'parent_id' => 5,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'size_range' => [6, 12],
                    'default_size' => 7,
                ],
            ],
            [
                'name' => 'گردنبند',
                'name_en' => 'Necklaces',
                'description' => 'گردنبندهای طلا و جواهر',
                'description_en' => 'Gold and jeweled necklaces',
                'code' => 'NECKLACES',
                'type' => 'finished_jewelry',
                'parent_id' => 5,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [
                    'length_options' => [16, 18, 20, 22, 24],
                    'default_length' => 18,
                ],
            ],
            [
                'name' => 'گوشواره',
                'name_en' => 'Earrings',
                'description' => 'گوشواره‌های طلا و جواهر',
                'description_en' => 'Gold and jeweled earrings',
                'code' => 'EARRINGS',
                'type' => 'finished_jewelry',
                'parent_id' => 5,
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'closure_types' => ['post', 'hook', 'lever_back', 'clip'],
                ],
            ],
            [
                'name' => 'دستبند',
                'name_en' => 'Bracelets',
                'description' => 'دستبندهای طلا و جواهر',
                'description_en' => 'Gold and jeweled bracelets',
                'code' => 'BRACELETS',
                'type' => 'finished_jewelry',
                'parent_id' => 5,
                'is_active' => true,
                'sort_order' => 4,
                'settings' => [
                    'size_range' => [6, 9],
                    'default_size' => 7,
                ],
            ],

            // Coins Categories
            [
                'name' => 'سکه',
                'name_en' => 'Coins',
                'description' => 'سکه‌های طلا و نقره',
                'description_en' => 'Gold and silver coins',
                'code' => 'COINS',
                'type' => 'coins',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'track_premium' => true,
                    'spot_price_based' => true,
                ],
            ],
            [
                'name' => 'سکه بهار آزادی',
                'name_en' => 'Bahar Azadi Coin',
                'description' => 'سکه بهار آزادی طلا',
                'description_en' => 'Bahar Azadi gold coin',
                'code' => 'BAHAR_AZADI',
                'type' => 'coins',
                'parent_id' => 10,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'weight_grams' => 8.136,
                    'purity_percentage' => 90,
                    'diameter_mm' => 25,
                ],
            ],
            [
                'name' => 'نیم سکه',
                'name_en' => 'Half Coin',
                'description' => 'نیم سکه طلا',
                'description_en' => 'Half gold coin',
                'code' => 'HALF_COIN',
                'type' => 'coins',
                'parent_id' => 10,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [
                    'weight_grams' => 4.068,
                    'purity_percentage' => 90,
                    'diameter_mm' => 20,
                ],
            ],
            [
                'name' => 'ربع سکه',
                'name_en' => 'Quarter Coin',
                'description' => 'ربع سکه طلا',
                'description_en' => 'Quarter gold coin',
                'code' => 'QUARTER_COIN',
                'type' => 'coins',
                'parent_id' => 10,
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'weight_grams' => 2.034,
                    'purity_percentage' => 90,
                    'diameter_mm' => 17,
                ],
            ],

            // Stones Categories
            [
                'name' => 'سنگ‌های قیمتی',
                'name_en' => 'Precious Stones',
                'description' => 'سنگ‌های قیمتی و نیمه قیمتی',
                'description_en' => 'Precious and semi-precious stones',
                'code' => 'STONES',
                'type' => 'stones',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 4,
                'settings' => [
                    'requires_certification' => true,
                    'track_clarity' => true,
                    'track_cut' => true,
                ],
            ],
            [
                'name' => 'الماس',
                'name_en' => 'Diamond',
                'description' => 'الماس طبیعی و مصنوعی',
                'description_en' => 'Natural and synthetic diamonds',
                'code' => 'DIAMOND',
                'type' => 'stones',
                'parent_id' => 14,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    '4c_grading' => true,
                    'certification_required' => true,
                ],
            ],
            [
                'name' => 'یاقوت',
                'name_en' => 'Ruby',
                'description' => 'یاقوت سرخ',
                'description_en' => 'Red ruby',
                'code' => 'RUBY',
                'type' => 'stones',
                'parent_id' => 14,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [
                    'origin_tracking' => true,
                    'heat_treatment' => 'optional',
                ],
            ],
            [
                'name' => 'زمرد',
                'name_en' => 'Emerald',
                'description' => 'زمرد سبز',
                'description_en' => 'Green emerald',
                'code' => 'EMERALD',
                'type' => 'stones',
                'parent_id' => 14,
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'origin_tracking' => true,
                    'oil_treatment' => 'common',
                ],
            ],
            [
                'name' => 'یاقوت کبود',
                'name_en' => 'Sapphire',
                'description' => 'یاقوت کبود آبی',
                'description_en' => 'Blue sapphire',
                'code' => 'SAPPHIRE',
                'type' => 'stones',
                'parent_id' => 14,
                'is_active' => true,
                'sort_order' => 4,
                'settings' => [
                    'origin_tracking' => true,
                    'heat_treatment' => 'common',
                ],
            ],

            // Other Categories
            [
                'name' => 'سایر',
                'name_en' => 'Other',
                'description' => 'سایر اقلام و لوازم جانبی',
                'description_en' => 'Other items and accessories',
                'code' => 'OTHER',
                'type' => 'other',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 5,
                'settings' => [],
            ],
            [
                'name' => 'ابزار و تجهیزات',
                'name_en' => 'Tools & Equipment',
                'description' => 'ابزار و تجهیزات طلاسازی',
                'description_en' => 'Jewelry making tools and equipment',
                'code' => 'TOOLS',
                'type' => 'other',
                'parent_id' => 19,
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [],
            ],
            [
                'name' => 'بسته‌بندی',
                'name_en' => 'Packaging',
                'description' => 'جعبه، کیسه و مواد بسته‌بندی',
                'description_en' => 'Boxes, bags and packaging materials',
                'code' => 'PACKAGING',
                'type' => 'other',
                'parent_id' => 19,
                'is_active' => true,
                'sort_order' => 2,
                'settings' => [],
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}