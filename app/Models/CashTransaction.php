<?php

namespace App\Models;

use App\Enums\CashTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashTransaction extends Model
{
    use HasFactory;

    protected $table = 'cash_transactions';

    protected $fillable = [
        'transaction_date',
        'transaction_type',
        'reference_number',
        'name',
        'description',
        'account_name',
        'split_account',
        'finance_category_id',
        'amount',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'transaction_type' => CashTransactionType::class,
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Signed amount accessor: positive for inflows, negative for outflows.
     */
    protected function signedAmount(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $rawAmount = (float) ($attributes['amount'] ?? 0);
                $type = $this->transaction_type;

                if ($type instanceof CashTransactionType && $type->isOutflow()) {
                    return -$rawAmount;
                }

                if (is_string($type) && in_array($type, [CashTransactionType::Expense->value, CashTransactionType::Drawing->value], true)) {
                    return -$rawAmount;
                }

                return $rawAmount;
            }
        );
    }

    /**
     * Scope for chronological ordering.
     */
    public function scopeChronological(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('transaction_date', $direction)->orderBy('id', $direction);
    }

    /**
     * Scope for cash inflows.
     */
    public function scopeInflows(Builder $query): Builder
    {
        return $query->whereIn('transaction_type', [
            CashTransactionType::CashSale->value,
            CashTransactionType::CashInvestment->value,
        ]);
    }

    /**
     * Scope for cash outflows.
     */
    public function scopeOutflows(Builder $query): Builder
    {
        return $query->whereIn('transaction_type', [
            CashTransactionType::Expense->value,
            CashTransactionType::Drawing->value,
        ]);
    }
}
