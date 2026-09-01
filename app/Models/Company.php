<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_code',
        'company_name',
        'legal_name',
        'short_name',
        'company_type',
        'parent_company_id',
        'registration_number',
        'gstin',
        'pan',
        'cin',
        'tax_registration_number',
        'incorporation_date',
        'primary_email',
        'phone',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'district',
        'state',
        'postal_code',
        'country',
        'base_currency_id',
        'financial_year_start',
        'default_payment_terms_id',
        'default_purchase_tax_id',
        'default_payable_account',
        'status',
    ];

    protected $casts = [
        'incorporation_date' => 'date',
        'financial_year_start' => 'date',
    ];

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_company_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_companies')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
