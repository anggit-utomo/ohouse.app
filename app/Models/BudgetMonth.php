<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use App\Enums\GoalMovementTypeEnum;
use App\Support\Currencies;
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

    /** @return HasMany<GoalMovement, $this> */
    public function goalMovements(): HasMany
    {
        return $this->hasMany(GoalMovement::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Spent so far: expenses and money moved to another budget, not money saved to a goal. */
    public function spent(): int
    {
        return $this->transactions
            ->filter(fn (Transaction $t) => $t->type->sign() < 0)
            ->sum('amount');
    }

    /** What is left: allocated + carried in + income/transfers in - spent - saved + returned. */
    public function balance(): int
    {
        $net = $this->transactions->sum(fn (Transaction $t) => $t->signedAmount());

        return $this->allocated + $this->carried_in + $net + $this->goalNet();
    }

    /**
     * Numbers for one budget this month. Amounts are encrypted, so this runs in PHP.
     *
     * @return array{spent: int, left: int, available: int, moved: int, returned: int, pct: int, over: bool}
     */
    public function summary(): array
    {
        $spent = $this->spent();
        $left = $this->balance();
        $moved = $this->movedToGoals();
        $returned = $this->returnedFromGoals();
        $available = $this->allocated + $this->carried_in + $this->incoming() + $returned;

        return [
            'spent' => $spent,
            'left' => $left,
            'available' => $available,
            'moved' => $moved,
            'returned' => $returned,
            'pct' => $available > 0 ? min(100, (int) round($spent / $available * 100)) : ($spent > 0 ? 100 : 0),
            'over' => $left < 0,
        ];
    }

    /** Everything that came into the budget: income and incoming moves. */
    private function incoming(): int
    {
        return $this->transactions
            ->filter(fn (Transaction $t) => $t->type->sign() > 0)
            ->sum('amount');
    }

    /** Money that left this budget for a goal. */
    public function movedToGoals(): int
    {
        return $this->goalMovementsOfType(GoalMovementTypeEnum::CONTRIBUTION);
    }

    /** Money returned to this budget from a goal. */
    public function returnedFromGoals(): int
    {
        return $this->goalMovementsOfType(GoalMovementTypeEnum::WITHDRAWAL);
    }

    /** The net effect of goals on this budget: money returned minus money saved. */
    public function goalNet(): int
    {
        return $this->returnedFromGoals() - $this->movedToGoals();
    }

    private function goalMovementsOfType(GoalMovementTypeEnum $type): int
    {
        return $this->goalMovements
            ->filter(fn (GoalMovement $m) => $m->type === $type)
            ->sum('amount');
    }

    /** Null when the amount fits, otherwise the message to show. */
    public function shortfallMessage(int $amount, string $currency): ?string
    {
        $left = $this->balance();

        if ($amount <= $left) {
            return null;
        }

        return sprintf(
            '%s only has %s left.',
            $this->budget->name,
            Currencies::format(max($left, 0), $currency)
        );
    }

    /**
     * @param  Builder<BudgetMonth>  $q
     * @return Builder<BudgetMonth>
     */
    public function scopeWithMoney(Builder $q): Builder
    {
        return $q->with(['transactions', 'goalMovements']);
    }

    /**
     * @param  Builder<BudgetMonth>  $q
     * @return Builder<BudgetMonth> for the given household, via the budget relation.
     */
    public function scopeForHousehold(Builder $q, Household $household): Builder
    {
        return $q->whereHas('budget', fn ($b) => $b->where('household_id', $household->id));
    }
}
