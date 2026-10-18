<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Face Makeup',
                'description' => 'Foundations, concealers, primers, powders, blush, and contour products.',
            ],
            [
                'name' => 'Eye Makeup',
                'description' => 'Mascara, eyeliner, eyebrow products, eyeshadow palettes, and eye primers.',
            ],
            [
                'name' => 'Lip Makeup',
                'description' => 'Lipstick, gloss, liner, balm, tint, and lip care products.',
            ],
            [
                'name' => 'Skin Care',
                'description' => 'Cleansers, toners, serums, moisturizers, sunscreen, masks, and treatments.',
            ],
            [
                'name' => 'Hair Care',
                'description' => 'Shampoo, conditioner, oils, masks, styling cream, and scalp care.',
            ],
            [
                'name' => 'Fragrance',
                'description' => 'Perfume, body mist, roll-ons, and travel fragrance.',
            ],
            [
                'name' => 'Tools & Accessories',
                'description' => 'Brushes, sponges, applicators, mirrors, pouches, and cosmetic tools.',
            ],
            [
                'name' => 'Body Care',
                'description' => 'Body lotion, scrubs, shower gel, hand cream, and deodorant.',
            ],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
