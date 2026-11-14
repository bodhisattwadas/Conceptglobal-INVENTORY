<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Services\SaleService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'created_by',
        'sale_date',
        'status',
        'subtotal',
        'global_discount',
        'coupon_discount',
        'round_off',
        'return_discount',
        'total_discount',
        'total',
        'cash_received',
        'change',
        'payment_method',
        'notes',
        'discount_coupon_id',
        'coupon_code',
        'cash_amount',
        'bank_amount',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'status' => SaleStatus::class,
        'payment_method' => PaymentMethod::class,
        'subtotal' => 'decimal:2',
        'global_discount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'return_discount' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'bank_amount' => 'decimal:2',
    ];

    public function isSplit(): bool
    {
        $method = $this->payment_method instanceof PaymentMethod
            ? $this->payment_method
            : PaymentMethod::tryFrom((string) $this->payment_method);

        return $method === PaymentMethod::SPLIT;
    }

    public function getCashAmount(): float
    {
        if ($this->isSplit()) {
            return (float) ($this->cash_amount ?? 0);
        }

        $isCash = $this->payment_method === PaymentMethod::CASH || $this->payment_method === 'cash';

        return $isCash ? (float) ($this->total ?? 0) : 0.0;
    }

    public function getBankAmount(): float
    {
        if ($this->isSplit()) {
            return (float) ($this->bank_amount ?? 0);
        }

        $isCash = $this->payment_method === PaymentMethod::CASH || $this->payment_method === 'cash';

        return ! $isCash ? (float) ($this->total ?? 0) : 0.0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(DiscountCoupon::class, 'discount_coupon_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Sale $sale) {
            app(SaleService::class)->revertInventoryAndCleanupSale($sale);
        });
    }
}
