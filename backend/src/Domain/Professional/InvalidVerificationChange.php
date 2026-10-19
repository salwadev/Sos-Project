<?php

namespace App\Domain\Professional;

/** Décision impossible dans l'état actuel du profil ou de ses justificatifs (réponse 409). */
final class InvalidVerificationChange extends \DomainException
{
}
