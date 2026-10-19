<?php

namespace App\Sms;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Environnement dev uniquement : écrit les SMS dans var/sms_outbox.log au lieu de les envoyer,
 * pour tester la connexion OTP sans fournisseur. Jamais utilisé en production.
 */
final class DevOutboxSmsSender implements SmsSender
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/sms_outbox.log')]
        private readonly string $outboxPath,
    ) {
    }

    public function send(string $phoneE164, string $message): void
    {
        $line = \sprintf("[%s] %s : %s\n", gmdate('Y-m-d H:i:s'), $phoneE164, $message);
        file_put_contents($this->outboxPath, $line, \FILE_APPEND | \LOCK_EX);
    }
}
