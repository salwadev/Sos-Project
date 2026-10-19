<?php

namespace App\Sms;

/** Environnement de test : garde les SMS en mémoire pour que les tests lisent le code envoyé. */
final class InMemorySmsSender implements SmsSender
{
    /** @var list<array{phone: string, message: string}> */
    private array $messages = [];

    public function send(string $phoneE164, string $message): void
    {
        $this->messages[] = ['phone' => $phoneE164, 'message' => $message];
    }

    /** @return list<array{phone: string, message: string}> */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function lastCodeFor(string $phoneE164): ?string
    {
        foreach (array_reverse($this->messages) as $sms) {
            if ($sms['phone'] === $phoneE164 && preg_match('/\b(\d{6})\b/', $sms['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
