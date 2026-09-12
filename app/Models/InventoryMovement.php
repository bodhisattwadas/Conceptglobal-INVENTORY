<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'purchase_id',
        'purchase_item_id',
        'sale_id',
        'sale_item_id',
        'type',
        'quantity',
        'balance_after',
        'reference',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'purchase_id' => 'integer',
        'purchase_item_id' => 'integer',
        'sale_id' => 'integer',
        'sale_item_id' => 'integer',
        'quantity' => 'integer',
        'balance_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
