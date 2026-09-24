<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->date('order_received_date')->nullable()->after('invoice_date');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->date('manufacturing_date')->nullable()->after('received_quantity');
            $table->date('expiry_date')->nullable()->after('manufacturing_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['manufacturing_date', 'expiry_date']);
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('order_received_date');
        });
    }
};
