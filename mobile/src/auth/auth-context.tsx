import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { onSessionExpired } from '@/api/client';
import { authApi } from '@/api/endpoints';
import { clearTokens, getAccessToken, getRefreshToken, getSession, saveSession, saveTokens, type StoredSession } from './token-storage';

export type Space = StoredSession['space'];

type AuthState =
  | { status: 'loading' }
  | { status: 'signedOut' }
  | { status: 'signedIn'; session: StoredSession };

type AuthValue = AuthState & {
  /** Vérifie le code OTP, stocke les jetons et ouvre l'espace demandé. */
  signIn: (phone: string, code: string, space: Space) => Promise<StoredSession>;
  signOut: () => Promise<void>;
  /** Mémorise l'espace (client / pro) rouvert au prochain lancement. */
  setSpace: (space: Space) => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({ status: 'loading' });

  // Reprise de session sans réseau : le jeton d'accès expiré sera renouvelé au premier 401 (api/client).
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const [access, refresh, session] = await Promise.all([getAccessToken(), getRefreshToken(), getSession()]);
      if (!cancelled) {
        setState(access && refresh && session ? { status: 'signedIn', session } : { status: 'signedOut' });
      }
    })().catch(() => {
      if (!cancelled) {
        setState({ status: 'signedOut' });
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => onSessionExpired(() => setState({ status: 'signedOut' })), []);

  const value = useMemo<AuthValue>(() => {
    const signIn = async (phone: string, code: string, space: Space) => {
      const pair = await authApi.verifyOtp(phone, code);
      await saveTokens(pair.token, pair.refreshToken);
      const session: StoredSession = { phone, roles: pair.roles, space };
      await saveSession(session);
      setState({ status: 'signedIn', session });
      return session;
    };

    const signOut = async () => {
      const refreshToken = await getRefreshToken();
      if (refreshToken) {
        // Révocation côté serveur ; la déconnexion locale a lieu même hors ligne.
        await authApi.logout(refreshToken).catch(() => undefined);
      }
      await clearTokens();
      setState({ status: 'signedOut' });
    };

    const setSpace = async (space: Space) => {
      if (state.status !== 'signedIn') {
        return;
      }
      const session = { ...state.session, space };
      await saveSession(session);
      setState({ status: 'signedIn', session });
    };

    return { ...state, signIn, signOut, setSpace };
  }, [state]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const value = useContext(AuthContext);
  if (!value) {
    throw new Error('useAuth doit être utilisé dans un AuthProvider');
  }
  return value;
}
