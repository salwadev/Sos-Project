import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';

type QueryState<T> = { data?: T; error?: unknown; loading: boolean };

/**
 * Charge une ressource quand l'écran prend le focus, puis la recharge toutes les `pollMs` millisecondes
 * tant qu'il reste affiché et que l'application est au premier plan (en attendant les notifications push).
 * `key` identifie la ressource : null suspend le chargement, un changement de clé relance un chargement.
 */
export function useApiQuery<T>(key: string | null, fetcher: () => Promise<T>, pollMs?: number) {
  const [state, setState] = useState<QueryState<T>>({ loading: key !== null });
  const fetcherRef = useRef(fetcher);
  const sequence = useRef(0);

  useEffect(() => {
    fetcherRef.current = fetcher;
  });

  const load = useCallback(async () => {
    if (key === null) {
      return;
    }
    const id = ++sequence.current;
    try {
      const data = await fetcherRef.current();
      if (id === sequence.current) {
        setState({ data, loading: false });
      }
    } catch (error) {
      if (id === sequence.current) {
        setState((previous) => ({ data: previous.data, error, loading: false }));
      }
    }
  }, [key]);

  useFocusEffect(
    useCallback(() => {
      load();
      if (!pollMs) {
        return undefined;
      }
      const timer = setInterval(() => {
        if (AppState.currentState === 'active') {
          load();
        }
      }, pollMs);
      return () => clearInterval(timer);
    }, [load, pollMs]),
  );

  return {
    data: state.data,
    error: state.error,
    /** Premier chargement seulement : les rafraîchissements suivants gardent l'affichage. */
    loading: state.loading && state.data === undefined,
    refresh: load,
  };
}
