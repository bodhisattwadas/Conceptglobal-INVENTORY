<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('mrp', 15, 2)->default(0)->change();
            $table->decimal('purchase_price', 15, 2)->default(0)->change();
            $table->decimal('selling_price', 15, 2)->default(0)->change();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('total', 15, 2)->default(0)->change();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->change();
            $table->decimal('selling_price', 15, 2)->nullable()->change();
            $table->decimal('subtotal', 15, 2)->default(0)->change();
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0)->change();
            $table->decimal('paid_amount', 15, 2)->default(0)->change();
        });

        Schema::table('product_price_histories', function (Blueprint $table) {
            $table->decimal('old_mrp', 15, 2)->nullable()->change();
            $table->decimal('new_mrp', 15, 2)->nullable()->change();
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'currency_fraction_digits'],
            ['value' => '2', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {
            $table->integer('old_mrp')->nullable()->change();
            $table->integer('new_mrp')->nullable()->change();
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->bigInteger('paid_amount')->default(0)->change();
            $table->bigInteger('amount')->default(0)->change();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->bigInteger('subtotal')->default(0)->change();
            $table->bigInteger('selling_price')->nullable()->change();
            $table->bigInteger('unit_price')->default(0)->change();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->bigInteger('total')->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('selling_price')->default(0)->change();
            $table->bigInteger('purchase_price')->default(0)->change();
            $table->bigInteger('mrp')->default(0)->change();
        });

        DB::table('settings')->where('key', 'currency_fraction_digits')->update(['value' => '0', 'updated_at' => now()]);
    }
};
