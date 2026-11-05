<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $user_id
 * @property string $role
 * @property Carbon $joined_at
 * @property bool $notify
 * @property bool $notify_amounts
 */
class Membership extends Pivot
{
    protected $table = 'household_user';

    protected $casts = [
        'joined_at' => 'datetime',
        'notify' => 'boolean',
        'notify_amounts' => 'boolean',
    ];
}
