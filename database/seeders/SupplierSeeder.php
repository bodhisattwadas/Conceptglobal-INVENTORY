<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyIds = Company::query()
            ->where('status', 'active')
            ->pluck('id');

        Supplier::factory()
            ->count(25)
            ->create()
            ->each(function (Supplier $supplier) use ($companyIds): void {
                if ($companyIds->isEmpty()) {
                    return;
                }

                $supplier->companies()->sync(
                    $companyIds->random(min(fake()->numberBetween(2, 5), $companyIds->count()))->all()
                );
            });
    }
}
