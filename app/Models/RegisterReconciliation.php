<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use App\Enums\RegisterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterReconciliation extends Model
{
    use HasFactory;

    protected $table = 'register_reconciliations';

    protected $fillable = [
        'register_type',
        'year',
        'month',
        'reconciliation_date',
        'beginning_balance',
        'total_inflows',
        'total_outflows',
        'recorded_balance',
        'actual_balance',
        'difference',
        'status',
        'notes',
        'denominations',
        'reconciled_by',
        'reconciled_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'register_type' => RegisterType::class,
        'status' => ReconciliationStatus::class,
        'year' => 'integer',
        'month' => 'integer',
        'reconciliation_date' => 'date',
        'beginning_balance' => 'decimal:2',
        'total_inflows' => 'decimal:2',
        'total_outflows' => 'decimal:2',
        'recorded_balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'denominations' => 'array',
        'reconciled_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function isReconciled(): bool
    {
        return $this->status === ReconciliationStatus::Reconciled;
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getPeriodYearAttribute(): ?int
    {
        return $this->year !== null ? (int) $this->year : null;
    }

    public function getPeriodMonthAttribute(): ?int
    {
        return $this->month !== null ? (int) $this->month : null;
    }

    public function scopeForRegister(Builder $query, RegisterType|string $type): Builder
    {
        $val = $type instanceof RegisterType ? $type->value : $type;

        return $query->where('register_type', $val);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
