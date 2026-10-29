<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'name', 'default_amount', 'repeat_monthly', 'sort', 'archived_at'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected $casts = [
        'name' => 'encrypted',
        'default_amount' => EncryptedInteger::class,
        'repeat_monthly' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(BudgetMonth::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }
}
