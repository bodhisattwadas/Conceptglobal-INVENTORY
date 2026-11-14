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
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->string('transaction_type', 32)->index(); // cash_sale, cash_investment, expense, drawing
            $table->string('reference_number', 100)->nullable(); // Num
            $table->string('name', 255)->nullable(); // Payee / Customer / Investor / Owner
            $table->text('description')->nullable();
            $table->string('account_name', 150)->default('1100 Cash and Cash Equivalent');
            $table->string('split_account', 150)->nullable(); // Category / Item split account
            $table->foreignId('finance_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->decimal('amount', 18, 2); // Raw positive transaction amount
            $table->nullableMorphs('reference'); // reference_type, reference_id
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['transaction_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
