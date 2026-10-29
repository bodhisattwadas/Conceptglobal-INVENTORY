<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'cost',
        'description',
        'is_active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Alias for cost as price for consistency.
     */
    public function getPriceAttribute(): float
    {
        return (float) $this->cost;
    }
}
