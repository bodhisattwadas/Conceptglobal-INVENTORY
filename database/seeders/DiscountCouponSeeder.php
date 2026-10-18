<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\DiscountCoupon;
use Illuminate\Database\Seeder;

class DiscountCouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => CouponType::Percentage,
                'value' => 10,
                'min_purchase_amount' => null,
                'max_discount_amount' => 500,
                'usage_limit' => 100,
                'used_count' => 0,
                'starts_at' => now()->startOfYear()->toDateString(),
                'expires_at' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
                'description' => '10% off on first purchase (max discount ₹500).',
            ],
            [
                'code' => 'SAVE20',
                'type' => CouponType::Percentage,
                'value' => 20,
                'min_purchase_amount' => 1000,
                'max_discount_amount' => 1000,
                'usage_limit' => 50,
                'used_count' => 2,
                'starts_at' => now()->subDays(10)->toDateString(),
                'expires_at' => now()->addMonths(3)->toDateString(),
                'is_active' => true,
                'description' => '20% discount on orders above ₹1,000 (max discount ₹1,000).',
            ],
            [
                'code' => 'FLAT100',
                'type' => CouponType::Fixed,
                'value' => 100,
                'min_purchase_amount' => 500,
                'max_discount_amount' => null,
                'usage_limit' => 200,
                'used_count' => 5,
                'starts_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
                'description' => 'Flat ₹100 discount on minimum purchase of ₹500.',
            ],
            [
                'code' => 'FESTIVE500',
                'type' => CouponType::Fixed,
                'value' => 500,
                'min_purchase_amount' => 2500,
                'max_discount_amount' => null,
                'usage_limit' => 50,
                'used_count' => 0,
                'starts_at' => now()->subDays(5)->toDateString(),
                'expires_at' => now()->addMonths(2)->toDateString(),
                'is_active' => true,
                'description' => 'Festive special: Flat ₹500 off on purchases of ₹2,500 and above.',
            ],
            [
                'code' => 'BEAUTY50',
                'type' => CouponType::Fixed,
                'value' => 50,
                'min_purchase_amount' => null,
                'max_discount_amount' => null,
                'usage_limit' => null,
                'used_count' => 12,
                'starts_at' => now()->subMonths(2)->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'is_active' => true,
                'description' => 'Instant ₹50 off on any purchase with no minimum order.',
            ],
            [
                'code' => 'VIP25',
                'type' => CouponType::Percentage,
                'value' => 25,
                'min_purchase_amount' => 2000,
                'max_discount_amount' => 2000,
                'usage_limit' => 30,
                'used_count' => 1,
                'starts_at' => now()->subWeek()->toDateString(),
                'expires_at' => now()->addMonths(4)->toDateString(),
                'is_active' => true,
                'description' => 'Exclusive 25% discount for VIP club members on orders ₹2,000+.',
            ],
            [
                'code' => 'EXPIRED15',
                'type' => CouponType::Percentage,
                'value' => 15,
                'min_purchase_amount' => 500,
                'max_discount_amount' => 300,
                'usage_limit' => 50,
                'used_count' => 50,
                'starts_at' => now()->subMonths(3)->toDateString(),
                'expires_at' => now()->subDay()->toDateString(),
                'is_active' => true,
                'description' => 'Past promotional campaign (expired).',
            ],
            [
                'code' => 'SUMMER30',
                'type' => CouponType::Percentage,
                'value' => 30,
                'min_purchase_amount' => 1500,
                'max_discount_amount' => 1500,
                'usage_limit' => 100,
                'used_count' => 0,
                'starts_at' => now()->addMonths(1)->toDateString(),
                'expires_at' => now()->addMonths(3)->toDateString(),
                'is_active' => false,
                'description' => 'Upcoming summer promotional discount (currently inactive).',
            ],
        ];

        foreach ($coupons as $coupon) {
            DiscountCoupon::updateOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
