<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_return_id',
        'purchase_item_id',
        'product_id',
        'batch_number',
        'quantity',
        'unit_price',
        'subtotal',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vendor_return_id' => 'integer',
        'purchase_item_id' => 'integer',
        'product_id' => 'integer',
    ];

    public function vendorReturn(): BelongsTo
    {
        return $this->belongsTo(VendorReturn::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
