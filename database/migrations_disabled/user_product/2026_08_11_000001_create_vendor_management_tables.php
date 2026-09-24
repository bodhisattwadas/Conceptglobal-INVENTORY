<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term_code')->unique();
            $table->string('term_name');
            $table->unsignedInteger('days')->default(0);
            $table->unsignedInteger('discount_days')->nullable();
            $table->decimal('discount_percent', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('method_code')->unique();
            $table->string('method_name');
            $table->boolean('requires_bank_account')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('rate', 8, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code')->unique();
            $table->string('category_name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

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
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->date('financial_year_start')->nullable();
            $table->foreignId('default_payment_terms_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('default_purchase_tax_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('default_payable_account')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code')->unique();
            $table->string('vendor_name')->index();
            $table->string('legal_name')->nullable()->index();
            $table->string('trade_name')->nullable();
            $table->string('vendor_type')->nullable();
            $table->foreignId('vendor_category_id')->nullable()->constrained('vendor_categories')->nullOnDelete();
            $table->unsignedBigInteger('vendor_group_id')->nullable();
            $table->foreignId('parent_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('business_type')->nullable();
            $table->string('registration_number')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->text('business_description')->nullable();
            $table->string('primary_contact_person')->nullable();
            $table->string('primary_email')->nullable()->index();
            $table->string('accounts_email')->nullable();
            $table->string('po_email')->nullable();
            $table->string('primary_phone')->nullable()->index();
            $table->string('alternate_phone')->nullable();
            $table->foreignId('default_payment_terms_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('default_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->boolean('preferred_vendor')->default(false);
            $table->boolean('purchase_enabled')->default(true);
            $table->boolean('payment_enabled')->default(true);
            $table->boolean('allow_advance_payment')->default(false);
            $table->string('status')->default('draft')->index();
            $table->string('approval_status')->default('draft')->index();
            $table->string('risk_level')->nullable();
            $table->boolean('blocked')->default(false)->index();
            $table->string('block_type')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->text('unblock_reason')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unblocked_at')->nullable();
            $table->boolean('blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendor_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('vendor_code_for_company')->nullable();
            $table->string('account_reference')->nullable();
            $table->foreignId('payment_terms_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->foreignId('credit_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('purchase_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('default_payable_account')->nullable();
            $table->string('default_expense_account')->nullable();
            $table->boolean('purchase_enabled')->default(true);
            $table->boolean('payment_enabled')->default(true);
            $table->boolean('preferred_vendor')->default(false);
            $table->decimal('minimum_order_value', 15, 2)->nullable();
            $table->decimal('free_shipping_threshold', 15, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('price_level')->nullable();
            $table->decimal('discount_percent', 8, 2)->nullable();
            $table->foreignId('withholding_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vendor_id', 'company_id']);
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('contact_type')->nullable();
            $table->string('salutation')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('alternate_phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('secondary_email')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_po')->default(false);
            $table->boolean('receives_payment_advice')->default(false);
            $table->boolean('receives_rfq')->default(false);
            $table->boolean('receives_statement')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('address_type')->nullable();
            $table->string('address_name')->nullable();
            $table->string('attention')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('state')->nullable()->index();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('account_name')->nullable();
            $table->string('bank_name');
            $table->string('bank_branch')->nullable();
            $table->text('account_number_encrypted');
            $table->string('account_number_last4', 4)->index();
            $table->string('account_type')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('swift_bic')->nullable();
            $table->string('iban')->nullable();
            $table->string('micr_code')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('country')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('beneficiary_name')->nullable();
            $table->text('beneficiary_address')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_status')->default('pending');
            $table->date('verification_date')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_tax_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('country')->nullable();
            $table->string('tax_type')->nullable();
            $table->string('tax_registration_number')->nullable()->index();
            $table->string('gstin')->nullable()->index();
            $table->string('pan')->nullable()->index();
            $table->string('vat_number')->nullable();
            $table->string('tin')->nullable();
            $table->boolean('withholding_tax_applicable')->default(false);
            $table->string('withholding_tax_code')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exemption_number')->nullable();
            $table->date('tax_exemption_expiry')->nullable();
            $table->boolean('msme_registered')->default(false);
            $table->string('msme_number')->nullable();
            $table->string('msme_category')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('status')->default('uploaded');
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('vendor_item_code')->nullable();
            $table->string('vendor_item_name')->nullable();
            $table->string('vendor_part_number')->nullable();
            $table->string('manufacturer_part_number')->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->decimal('minimum_order_quantity', 15, 3)->nullable();
            $table->decimal('order_multiple', 15, 3)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedInteger('minimum_lead_time_days')->nullable();
            $table->unsignedInteger('maximum_lead_time_days')->nullable();
            $table->decimal('last_purchase_price', 15, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('preferred')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'item_id']);
        });

        Schema::create('vendor_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('note_type')->default('general');
            $table->string('subject')->nullable();
            $table->text('note');
            $table->string('visibility')->default('internal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->decimal('price_score', 3, 2)->nullable();
            $table->decimal('quality_score', 3, 2)->nullable();
            $table->decimal('delivery_score', 3, 2)->nullable();
            $table->decimal('service_score', 3, 2)->nullable();
            $table->decimal('compliance_score', 3, 2)->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('action')->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs',
            'vendor_status_history',
            'vendor_ratings',
            'vendor_notes',
            'vendor_items',
            'vendor_documents',
            'vendor_tax_details',
            'vendor_bank_accounts',
            'vendor_addresses',
            'vendor_contacts',
            'vendor_companies',
            'vendors',
            'companies',
            'vendor_categories',
            'tax_codes',
            'currencies',
            'payment_methods',
            'payment_terms',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
