<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the orphaned "Vendor" enterprise CRM tables (dead code superseded by the Supplier module).
     * Order matters: child/pivot tables referencing `vendors` are dropped before `vendors` and `vendor_categories`.
     */
    public function up(): void
    {
        foreach ([
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
            'vendor_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Intentionally not reversible: this drops dead code's tables. Restore from
        // 2026_08_11_000001_create_vendor_management_tables.php if ever needed.
    }
};
