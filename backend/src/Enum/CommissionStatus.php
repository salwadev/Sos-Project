<?php

namespace App\Enum;

/** Commission due par le professionnel (§9.4). */
enum CommissionStatus: string
{
    case Due = 'due';
    case Paid = 'paid';
    case Adjusted = 'adjusted';
    case Cancelled = 'cancelled';
}
