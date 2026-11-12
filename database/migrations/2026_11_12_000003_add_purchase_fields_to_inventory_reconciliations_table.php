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
        Schema::table('inventory_reconciliations', function (Blueprint $table) {
            $table->foreignId('target_purchase_id')->nullable()->after('reconciled_purchase_item_id')->constrained('purchases')->nullOnDelete();
            $table->foreignId('reconciled_purchase_id')->nullable()->after('target_purchase_id')->constrained('purchases')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_reconciliations', function (Blueprint $table) {
            $table->dropForeign(['target_purchase_id']);
            $table->dropColumn('target_purchase_id');
            $table->dropForeign(['reconciled_purchase_id']);
            $table->dropColumn('reconciled_purchase_id');
        });
    }
};
