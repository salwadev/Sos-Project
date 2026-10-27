import { useEffect, useState } from 'react';

/** Secondes restantes avant une échéance ISO renvoyée par l'API (réservation, délai de réponse), arrêtées à 0. */
export function useSecondsUntil(deadline: string | null): number {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);

  if (!deadline) {
    return 0;
  }
  return Math.max(0, Math.round((new Date(deadline).getTime() - now) / 1000));
}
