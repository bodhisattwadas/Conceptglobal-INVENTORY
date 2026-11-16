<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_number',
        'sale_id',
        'sale_item_id',
        'matched_product_id',
        'item_name',
        'barcode',
        'quantity',
        'unit_price',
        'cost_price',
        'discount',
        'subtotal',
        'status',
        'reconciled_product_id',
        'reconciled_purchase_item_id',
        'target_purchase_id',
        'reconciled_purchase_id',
        'reconciled_by',
        'reconciled_at',
        'action_taken',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function reconciledProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reconciled_product_id');
    }

    public function reconciledPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'reconciled_purchase_item_id');
    }

    public function targetPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'target_purchase_id');
    }

    public function reconciledPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'reconciled_purchase_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryReconciliation $model) {
            if (empty($model->reconciliation_number)) {
                $date = $model->created_at instanceof Carbon
                    ? $model->created_at
                    : ($model->created_at ? Carbon::parse($model->created_at) : null);
                $calcDate = $date ?? now();
                $prefix = 'REC.'.$calcDate->format('ymd').'.';
                $model->reconciliation_number = static::withSequenceLock($prefix, function () use ($date) {
                    return static::generateReconciliationNumber($date, lock: true);
                });
            }
        });
    }

    /**
     * Acquire an advisory sequence lock on MySQL/MariaDB for the given prefix to prevent gap-lock deadlocks and collisions.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withSequenceLock(string $prefix, callable $callback)
    {
        $driver = DB::connection()->getDriverName();
        $isMySql = in_array($driver, ['mysql', 'mariadb'], true);

        if (! $isMySql) {
            return $callback();
        }

        $lockName = 'recon_seq_lock_'.md5($prefix);
        $lockAcquired = false;

        try {
            $lockResult = DB::select('SELECT GET_LOCK(?, 15) as acquired', [$lockName]);
            $lockAcquired = ! empty($lockResult[0]->acquired);

            return $callback();
        } finally {
            if ($lockAcquired) {
                try {
                    DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
                } catch (Throwable) {
                    // Ignore release errors on connection disconnect
                }
            }
        }
    }

    /**
     * Create an InventoryReconciliation record with guaranteed unique sequence numbering and retry logic.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithUniqueNumber(array $attributes, int $maxAttempts = 5): static
    {
        $date = null;
        if (! empty($attributes['created_at'])) {
            $date = $attributes['created_at'] instanceof Carbon
                ? $attributes['created_at']
                : Carbon::parse($attributes['created_at']);
        }

        $calcDate = $date ?? now();
        $prefix = 'REC.'.$calcDate->format('ymd').'.';

        return static::withSequenceLock($prefix, function () use ($attributes, $date, $maxAttempts) {
            $userSuppliedNumber = ! empty($attributes['reconciliation_number']);
            $attempt = 0;

            while (true) {
                $attempt++;
                try {
                    if (! $userSuppliedNumber || $attempt > 1) {
                        $attributes['reconciliation_number'] = static::generateReconciliationNumber($date, lock: true);
                    }

                    return static::create($attributes);
                } catch (Throwable $e) {
                    if ($attempt < $maxAttempts && static::isDuplicateReconciliationNumberException($e)) {
                        usleep(random_int(1000, 10000));

                        continue;
                    }

                    throw $e;
                }
            }
        });
    }

    /**
     * Determine if an exception represents a unique constraint violation on reconciliation_number.
     */
    public static function isDuplicateReconciliationNumberException(Throwable $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'inventory_reconciliations') || str_contains($msg, 'reconciliation_number')) {
                return true;
            }
        }

        if ($e instanceof QueryException) {
            $errorCode = $e->errorInfo[1] ?? null;
            $errorMsg = $e->errorInfo[2] ?? $e->getMessage();

            // MySQL error 1062: ER_DUP_ENTRY
            if ($errorCode === 1062 && (str_contains($errorMsg, 'reconciliation_number') || str_contains($errorMsg, 'inventory_reconciliations'))) {
                return true;
            }

            // SQLite error 19 (SQLITE_CONSTRAINT) or 2067 (SQLITE_CONSTRAINT_UNIQUE)
            if (in_array($errorCode, [19, 2067], true) && str_contains($errorMsg, 'reconciliation_number')) {
                return true;
            }

            if (str_contains($errorMsg, 'inventory_reconciliations_reconciliation_number_unique') ||
                str_contains($errorMsg, 'inventory_reconciliations.reconciliation_number')) {
                return true;
            }
        }

        if ($e->getPrevious() && static::isDuplicateReconciliationNumberException($e->getPrevious())) {
            return true;
        }

        return false;
    }

    /**
     * Generate the next unique reconciliation sequence number for the given date.
     * Format: REC.YYMMDD.XXXX
     */
    public static function generateReconciliationNumber(?Carbon $date = null, bool $lock = false): string
    {
        $date = $date ?? now();
        $prefix = 'REC.'.$date->format('ymd').'.';

        $query = static::query()
            ->where('reconciliation_number', 'like', $prefix.'%');

        if ($lock && DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $existing = (clone $query)->pluck('reconciliation_number');

        $maxSequence = 0;
        foreach ($existing as $reconNum) {
            if (preg_match('/\.(\d+)$/', $reconNum, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSequence) {
                    $maxSequence = $seq;
                }
            }
        }

        $sequence = $maxSequence + 1;

        do {
            $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::query()->where('reconciliation_number', $candidate)->exists());

        return $candidate;
    }
}
