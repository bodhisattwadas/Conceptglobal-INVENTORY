<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'legal_name',
        'trade_name',
        'supplier_type',
        'registration_number',
        'tax_id',
        'website',
        'industry',
        'contact_person',
        'email',
        'accounts_email',
        'purchase_email',
        'phone',
        'alternate_phone',
        'address',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'bank_name',
        'bank_branch',
        'account_name',
        'account_number_encrypted',
        'account_number_last4',
        'account_type',
        'ifsc_code',
        'swift_bic',
        'beneficiary_name',
        'bank_country',
        'blank_cheque_path',
        'gst_document_path',
        'status',
        'notes',
    ];

    protected $casts = [
        'email' => 'string',
        'name' => 'string',
        'contact_person' => 'string',
        'phone' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'supplier_companies')->withTimestamps();
    }

    public function setAccountNumberAttribute(?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $digits = preg_replace('/\D+/', '', $value);
        $this->attributes['account_number_encrypted'] = Crypt::encryptString($value);
        $this->attributes['account_number_last4'] = substr($digits, -4);
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        return $this->account_number_last4 ? 'XXXXXXXX'.$this->account_number_last4 : null;
    }

    public function getFullAccountNumberAttribute(): ?string
    {
        if (blank($this->account_number_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->account_number_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
