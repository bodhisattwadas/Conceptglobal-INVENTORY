<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'received_quantity',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'unit_price',
        'subtotal',
        'selling_price',
    ];

    protected $casts = [
        'purchase_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
