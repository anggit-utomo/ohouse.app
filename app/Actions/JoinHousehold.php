<?php

namespace App\Actions;

use App\Enums\HouseholdRoleEnum;
use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinHousehold
{
    /** A code works once. Someone already in the household is sent there without using it up. */
    public function handle(User $user, string $code): Household
    {
        return DB::transaction(function () use ($user, $code) {
            $invite = HouseholdInvite::active()
                ->where('code', HouseholdInvite::normalize($code))
                ->lockForUpdate()->with('household')->first();

            if (! $invite) {
                throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
            }

            $household = $invite->household;

            if ($household->members()->whereKey($user->id)->exists()) {
                return $household;
            }

            $household->members()->attach($user->id, ['role' => HouseholdRoleEnum::EDITOR->value]);
            $invite->update(['used_at' => now(), 'used_by' => $user->id]);
            $user->forceFill(['current_household_id' => $household->id])->save();

            return $household;
        });
    }
}
