<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['household_id', 'code', 'created_by', 'used_by', 'expires_at', 'used_at', 'revoked_at'])]
class HouseholdInvite extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return ! $this->used_at && ! $this->revoked_at && $this->expires_at->isFuture();
    }

    /** Revokes older active codes and creates a new one, e.g. KTR-4829. */
    public static function generate(Household $household, User $by, int $minutes = 10): self
    {
        $household->invites()->active()->update(['revoked_at' => now()]);

        do {
            $code = Str::upper(Str::random(3)).'-'.random_int(1000, 9999);
        } while (static::where('code', $code)->exists());

        return static::create([
            'household_id' => $household->id,
            'code' => $code,
            'created_by' => $by->id,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }
}
