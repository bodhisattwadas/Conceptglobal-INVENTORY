<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! $this->indexExists('products', 'products_created_at_index')) {
                $table->index('created_at', 'products_created_at_index');
            }

            if (! $this->indexExists('products', 'products_is_active_index')) {
                $table->index('is_active', 'products_is_active_index');
            }

            if (! $this->indexExists('products', 'products_mrp_index')) {
                $table->index('mrp', 'products_mrp_index');
            }

            if (! $this->indexExists('products', 'products_min_stock_index')) {
                $table->index('min_stock', 'products_min_stock_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if ($this->indexExists('products', 'products_created_at_index')) {
                $table->dropIndex('products_created_at_index');
            }

            if ($this->indexExists('products', 'products_is_active_index')) {
                $table->dropIndex('products_is_active_index');
            }

            if ($this->indexExists('products', 'products_mrp_index')) {
                $table->dropIndex('products_mrp_index');
            }

            if ($this->indexExists('products', 'products_min_stock_index')) {
                $table->dropIndex('products_min_stock_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains('name', $index);
    }
};
