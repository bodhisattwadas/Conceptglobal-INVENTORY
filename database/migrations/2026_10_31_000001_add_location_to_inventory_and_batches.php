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
        // 1. Add location to purchase_items
        if (Schema::hasTable('purchase_items') && ! Schema::hasColumn('purchase_items', 'location')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->string('location')->nullable()->after('product_id')->index();
            });

            // Backfill from purchases table
            if (DB::getDriverName() === 'mysql') {
                DB::statement('
                    UPDATE purchase_items
                    JOIN purchases ON purchase_items.purchase_id = purchases.id
                    SET purchase_items.location = purchases.location
                    WHERE purchases.location IS NOT NULL
                ');
            } else {
                DB::statement('
                    UPDATE purchase_items
                    SET location = (SELECT location FROM purchases WHERE purchases.id = purchase_items.purchase_id)
                    WHERE EXISTS (SELECT 1 FROM purchases WHERE purchases.id = purchase_items.purchase_id AND purchases.location IS NOT NULL)
                ');
            }

            // Default any remaining nulls to 'shop'
            DB::table('purchase_items')->whereNull('location')->update(['location' => 'shop']);
        }

        // 2. Add location to products
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'location')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('location')->default('shop')->after('min_stock')->index();
            });
        }

        // 3. Add location to inventory_movements
        if (Schema::hasTable('inventory_movements') && ! Schema::hasColumn('inventory_movements', 'location')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->string('location')->nullable()->after('type')->index();
            });

            // Backfill from purchases if movement is linked to purchase
            if (DB::getDriverName() === 'mysql') {
                DB::statement('
                    UPDATE inventory_movements
                    JOIN purchases ON inventory_movements.purchase_id = purchases.id
                    SET inventory_movements.location = purchases.location
                    WHERE purchases.location IS NOT NULL
                ');
            } else {
                DB::statement('
                    UPDATE inventory_movements
                    SET location = (SELECT location FROM purchases WHERE purchases.id = inventory_movements.purchase_id)
                    WHERE EXISTS (SELECT 1 FROM purchases WHERE purchases.id = inventory_movements.purchase_id AND purchases.location IS NOT NULL)
                ');
            }
        }

        // 4. Add source_location & target_location to inventory_adjustments
        if (Schema::hasTable('inventory_adjustments')) {
            Schema::table('inventory_adjustments', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_adjustments', 'source_location')) {
                    $table->string('source_location')->nullable()->after('type');
                }
                if (! Schema::hasColumn('inventory_adjustments', 'target_location')) {
                    $table->string('target_location')->nullable()->after('source_location');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'location')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'location')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'location')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }

        if (Schema::hasTable('inventory_adjustments')) {
            Schema::table('inventory_adjustments', function (Blueprint $table) {
                $columns = array_filter(['source_location', 'target_location'], fn ($col) => Schema::hasColumn('inventory_adjustments', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
