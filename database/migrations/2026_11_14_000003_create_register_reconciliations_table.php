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
        Schema::create('register_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('register_type', 32)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->date('reconciliation_date')->index();
            $table->decimal('beginning_balance', 18, 2);
            $table->decimal('total_inflows', 18, 2)->default(0);
            $table->decimal('total_outflows', 18, 2)->default(0);
            $table->decimal('recorded_balance', 18, 2);
            $table->decimal('actual_balance', 18, 2);
            $table->decimal('difference', 18, 2);
            $table->string('status', 32)->index();
            $table->text('notes')->nullable();
            $table->json('denominations')->nullable();
            $table->foreignId('reconciled_by')->constrained('users');
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['register_type', 'year', 'month'], 'reg_recon_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('register_reconciliations');
    }
};
