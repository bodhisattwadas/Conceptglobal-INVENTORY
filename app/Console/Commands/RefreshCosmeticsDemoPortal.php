<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\CosmeticsDemoPortalSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshCosmeticsDemoPortal extends Command
{
    protected $signature = 'portal:cosmetics-demo {--force : Run without confirmation}';

    protected $description = 'Clear portal business data, keep user/admin logins, and seed cosmetics demo data.';

    public function handle(): int
    {
        if (! User::query()->exists()) {
            $this->error('No user account found. Create or keep an admin login before running this command.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will delete portal business data but keep user/admin logins. Continue?')) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $this->info('Clearing portal data while preserving users...');

        Schema::disableForeignKeyConstraints();

        if (DB::getDriverName() === 'pgsql') {
            $tables = implode(', ', array_map(fn (string $table) => '"'.$table.'"', $this->tablesToClear()));
            DB::statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");
        } else {
            foreach ($this->tablesToClear() as $table) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->info('Seeding cosmetics demo data...');
        Artisan::call('db:seed', [
            '--class' => CosmeticsDemoPortalSeeder::class,
            '--force' => true,
        ]);

        $this->line(Artisan::output());
        $this->info('Cosmetics demo portal data is ready.');

        return self::SUCCESS;
    }

    private function tablesToClear(): array
    {
        return [
            'finance_transactions',
            'inventory_movements',
            'inventory_stocks',
            'vendor_invoices',
            'product_price_histories',
            'sale_items',
            'sales',
            'purchase_items',
            'purchases',
            'products',
            'finance_categories',
            'customers',
            'supplier_companies',
            'suppliers',
            'categories',
            'units',
        ];
    }
}
