<?php

namespace App\Domain\Professional;

use App\Enum\VerificationStatus;

/** Décisions de l'administration sur un profil professionnel (§7.2) et les statuts qu'elles relient. */
enum VerificationAction: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Suspend = 'suspend';
    case Reactivate = 'reactivate';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<VerificationStatus> */
    public function allowedFrom(): array
    {
        return match ($this) {
            self::Approve => [VerificationStatus::Pending, VerificationStatus::Rejected],
            self::Reject => [VerificationStatus::Pending],
            self::Suspend => [VerificationStatus::Verified],
            self::Reactivate => [VerificationStatus::Suspended],
        };
    }

    public function target(): VerificationStatus
    {
        return match ($this) {
            self::Approve, self::Reactivate => VerificationStatus::Verified,
            self::Reject => VerificationStatus::Rejected,
            self::Suspend => VerificationStatus::Suspended,
        };
    }
}
