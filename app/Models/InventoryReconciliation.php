<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_number',
        'sale_id',
        'sale_item_id',
        'matched_product_id',
        'item_name',
        'barcode',
        'quantity',
        'unit_price',
        'cost_price',
        'discount',
        'subtotal',
        'status',
        'reconciled_product_id',
        'reconciled_purchase_item_id',
        'reconciled_by',
        'reconciled_at',
        'action_taken',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function reconciledProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reconciled_product_id');
    }

    public function reconciledPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'reconciled_purchase_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public static function generateReconciliationNumber(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $prefix = 'REC.'.$date->format('ymd').'.';

        $lastRecord = static::query()
            ->where('reconciliation_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastRecord && preg_match('/\.(\d+)$/', $lastRecord->reconciliation_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
