<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {
            $table->dropColumn([
                'old_purchase_price',
                'new_purchase_price',
                'old_selling_price',
                'new_selling_price',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {
            $table->integer('old_purchase_price')->nullable()->after('new_mrp');
            $table->integer('new_purchase_price')->nullable()->after('old_purchase_price');
            $table->integer('old_selling_price')->nullable()->after('new_purchase_price');
            $table->integer('new_selling_price')->nullable()->after('old_selling_price');
        });
    }
};
