<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'adjustment_number',
        'type',
        'source_product_id',
        'source_purchase_item_id',
        'target_product_id',
        'target_purchase_item_id',
        'quantity',
        'unit_cost',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'source_product_id' => 'integer',
        'source_purchase_item_id' => 'integer',
        'target_product_id' => 'integer',
        'target_purchase_item_id' => 'integer',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'created_by' => 'integer',
    ];

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function sourcePurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'source_purchase_item_id');
    }

    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function targetPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'target_purchase_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateAdjustmentNumber(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $prefix = 'ADJ.'.$date->format('ymd').'.';

        $lastRecord = static::query()
            ->where('adjustment_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastRecord && preg_match('/\.(\d+)$/', $lastRecord->adjustment_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
