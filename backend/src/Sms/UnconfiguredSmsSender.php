<?php

namespace App\Sms;

/** Implémentation par défaut hors dev/test : refuse d'envoyer tant qu'aucun fournisseur n'est branché. */
final class UnconfiguredSmsSender implements SmsSender
{
    public function send(string $phoneE164, string $message): void
    {
        throw new \LogicException('Aucun fournisseur SMS configuré : brancher une implémentation de App\Sms\SmsSender.');
    }
}
