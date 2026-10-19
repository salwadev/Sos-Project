<?php

namespace App\Enum;

/** Niveau d'urgence choisi par le client (§5.3). */
enum Urgency: string
{
    case Asap = 'asap';
    case Today = 'today';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
