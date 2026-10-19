<?php

namespace App\Enum;

/** Version de devis : estimation proposée puis acceptée ou remplacée (§9.2). */
enum QuoteStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
