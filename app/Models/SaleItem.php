<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'service_id',
        'custom_name',
        'barcode',
        'is_manual',
        'quantity',
        'cost_price',
        'unit_price',
        'discount',
        'final_price',
        'subtotal',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'cost_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isService(): bool
    {
        return ! empty($this->service_id);
    }

    public function isProduct(): bool
    {
        return ! empty($this->product_id);
    }

    public function isManual(): bool
    {
        return (bool) $this->is_manual;
    }

    public function getItemNameAttribute(): string
    {
        return $this->custom_name ?? $this->service?->name ?? $this->product?->name ?? 'Unknown Item';
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(InventoryReconciliation::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
