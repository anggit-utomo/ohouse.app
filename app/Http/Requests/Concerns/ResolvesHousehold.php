<?php

namespace App\Http\Requests\Concerns;

use App\Models\Household;

trait ResolvesHousehold
{
    protected function household(): Household
    {
        /** @var Household $household */
        $household = $this->route('household');

        return $household;
    }
}
