<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'slug')->toArray();
        $units = Unit::pluck('id', 'symbol')->toArray();
        $brandIds = Company::query()->active()->pluck('id')->values();

        $categoryId = fn (string $name) => $categories[Str::slug($name)] ?? reset($categories);
        $unitId = fn (string $symbol) => $units[$symbol] ?? reset($units);

        $products = [
            ['Face Makeup', 'tube', 'LuxeGlow HD Matte Foundation - Warm Beige 30ml', 520, 899, 76],
            ['Face Makeup', 'pcs', 'LuxeGlow Soft Blur Compact Powder - Natural 9g', 260, 499, 95],
            ['Face Makeup', 'pcs', 'ColorMuse Cream Blush Stick - Coral Pop 8g', 310, 649, 63],
            ['Eye Makeup', 'plt', 'ColorMuse 12 Shade Eyeshadow Palette - Sunset Nude', 620, 1299, 42],
            ['Eye Makeup', 'pcs', 'LuxeGlow Waterproof Mascara - Black 12ml', 330, 699, 72],
            ['Eye Makeup', 'pcs', 'Velvet Rose Precision Liquid Eyeliner 2.5ml', 180, 399, 120],
            ['Lip Makeup', 'pcs', 'Velvet Rose Satin Lipstick - Ruby Muse 4g', 220, 549, 84],
            ['Lip Makeup', 'tube', 'LuxeGlow Plump Shine Lip Gloss - Peach 6ml', 190, 449, 91],
            ['Skin Care', 'btl', 'DermaPure Vitamin C Brightening Serum 30ml', 540, 1199, 58],
            ['Skin Care', 'jar', 'DermaPure Ceramide Repair Moisturizer 50g', 430, 949, 66],
            ['Skin Care', 'tube', 'Herbelle Mineral Sunscreen SPF 50 PA++++ 50g', 390, 899, 80],
            ['Hair Care', 'btl', 'SilkStrand Keratin Smooth Shampoo 250ml', 260, 599, 74],
            ['Fragrance', 'btl', 'Urban Aura Eau De Parfum - Bloom 50ml', 780, 1699, 37],
            ['Tools & Accessories', 'set', 'ProBlend 10 Piece Makeup Brush Set', 520, 1199, 46],
            ['Tools & Accessories', 'pcs', 'ProBlend Latex-Free Beauty Sponge', 95, 249, 160],
            ['Body Care', 'btl', 'Herbelle Cocoa Body Lotion 250ml', 220, 499, 92],
        ];

        foreach ($products as $index => $product) {
            Product::updateOrCreate(
                ['sku' => 'COS.'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'category_id' => $categoryId($product[0]),
                    'unit_id' => $unitId($product[1]),
                    'company_id' => $brandIds->isNotEmpty() ? $brandIds[$index % $brandIds->count()] : null,
                    'name' => $product[2],
                    'mrp' => $product[4],
                    'purchase_price' => $product[4],
                    'selling_price' => $product[4],
                    'quantity' => $product[5],
                    'min_stock' => max(8, (int) floor($product[5] * 0.18)),
                    'is_active' => true,
                    'description' => 'Seeded cosmetics inventory item.',
                    'notes' => 'English demo data for cosmetics portal exports and reports.',
                    'image_path' => null,
                ]
            );
        }

        $this->seedBulkProducts($categories, $units, $brandIds);
    }

    private function seedBulkProducts(array $categories, array $units, $brandIds): void
    {
        $categorySlugs = array_values(array_filter(array_keys($categories)));
        $unitSymbols = array_values(array_filter(array_keys($units)));

        if (empty($categorySlugs) || empty($unitSymbols)) {
            return;
        }

        $adjectives = ['Hydrating', 'Matte', 'Glow', 'Silky', 'Velvet', 'Radiant', 'Nourishing', 'Repair', 'Fresh', 'Daily'];
        $types = ['Foundation', 'Compact Powder', 'Lipstick', 'Lip Gloss', 'Mascara', 'Eyeliner', 'Serum', 'Moisturizer', 'Shampoo', 'Body Lotion'];
        $shades = ['Ivory', 'Natural', 'Warm Beige', 'Caramel', 'Rose', 'Coral', 'Ruby', 'Nude', 'Berry', 'Bronze'];
        $sizes = ['5ml', '8g', '10ml', '15ml', '30ml', '50g', '75ml', '100ml', '150ml', '250ml'];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= 2100; $i++) {
            $mrp = 199 + (($i * 37) % 1800);
            $quantity = ($i * 13) % 250;
            $sku = 'DUMMY.'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);

            $rows[] = [
                'category_id' => $categories[$categorySlugs[$i % count($categorySlugs)]],
                'unit_id' => $units[$unitSymbols[$i % count($unitSymbols)]],
                'company_id' => $brandIds->isNotEmpty() ? $brandIds[($i - 1) % $brandIds->count()] : null,
                'sku' => $sku,
                'name' => sprintf(
                    '%s %s - %s %s',
                    $adjectives[$i % count($adjectives)],
                    $types[$i % count($types)],
                    $shades[$i % count($shades)],
                    $sizes[$i % count($sizes)]
                ),
                'mrp' => $mrp,
                'purchase_price' => $mrp,
                'selling_price' => $mrp,
                'quantity' => $quantity,
                'min_stock' => max(5, (int) floor($quantity * 0.15)),
                'is_active' => $i % 20 !== 0,
                'description' => 'Bulk dummy product for product datatable performance testing.',
                'notes' => 'Generated by ProductSeeder.',
                'image_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('products')->upsert($rows, ['sku'], [
                    'category_id',
                    'unit_id',
                    'company_id',
                    'name',
                    'mrp',
                    'purchase_price',
                    'selling_price',
                    'quantity',
                    'min_stock',
                    'is_active',
                    'description',
                    'notes',
                    'image_path',
                    'updated_at',
                ]);

                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('products')->upsert($rows, ['sku'], [
                'category_id',
                'unit_id',
                'company_id',
                'name',
                'mrp',
                'purchase_price',
                'selling_price',
                'quantity',
                'min_stock',
                'is_active',
                'description',
                'notes',
                'image_path',
                'updated_at',
            ]);
        }
    }
}
