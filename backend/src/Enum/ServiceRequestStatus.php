<?php

namespace App\Enum;

/** États d'une demande (§8.4) — transitions définies dans config/packages/workflow.yaml. */
enum ServiceRequestStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Searching = 'SEARCHING';
    case Offered = 'OFFERED';
    case Accepted = 'ACCEPTED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
    case Disputed = 'DISPUTED';
}
