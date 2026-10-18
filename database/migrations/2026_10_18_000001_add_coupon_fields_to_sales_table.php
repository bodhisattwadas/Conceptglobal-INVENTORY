<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('discount_coupon_id')
                ->nullable()
                ->after('notes')
                ->constrained('discount_coupons')
                ->nullOnDelete();
            $table->string('coupon_code', 50)->nullable()->after('discount_coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['discount_coupon_id']);
            $table->dropColumn(['discount_coupon_id', 'coupon_code']);
        });
    }
};
