import { useEffect, useState } from 'react';

function secondsUntil(deadline: string | null): number {
  if (!deadline) {
    return 0;
  }
  return Math.max(0, Math.round((new Date(deadline).getTime() - Date.now()) / 1000));
}

/** Secondes restantes avant une échéance ISO renvoyée par l'API (réservation, délai de réponse), arrêtées à 0. */
export function useSecondsUntil(deadline: string | null): number {
  const [remaining, setRemaining] = useState(() => secondsUntil(deadline));

  useEffect(() => {
    setRemaining(secondsUntil(deadline));
    const id = setInterval(() => setRemaining(secondsUntil(deadline)), 1000);
    return () => clearInterval(id);
  }, [deadline]);

  return remaining;
}
