<?php

namespace Database\Seeders;

use App\Enums\FinanceCategoryType;
use App\Models\FinanceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinanceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Income
            [
                'name' => 'Product Sales',
                'type' => FinanceCategoryType::Income,
                'description' => 'Direct income from store product sales.',
            ],
            [
                'name' => 'Service Revenue',
                'type' => FinanceCategoryType::Income,
                'description' => 'Income from service work or consultation.',
            ],
            [
                'name' => 'Investasi',
                'type' => FinanceCategoryType::Income,
                'description' => 'Dividends or interest from capital investments.',
            ],
            [
                'name' => 'Other Income',
                'type' => FinanceCategoryType::Income,
                'description' => 'Income outside core operations.',
            ],

            // Expenses
            [
                'name' => 'Employee Salaries',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Monthly salary and employee allowance costs.',
            ],
            [
                'name' => 'Building Rent',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Store or operational warehouse rental costs.',
            ],
            [
                'name' => 'Electricity & Water',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Monthly utility bills.',
            ],
            [
                'name' => 'Internet & Phone',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Communication and internet connection costs.',
            ],
            [
                'name' => 'Marketing & Advertising',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Promotion, social media advertising, and print advertising costs.',
            ],
            [
                'name' => 'Maintenance & Repairs',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Asset and equipment maintenance costs.',
            ],
            [
                'name' => 'Transportasi & Logistik',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Fuel, delivery, and business travel costs.',
            ],
            [
                'name' => 'Inventory Purchases',
                'type' => FinanceCategoryType::Expense,
                'description' => 'Cost of merchandise inventory purchases.',
            ],
        ];

        foreach ($categories as $category) {
            FinanceCategory::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'type' => $category['type'],
                'description' => $category['description'],
            ]);
        }
    }
}
