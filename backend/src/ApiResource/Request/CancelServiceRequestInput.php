<?php

namespace App\ApiResource\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/requests/{id}/cancel : le motif est enregistré (§8.5). */
final class CancelServiceRequestInput
{
    #[Assert\Length(max: 255)]
    public ?string $reason = null;
}
