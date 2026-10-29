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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique();
            $table->string('type')->default('reallocation'); // reallocation, quantity_decrease, quantity_increase
            $table->foreignId('source_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('source_purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->foreignId('target_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('target_purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['source_product_id', 'created_at']);
            $table->index(['target_product_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
