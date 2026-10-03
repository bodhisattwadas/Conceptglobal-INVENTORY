<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['subtotal', 'global_discount', 'total_discount', 'total', 'cash_received', 'change'] as $column) {
                $table->decimal($column, 18, 2)->default(0)->change();
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            foreach (['cost_price', 'unit_price', 'final_price', 'subtotal'] as $column) {
                $table->decimal($column, 18, 2)->change();
            }
            $table->decimal('discount', 18, 2)->default(0)->change();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['subtotal', 'global_discount', 'total_discount', 'total', 'cash_received', 'change'] as $column) {
                $table->bigInteger($column)->default(0)->change();
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            foreach (['cost_price', 'unit_price', 'final_price', 'subtotal'] as $column) {
                $table->bigInteger($column)->change();
            }
            $table->bigInteger('discount')->default(0)->change();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
        });
    }
};