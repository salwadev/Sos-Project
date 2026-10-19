<?php

namespace App\Enum;

/** Statuts d'exécution mis à jour par le professionnel (§6.6). */
enum InterventionStatus: string
{
    case Confirmed = 'confirmed';
    case OnTheWay = 'on_the_way';
    case OnSite = 'on_site';
    case Working = 'working';
    case CompletionDeclared = 'completion_declared';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    /** Étapes que le professionnel peut déclarer via POST /api/interventions/{id}/status. */
    public const PROFESSIONAL_STEPS = ['on_the_way', 'on_site', 'working', 'completion_declared'];

    /** Rang dans le déroulé d'exécution (on n'avance que vers un rang supérieur) ; null hors de ce déroulé. */
    public function executionRank(): ?int
    {
        return match ($this) {
            self::Confirmed => 0,
            self::OnTheWay => 1,
            self::OnSite => 2,
            self::Working => 3,
            self::CompletionDeclared => 4,
            default => null,
        };
    }
}
