<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = Category::pluck('id')->toArray();
        $units = Unit::pluck('id')->toArray();
        $companies = Company::pluck('id')->toArray();
        $mrp = fake()->numberBetween(10000, 1000000);

        return [
            'category_id' => ! empty($categories) ? fake()->randomElement($categories) : Category::factory(),
            'unit_id' => ! empty($units) ? fake()->randomElement($units) : Unit::factory(),
            'company_id' => ! empty($companies) ? fake()->randomElement($companies) : null,
            'sku' => 'P.'.date('ymd').'.'.strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'mrp' => $mrp,
            'purchase_price' => $mrp,
            'selling_price' => $mrp,
            'quantity' => fake()->numberBetween(0, 100),
            'min_stock' => fake()->numberBetween(5, 20),
            'is_active' => fake()->boolean(90),
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
