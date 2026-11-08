<?php

namespace App\Models;

use App\Support\Period;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property Carbon|null $period_start
 */
#[Fillable(['name', 'currency', 'month_start_day', 'owner_id', 'period_start'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $casts = ['name' => 'encrypted', 'period_start' => 'date'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this, Membership, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role', 'joined_at', 'notify', 'notify_amounts');
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Goal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /** @return HasMany<GoalMovement, $this> */
    public function goalMovements(): HasMany
    {
        return $this->hasMany(GoalMovement::class);
    }

    /** True once any Transaction or Goal movement exists, so the currency is fixed. */
    public function hasRecordedMoney(): bool
    {
        return $this->transactions()->withTrashed()->exists()
            || $this->goalMovements()->exists();
    }

    /** @return HasMany<HouseholdInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    /**
     * Active budgets with what is left in the given period, for pickers.
     *
     * @return Collection<int, array{id: int, name: string, left: int}>
     */
    public function budgetBalances(Period $period): Collection
    {
        return $this->budgets()->active()->orderBy('sort')
            ->with([
                'months' => fn ($q) => $q->whereDate('period_start', $period->key())
                    ->with('transactions'),
            ])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance()
                    ?? ($b->repeat_monthly ? ($b->default_amount ?? 0) : 0),
            ])->values();
    }
}
