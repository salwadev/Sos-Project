<?php

namespace App\Auth;

/** Normalisation des numéros tunisiens au format E.164 (+216 suivi de 8 chiffres, §15.4). */
final class PhoneNumber
{
    public const COUNTRY_PREFIX = '+216';

    /** « 22 123 456 », « +216 22-123-456 », « 0021622123456 » → « +21622123456 » ; null si invalide. */
    public static function normalizeTunisian(string $raw): ?string
    {
        $compact = preg_replace('/[\s.\-()]/', '', $raw) ?? '';

        $national = match (true) {
            str_starts_with($compact, '+216') => substr($compact, 4),
            str_starts_with($compact, '00216') => substr($compact, 5),
            11 === \strlen($compact) && str_starts_with($compact, '216') => substr($compact, 3),
            default => $compact,
        };

        return 1 === preg_match('/^[2-9]\d{7}$/', $national) ? self::COUNTRY_PREFIX.$national : null;
    }
}
