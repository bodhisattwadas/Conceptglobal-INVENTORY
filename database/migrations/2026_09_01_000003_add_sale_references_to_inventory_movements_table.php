<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('purchase_item_id')->constrained()->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable()->after('sale_id')->constrained()->nullOnDelete();

            $table->index(['sale_id', 'created_at']);
            $table->index(['sale_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['sale_item_id']);
            $table->dropForeign(['sale_id']);

            $table->dropIndex(['sale_item_id', 'type']);
            $table->dropIndex(['sale_id', 'created_at']);

            $table->dropColumn(['sale_item_id', 'sale_id']);
        });
    }
};
