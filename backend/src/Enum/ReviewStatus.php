<?php

namespace App\Enum;

/** Modération d'un avis (§7, avis signalés). */
enum ReviewStatus: string
{
    case Published = 'published';
    case Flagged = 'flagged';
    case Hidden = 'hidden';
}
