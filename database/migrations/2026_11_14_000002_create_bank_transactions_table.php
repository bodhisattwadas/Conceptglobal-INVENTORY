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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->string('transaction_type', 32)->index();
            $table->string('reference_number', 100)->nullable();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('account_name', 150)->default('1200 Bank Account');
            $table->string('split_account', 150)->nullable();
            $table->foreignId('finance_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->nullableMorphs('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['transaction_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
