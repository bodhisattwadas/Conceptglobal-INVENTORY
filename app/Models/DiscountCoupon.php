<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_purchase_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'description',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'integer',
        'min_purchase_amount' => 'integer',
        'max_discount_amount' => 'integer',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'formatted_label',
    ];

    /**
     * Get human-friendly label for display in dropdowns.
     */
    public function getFormattedLabelAttribute(): string
    {
        $parts = [];
        if ($this->type === CouponType::Percentage) {
            $parts[] = "{$this->code} — {$this->value}% OFF";
            if ($this->max_discount_amount) {
                $parts[] = '(Max '.format_money($this->max_discount_amount).')';
            }
        } else {
            $parts[] = "{$this->code} — Flat ".format_money($this->value).' OFF';
        }

        if ($this->min_purchase_amount) {
            $parts[] = '[Min '.format_money($this->min_purchase_amount).']';
        }

        return implode(' ', $parts);
    }
}
