<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            // Independent/base data.
            UserSeeder::class,
            UnitSeeder::class,
            //CategorySeeder::class,
            //BrandCompanySeeder::class,
            //FinanceCategorySeeder::class,
            SettingSeeder::class,

            // Data that depends on the base records above.
            //CustomerSeeder::class,
            //SupplierSeeder::class,
           // ProductSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
