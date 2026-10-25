<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanProductBarcodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:clean-barcodes 
                            {--dry-run : Preview matching products without modifying the database}
                            {--force : Apply updates without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract barcode prefixes from product names into the product_code column and clean product names.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $this->info('Scanning products for barcode prefixes in name (e.g. "8906036215017- Strawberry")...');

        $products = Product::query()
            ->select(['id', 'sku', 'product_code', 'name'])
            ->orderBy('id')
            ->get();

        $candidates = [];

        foreach ($products as $product) {
            $name = (string) $product->name;

            // Pattern: 6 to 20 digits followed by dash / en-dash / em-dash and remaining name
            if (preg_match('/^([0-9]{6,20})\s*[-–—]\s*(.+)$/u', $name, $matches)) {
                $barcode = trim($matches[1]);
                $cleanName = trim($matches[2]);

                if (! empty($barcode) && ! empty($cleanName)) {
                    $candidates[] = [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'old_name' => $name,
                        'extracted_barcode' => $barcode,
                        'new_name' => $cleanName,
                        'current_code' => $product->product_code,
                    ];
                }
            }
        }

        $totalCount = count($candidates);

        if ($totalCount === 0) {
            $this->info('No products with barcode prefix patterns were found. Database is already clean.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'SKU', 'Current Name', 'Extracted Barcode', 'New Product Name'],
            array_map(fn ($item) => [
                $item['id'],
                $item['sku'],
                $item['old_name'],
                $item['extracted_barcode'],
                $item['new_name'],
            ], array_slice($candidates, 0, 50))
        );

        if ($totalCount > 50) {
            $this->line('... and '.($totalCount - 50).' more matching products.');
        }

        $this->line('');
        $this->info("Found {$totalCount} candidate product(s).");

        if ($isDryRun) {
            $this->warn('DRY RUN: No database records were modified. Run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm("Are you sure you want to update these {$totalCount} product(s)?", true)) {
            $this->warn('Operation cancelled by user.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates) {
            foreach ($candidates as $item) {
                DB::table('products')
                    ->where('id', $item['id'])
                    ->update([
                        'product_code' => $item['extracted_barcode'],
                        'name' => $item['new_name'],
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info("Successfully cleaned and updated {$totalCount} product(s)!");

        return self::SUCCESS;
    }
}
