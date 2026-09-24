<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE products MODIFY mrp DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE products MODIFY purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE products MODIFY selling_price DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchases MODIFY total DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchase_items MODIFY unit_price DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchase_items MODIFY selling_price DECIMAL(15,2) NULL');
            DB::statement('ALTER TABLE purchase_items MODIFY subtotal DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE vendor_invoices MODIFY amount DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE vendor_invoices MODIFY paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE product_price_histories MODIFY old_mrp DECIMAL(15,2) NULL');
            DB::statement('ALTER TABLE product_price_histories MODIFY new_mrp DECIMAL(15,2) NULL');
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'currency_fraction_digits'],
            ['value' => '2', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE product_price_histories MODIFY old_mrp INTEGER NULL');
            DB::statement('ALTER TABLE product_price_histories MODIFY new_mrp INTEGER NULL');
            DB::statement('ALTER TABLE vendor_invoices MODIFY paid_amount BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE vendor_invoices MODIFY amount BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchase_items MODIFY subtotal BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchase_items MODIFY selling_price BIGINT NULL');
            DB::statement('ALTER TABLE purchase_items MODIFY unit_price BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE purchases MODIFY total BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE products MODIFY selling_price BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE products MODIFY purchase_price BIGINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE products MODIFY mrp BIGINT NOT NULL DEFAULT 0');
        }

        DB::table('settings')->where('key', 'currency_fraction_digits')->update(['value' => '0', 'updated_at' => now()]);
    }
};
