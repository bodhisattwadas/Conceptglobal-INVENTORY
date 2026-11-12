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
        Schema::create('inventory_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_number', 50)->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name');
            $table->string('barcode')->nullable()->index();
            $table->integer('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('reconciled_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('reconciled_purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reconciliations');
    }
};
