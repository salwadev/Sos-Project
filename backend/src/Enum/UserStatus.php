<?php

namespace App\Enum;

/** État du compte. */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
