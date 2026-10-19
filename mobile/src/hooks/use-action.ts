import { useState } from 'react';

import { ApiError } from '@/api/client';
import type { Translations } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';

export type ActionError = { message: string; conflict: boolean };

/** Message à montrer pour une erreur d'API : le `detail` du serveur quand il est explicite (409, 422…). */
export function describeError(error: unknown, t: Translations): ActionError {
  if (!(error instanceof ApiError)) {
    return { message: t.errors.generic, conflict: false };
  }
  if (error.status === 0) {
    return { message: t.errors.network, conflict: false };
  }
  if (error.status === 401) {
    return { message: t.errors.session, conflict: false };
  }
  const violations = error.violations.map((v) => v.message);
  if (violations.length > 0) {
    return { message: violations.join('\n'), conflict: false };
  }
  return { message: error.detail ?? t.errors.generic, conflict: error.isConflict };
}

/**
 * Exécute une action (POST, PATCH…) en exposant son état : `pending` pour désactiver les boutons,
 * `error` pour afficher le refus du serveur. Un 409 (demande déjà réservée, délai dépassé…) est signalé
 * comme conflit : l'écran l'explique puis se recharge pour montrer l'état réel.
 */
export function useAction() {
  const { t } = useI18n();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<ActionError | null>(null);

  const run = async <R>(action: () => Promise<R>, onConflict?: () => void): Promise<R | undefined> => {
    setPending(true);
    setError(null);
    try {
      return await action();
    } catch (e) {
      const described = describeError(e, t);
      setError(described);
      if (described.conflict) {
        onConflict?.();
      }
      return undefined;
    } finally {
      setPending(false);
    }
  };

  return { run, pending, error, clearError: () => setError(null) };
}
