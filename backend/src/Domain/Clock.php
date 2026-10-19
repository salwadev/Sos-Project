<?php

namespace App\Domain;

/** Toutes les dates métier sont produites en UTC et affichées en heure de Tunis côté client (§8.6). */
final class Clock
{
    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
