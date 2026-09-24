<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('product');
            $table->string('reference')->nullable();
            $table->integer('old_mrp')->nullable();
            $table->integer('new_mrp')->nullable();
            $table->integer('old_purchase_price')->nullable();
            $table->integer('new_purchase_price')->nullable();
            $table->integer('old_selling_price')->nullable();
            $table->integer('new_selling_price')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
