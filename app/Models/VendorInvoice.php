<?php

namespace App\Models;

use App\Enums\VendorInvoiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInvoice extends Model
{
    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'company_id',
        'po_reference',
        'invoice_number',
        'invoice_date',
        'order_received_date',
        'amount',
        'vendor_additional_discount',
        'paid_amount',
        'document_path',
        'status',
        'payment_method',
        'payment_reference',
        'payment_notes',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'purchase_id' => 'integer',
        'supplier_id' => 'integer',
        'company_id' => 'integer',
        'invoice_date' => 'date',
        'order_received_date' => 'date',
        'amount' => 'decimal:2',
        'vendor_additional_discount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => VendorInvoiceStatus::class,
        'paid_at' => 'datetime',
        'paid_by' => 'integer',
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

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Alias relation to supplier (representing vendor).
     */
    public function vendor(): BelongsTo
    {
        return $this->supplier();
    }

    /**
     * Calculate net due balance (amount minus paid_amount).
     */
    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    /**
     * Get effective date following priority hierarchy: invoice_date -> order_received_date -> created_at.
     */
    public function getEffectiveDateAttribute(): Carbon
    {
        $date = $this->invoice_date ?? $this->order_received_date ?? $this->created_at;

        return Carbon::parse($date);
    }

    /**
     * Calculate aging days from effective date to today.
     */
    public function getAgeInDaysAttribute(): int
    {
        return max(0, (int) $this->effective_date->startOfDay()->diffInDays(now()->startOfDay(), false));
    }

    /**
     * Determine aging bucket code (0-30, 31-60, 61-90, 90+).
     */
    public function getAgingBucketAttribute(): string
    {
        $days = $this->age_in_days;

        return match (true) {
            $days <= 30 => '0-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }

    /**
     * Determine human-readable aging bucket label.
     */
    public function getAgingBucketLabelAttribute(): string
    {
        return match ($this->aging_bucket) {
            '0-30' => '0–30 Days (Current)',
            '31-60' => '31–60 Days',
            '61-90' => '61–90 Days',
            default => '90+ Days',
        };
    }
}
