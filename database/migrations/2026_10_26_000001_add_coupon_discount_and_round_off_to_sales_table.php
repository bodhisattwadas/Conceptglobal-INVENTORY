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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('coupon_discount', 18, 2)->default(0)->after('global_discount');
            $table->decimal('round_off', 18, 2)->default(0)->after('coupon_discount');
        });

        // Backfill existing records:
        // Sales with coupon_code or discount_coupon_id:
        DB::table('sales')
            ->whereNotNull('discount_coupon_id')
            ->orWhereNotNull('coupon_code')
            ->update([
                'coupon_discount' => DB::raw('global_discount'),
                'round_off' => 0,
            ]);

        // Sales without coupon where global_discount was applied:
        DB::table('sales')
            ->whereNull('discount_coupon_id')
            ->whereNull('coupon_code')
            ->where('global_discount', '!=', 0)
            ->update([
                'coupon_discount' => 0,
                'round_off' => DB::raw('-global_discount'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['coupon_discount', 'round_off']);
        });
    }
};
