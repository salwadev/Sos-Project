<?php

namespace App\Enum;

/** Justificatifs déposés par un professionnel (§6.2). */
enum DocumentType: string
{
    case Identity = 'identity';
    case Qualification = 'qualification';
    case CompanyRegistration = 'company_registration';
    case Insurance = 'insurance';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
