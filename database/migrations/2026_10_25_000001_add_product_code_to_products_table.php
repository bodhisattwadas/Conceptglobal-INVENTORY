<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'product_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('product_code', 100)->nullable()->after('sku')->index();
            });
        }

        // Clean and backfill any existing products where the barcode was entered in the name field
        $this->backfillExistingProductCodes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'product_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['product_code']);
                $table->dropColumn('product_code');
            });
        }
    }

    /**
     * Extract barcode prefixes (e.g. "8906036215017- Strawberry Hydrating...") into product_code.
     */
    private function backfillExistingProductCodes(): void
    {
        // Fetch candidates where product_code is null or empty
        $candidates = DB::table('products')
            ->whereNull('product_code')
            ->orWhere('product_code', '')
            ->select(['id', 'name'])
            ->get();

        foreach ($candidates as $candidate) {
            $name = (string) $candidate->name;
            // Pattern matches 6 to 20 digit barcodes followed by a dash (standard, en-dash, or em-dash) and remaining name
            if (preg_match('/^([0-9]{6,20})\s*[-–—]\s*(.+)$/u', $name, $matches)) {
                $barcode = trim($matches[1]);
                $cleanName = trim($matches[2]);

                if (! empty($barcode) && ! empty($cleanName)) {
                    DB::table('products')
                        ->where('id', $candidate->id)
                        ->update([
                            'product_code' => $barcode,
                            'name' => $cleanName,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }
};
