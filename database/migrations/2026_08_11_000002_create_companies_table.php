<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            return;
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->unique();
            $table->string('company_name')->index();
            $table->string('legal_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('company_type')->nullable();
            $table->foreignId('parent_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('registration_number')->nullable();
            $table->string('gstin')->nullable()->index();
            $table->string('pan')->nullable()->index();
            $table->string('cin')->nullable();
            $table->string('tax_registration_number')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('state')->nullable()->index();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->unsignedBigInteger('base_currency_id')->nullable();
            $table->date('financial_year_start')->nullable();
            $table->unsignedBigInteger('default_payment_terms_id')->nullable();
            $table->unsignedBigInteger('default_purchase_tax_id')->nullable();
            $table->string('default_payable_account')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
