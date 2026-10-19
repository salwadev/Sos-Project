import { useEffect, useState } from 'react';

/** Compte à rebours en secondes, arrêté à 0. */
export function useCountdown(initialSeconds: number): number {
  const [remaining, setRemaining] = useState(initialSeconds);

  useEffect(() => {
    const id = setInterval(() => {
      setRemaining((s) => (s > 0 ? s - 1 : 0));
    }, 1000);
    return () => clearInterval(id);
  }, []);

  return remaining;
}
