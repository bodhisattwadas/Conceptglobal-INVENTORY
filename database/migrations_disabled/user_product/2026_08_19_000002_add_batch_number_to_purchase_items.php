<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->unique()->after('received_quantity');
        });

        DB::table('purchase_items')
            ->where('received_quantity', '>', 0)
            ->orderBy('id')
            ->get(['id', 'updated_at'])
            ->each(function (object $item): void {
                $date = $item->updated_at
                    ? \Illuminate\Support\Carbon::parse($item->updated_at)
                    : now();
                $prefix = 'BN-'.$date->format('Ymd').'-';
                $lastBatchNumber = DB::table('purchase_items')
                    ->where('batch_number', 'like', $prefix.'%')
                    ->orderByDesc('batch_number')
                    ->value('batch_number');
                $nextNumber = $lastBatchNumber ? ((int) substr($lastBatchNumber, -4)) + 1 : 1;

                do {
                    $batchNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
                    $nextNumber++;
                } while (DB::table('purchase_items')->where('batch_number', $batchNumber)->exists());

                DB::table('purchase_items')
                    ->where('id', $item->id)
                    ->update(['batch_number' => $batchNumber]);
            });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropUnique(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
