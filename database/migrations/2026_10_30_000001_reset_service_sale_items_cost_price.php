<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sale_items')
            ->whereNotNull('service_id')
            ->update(['cost_price' => 0.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: service items do not have merchandise cost of goods.
    }
};
