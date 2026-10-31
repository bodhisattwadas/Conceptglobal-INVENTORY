<?php

namespace App\Models;

use App\Enums\PurchaseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'unit_id',
        'company_id',
        'sku',
        'product_code',
        'name',
        'mrp',
        'purchase_price',
        'selling_price',
        'quantity',
        'min_stock',
        'location',
        'is_active',
        'description',
        'notes',
        'image_path',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'quantity' => 'integer',
        'min_stock' => 'integer',
        'location' => PurchaseLocation::class,
        'is_active' => 'boolean',
    ];

    public function getLocationAttribute(?string $value): PurchaseLocation
    {
        if ($value) {
            return PurchaseLocation::tryFrom($value) ?? PurchaseLocation::SHOP;
        }

        return PurchaseLocation::SHOP;
    }

    public function getStockByLocation(): array
    {
        $activeBatches = $this->purchaseItems
            ?->filter(fn ($item) => (int) ($item->received_quantity ?? 0) > 0) ?? collect();

        $shop = 0;
        $parlour = 0;

        foreach ($activeBatches as $item) {
            if ($item->location === PurchaseLocation::PARLOUR) {
                $parlour += (int) $item->received_quantity;
            } else {
                $shop += (int) $item->received_quantity;
            }
        }

        return [
            'shop' => $shop,
            'parlour' => $parlour,
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $path = ltrim($this->image_path, '/');
        $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return public_storage_url($path);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function inventoryStock()
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
