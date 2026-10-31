<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use Database\Factories\BudgetMonthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'budget_id',
    'period_start',
    'period_end',
    'allocated',
    'carried_in',
    'carry_decision',
    'closed_at',
])]
class BudgetMonth extends Model
{
    /** @use HasFactory<BudgetMonthFactory> */
    use HasFactory;

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'allocated' => EncryptedInteger::class,
        'carried_in' => EncryptedInteger::class,
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Spent so far (expenses and money moved out). Amounts are encrypted, so this sums in PHP. */
    public function spent(): int
    {
        return $this->transactions
            ->filter(fn (Transaction $t) => $t->type->sign() < 0)
            ->sum('amount');
    }

    /** What is left: allocated + carried in + income/transfers in - spent. */
    public function balance(): int
    {
        $net = $this->transactions->sum(fn (Transaction $t) => $t->signedAmount());

        return $this->allocated + $this->carried_in + $net;
    }

    /**
     * Numbers for one budget this month. Amounts are encrypted, so this runs in PHP.
     *
     * @return array{spent: int, left: int, available: int, pct: int, over: bool}
     */
    public function summary(): array
    {
        $spent = $this->spent();
        $left = $this->balance();
        $available = $left + $spent;

        return [
            'spent' => $spent,
            'left' => $left,
            'available' => $available,
            'pct' => $available > 0 ? min(100, (int) round($spent / $available * 100)) : ($spent > 0 ? 100 : 0),
            'over' => $left < 0,
        ];
    }

    /** @return Builder<BudgetMonth> for the given household, via the budget relation. */
    public function scopeForHousehold(Builder $q, Household $household): Builder
    {
        return $q->whereHas('budget', fn ($b) => $b->where('household_id', $household->id));
    }
}
