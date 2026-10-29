<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use Database\Factories\TransactionFactory;
use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[Fillable([
    'household_id',
    'budget_month_id',
    'user_id',
    'type',
    'amount',
    'note',
    'occurred_at',
    'transfer_id',
    'corrects_transaction_id',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use SoftDeletes, HasFactory;

    protected $casts = [
        'type' => TransactionTypeEnum::class,
        'amount' => EncryptedInteger::class,
        'note' => 'encrypted',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Closed months are locked: add a correction in the current month instead.
        $guard = function (Transaction $t) {
            if ($t->budgetMonth?->isClosed()) {
                throw new LogicException('This month is closed. Add a correction entry instead.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function budgetMonth(): BelongsTo
    {
        return $this->belongsTo(BudgetMonth::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_transaction_id');
    }

    public function signedAmount(): int
    {
        return $this->type->sign() * $this->amount;
    }

    public function scopeBetween(Builder $q, mixed $from, mixed $to): Builder
    {
        return $q->whereBetween('occurred_at', [$from, $to]);
    }
}
