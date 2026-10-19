<?php

namespace App\Enum;

/** Rôles applicatifs (§4) ; un utilisateur peut en cumuler plusieurs. */
enum UserRole: string
{
    case Client = 'ROLE_CLIENT';
    case Professional = 'ROLE_PROFESSIONAL';
    case Support = 'ROLE_SUPPORT';
    case Admin = 'ROLE_ADMIN';
}
