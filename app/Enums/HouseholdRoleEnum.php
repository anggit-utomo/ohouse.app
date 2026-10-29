<?php

namespace App\Enums;

enum HouseholdRoleEnum: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function canEdit(): bool
    {
        return $this !== self::VIEWER;
    }
}
