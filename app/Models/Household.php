<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

#[Fillable(['name', 'currency', 'month_start_day', 'owner_id'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $casts = ['name' => 'encrypted'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this, Pivot, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'joined_at');
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

    /** @return HasMany<HouseholdInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    /**
     * Active budgets with what is left this month, for pickers.
     *
     * @return Collection<int, array{id: int, name: string, left: int}>
     */
    public function budgetBalances(): Collection
    {
        $period = now()->startOfMonth()->toDateString();

        return $this->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $period)->with('transactions')])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance() ?? ($b->repeat_monthly ? $b->default_amount : 0),
            ])->values();
    }
}
