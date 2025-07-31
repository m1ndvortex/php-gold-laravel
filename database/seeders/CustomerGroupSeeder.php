<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'مشتریان عادی',
                'name_en' => 'Regular Customers',
                'description' => 'مشتریان عادی بدون تخفیف خاص',
                'description_en' => 'Regular customers without special discount',
                'discount_percentage' => 0,
                'credit_limit_multiplier' => 1.0,
                'is_active' => true,
                'settings' => [
                    'default_payment_terms' => 30,
                    'auto_approve_credit' => false,
                ],
            ],
            [
                'name' => 'مشتریان VIP',
                'name_en' => 'VIP Customers',
                'description' => 'مشتریان ویژه با تخفیف و اعتبار بالا',
                'description_en' => 'VIP customers with discount and high credit',
                'discount_percentage' => 5.0,
                'credit_limit_multiplier' => 2.0,
                'is_active' => true,
                'settings' => [
                    'default_payment_terms' => 60,
                    'auto_approve_credit' => true,
                    'priority_support' => true,
                ],
            ],
            [
                'name' => 'عمده فروشان',
                'name_en' => 'Wholesalers',
                'description' => 'مشتریان عمده فروش با تخفیف ویژه',
                'description_en' => 'Wholesale customers with special discount',
                'discount_percentage' => 10.0,
                'credit_limit_multiplier' => 3.0,
                'is_active' => true,
                'settings' => [
                    'default_payment_terms' => 45,
                    'auto_approve_credit' => true,
                    'bulk_pricing' => true,
                    'minimum_order_amount' => 10000,
                ],
            ],
            [
                'name' => 'طلافروشان',
                'name_en' => 'Jewelers',
                'description' => 'سایر طلافروشان و همکاران تجاری',
                'description_en' => 'Other jewelers and business partners',
                'discount_percentage' => 7.5,
                'credit_limit_multiplier' => 2.5,
                'is_active' => true,
                'settings' => [
                    'default_payment_terms' => 30,
                    'auto_approve_credit' => false,
                    'trade_discount' => true,
                ],
            ],
            [
                'name' => 'مشتریان غیرفعال',
                'name_en' => 'Inactive Customers',
                'description' => 'مشتریان غیرفعال یا معوق',
                'description_en' => 'Inactive or delinquent customers',
                'discount_percentage' => 0,
                'credit_limit_multiplier' => 0,
                'is_active' => false,
                'settings' => [
                    'default_payment_terms' => 0,
                    'auto_approve_credit' => false,
                    'require_prepayment' => true,
                ],
            ],
        ];

        foreach ($groups as $group) {
            \App\Models\CustomerGroup::create($group);
        }
    }
}
