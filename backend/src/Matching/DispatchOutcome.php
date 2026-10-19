<?php

namespace App\Matching;

/** Résultat d'un passage du moteur de diffusion sur une demande. */
enum DispatchOutcome: string
{
    /** Une nouvelle vague de professionnels a été sollicitée. */
    case WaveSent = 'wave_sent';
    /** Des sollicitations sont encore dans leur délai de réponse. */
    case Waiting = 'waiting';
    /** Un professionnel a réservé la demande ; le client doit confirmer. */
    case Reserved = 'reserved';
    /** Plus aucun professionnel éligible : demande expirée (§5.8). */
    case Expired = 'expired';
    /** Demande hors recherche (acceptée, annulée…) : rien à faire. */
    case Idle = 'idle';
}
