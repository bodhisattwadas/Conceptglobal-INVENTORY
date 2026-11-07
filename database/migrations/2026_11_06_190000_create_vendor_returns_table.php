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
        Schema::create('vendor_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('location')->default('shop');
            $table->date('return_date');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('completed'); // completed, pending, cancelled
            $table->string('reason')->default('damaged'); // damaged, expired, defective, wrong_item, unsold_return, other
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['supplier_id', 'return_date']);
            $table->index(['purchase_id']);
        });

        Schema::create('vendor_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_return_id')->constrained('vendor_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['vendor_return_id']);
            $table->index(['product_id']);
            $table->index(['purchase_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_return_items');
        Schema::dropIfExists('vendor_returns');
    }
};
