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
        if (! Schema::hasColumn('products', 'hsn_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('hsn_code', 50)->nullable()->after('product_code')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'hsn_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['hsn_code']);
                $table->dropColumn('hsn_code');
            });
        }
    }
};
