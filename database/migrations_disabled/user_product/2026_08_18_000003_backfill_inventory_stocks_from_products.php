<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->select(['id', 'quantity', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(100, function ($products) {
                foreach ($products as $product) {
                    DB::table('inventory_stocks')->updateOrInsert(
                        ['product_id' => $product->id],
                        [
                            'quantity' => (int) $product->quantity,
                            'created_at' => $product->created_at ?? now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        DB::table('inventory_stocks')->truncate();
    }
};
