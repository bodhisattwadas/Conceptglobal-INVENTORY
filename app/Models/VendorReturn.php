<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'purchase_id',
        'supplier_id',
        'company_id',
        'location',
        'return_date',
        'total_amount',
        'status',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:2',
        'purchase_id' => 'integer',
        'supplier_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorReturnItem::class);
    }

    public function getFormattedTotalAttribute(): string
    {
        return format_money($this->total_amount);
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'damaged' => 'Damaged / Broken',
            'expired' => 'Expired / Near Expiry',
            'defective' => 'Defective / Quality Issue',
            'wrong_item' => 'Wrong Item Delivered',
            'unsold_return' => 'Unsold / Return Agreement',
            default => 'Other',
        };
    }
}
