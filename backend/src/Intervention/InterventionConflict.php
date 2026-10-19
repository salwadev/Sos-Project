<?php

namespace App\Intervention;

/** Action sur une intervention ou un devis impossible dans l'état actuel : 409 Conflict avec un message explicite. */
final class InterventionConflict extends \DomainException
{
}
