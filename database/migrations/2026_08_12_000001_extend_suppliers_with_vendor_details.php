<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'legal_name')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('legal_name')->nullable()->after('name');
                $table->string('trade_name')->nullable()->after('legal_name');
                $table->string('supplier_type')->nullable()->after('trade_name');
                $table->string('registration_number')->nullable()->after('supplier_type');
                $table->string('tax_id')->nullable()->after('registration_number');
                $table->string('website')->nullable()->after('tax_id');
                $table->string('industry')->nullable()->after('website');
                $table->string('alternate_phone')->nullable()->after('phone');
                $table->string('accounts_email')->nullable()->after('email');
                $table->string('purchase_email')->nullable()->after('accounts_email');
                $table->string('address_line_1')->nullable()->after('address');
                $table->string('address_line_2')->nullable()->after('address_line_1');
                $table->string('city')->nullable()->after('address_line_2');
                $table->string('state')->nullable()->after('city');
                $table->string('postal_code')->nullable()->after('state');
                $table->string('country')->nullable()->after('postal_code');
                $table->string('bank_name')->nullable()->after('country');
                $table->string('bank_branch')->nullable()->after('bank_name');
                $table->string('account_name')->nullable()->after('bank_branch');
                $table->text('account_number_encrypted')->nullable()->after('account_name');
                $table->string('account_number_last4', 4)->nullable()->index()->after('account_number_encrypted');
                $table->string('account_type')->nullable()->after('account_number_last4');
                $table->string('ifsc_code')->nullable()->after('account_type');
                $table->string('swift_bic')->nullable()->after('ifsc_code');
                $table->string('beneficiary_name')->nullable()->after('swift_bic');
                $table->string('bank_country')->nullable()->after('beneficiary_name');
                $table->string('status')->default('active')->index()->after('bank_country');
            });
        }

        if (! Schema::hasTable('supplier_companies')) {
            Schema::create('supplier_companies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['supplier_id', 'company_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_companies');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'trade_name',
                'supplier_type',
                'registration_number',
                'tax_id',
                'website',
                'industry',
                'alternate_phone',
                'accounts_email',
                'purchase_email',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
                'bank_name',
                'bank_branch',
                'account_name',
                'account_number_encrypted',
                'account_number_last4',
                'account_type',
                'ifsc_code',
                'swift_bic',
                'beneficiary_name',
                'bank_country',
                'status',
            ]);
        });
    }
};
