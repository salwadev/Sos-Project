import { clearTokens, getAccessToken, getRefreshToken, saveTokens } from '@/auth/token-storage';
import { API_URL } from '@/constants/config';

import type { TokenPair } from './types';

type Violation = { propertyPath: string; message: string };

/**
 * Erreur HTTP de l'API. Les erreurs métier arrivent en problem+json : `detail` porte le message explicite
 * (ex. 409 « Cette demande a déjà été réservée par un autre professionnel. »), `violations` les erreurs de saisie (422).
 * `status` vaut 0 quand le serveur n'a pas pu être joint.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(`API error ${status}`);
  }

  get detail(): string | null {
    const body = this.body as { detail?: unknown; message?: unknown } | null;
    if (typeof body?.detail === 'string' && body.detail !== '') {
      return body.detail;
    }
    // Erreurs du pare-feu JWT (Lexik) : { code, message }.
    return typeof body?.message === 'string' ? body.message : null;
  }

  get violations(): Violation[] {
    const body = this.body as { violations?: Violation[] } | null;
    return Array.isArray(body?.violations) ? body.violations : [];
  }

  get isConflict(): boolean {
    return this.status === 409;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: unknown;
  /** Envoi multipart (justificatifs) : le Content-Type et sa frontière sont posés par fetch. */
  form?: FormData;
  /** Clé d'idempotence pour les actions rejouables (cahier des charges §8.6). */
  idempotencyKey?: string;
  /** Appels d'authentification : ni jeton, ni tentative de rafraîchissement. */
  anonymous?: boolean;
};

const sessionExpiredListeners = new Set<() => void>();

/** Prévient l'AuthProvider quand le rafraîchissement échoue : retour à l'écran de connexion. */
export function onSessionExpired(listener: () => void): () => void {
  sessionExpiredListeners.add(listener);
  return () => {
    sessionExpiredListeners.delete(listener);
  };
}

async function send(path: string, options: RequestOptions, token: string | null): Promise<Response> {
  const { method = 'GET', body, form, idempotencyKey } = options;
  const headers: Record<string, string> = { Accept: 'application/ld+json' };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  if (body !== undefined) {
    headers['Content-Type'] = method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json';
  }
  if (idempotencyKey) {
    headers['Idempotency-Key'] = idempotencyKey;
  }

  try {
    return await fetch(`${API_URL}${path}`, {
      method,
      headers,
      body: form ?? (body === undefined ? undefined : JSON.stringify(body)),
    });
  } catch (error) {
    throw new ApiError(0, { detail: null, cause: String(error) });
  }
}

async function parse<T>(response: Response): Promise<T> {
  const data: unknown = response.status === 204 ? null : await response.json().catch(() => null);
  if (!response.ok) {
    throw new ApiError(response.status, data);
  }
  return data as T;
}

let refreshing: Promise<TokenPair | null> | null = null;

/**
 * Rotation du jeton de rafraîchissement : l'ancien est consommé côté serveur, donc un seul appel à la fois
 * (plusieurs écrans qui reçoivent un 401 en même temps attendent le même renouvellement).
 */
export function refreshSession(): Promise<TokenPair | null> {
  refreshing ??= (async () => {
    try {
      const refreshToken = await getRefreshToken();
      if (!refreshToken) {
        return null;
      }
      const response = await send('/auth/refresh', { method: 'POST', body: { refreshToken } }, null);
      if (response.status === 400 || response.status === 401 || response.status === 422) {
        return null;
      }
      const pair = await parse<TokenPair>(response);
      await saveTokens(pair.token, pair.refreshToken);
      return pair;
    } finally {
      refreshing = null;
    }
  })();
  return refreshing;
}

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  if (options.anonymous) {
    return parse<T>(await send(path, options, null));
  }

  const token = await getAccessToken();
  let response = await send(path, options, token);

  // Jeton d'accès expiré (15 min) : un renouvellement puis un seul nouvel essai.
  if (response.status === 401 && token) {
    const pair = await refreshSession();
    if (!pair) {
      await clearTokens();
      sessionExpiredListeners.forEach((listener) => listener());
      return parse<T>(response);
    }
    response = await send(path, options, pair.token);
  }

  return parse<T>(response);
}

/** Clé d'idempotence (caractères admis par l'API : lettres, chiffres, « . _ : - »). */
export function newIdempotencyKey(): string {
  const random = Array.from({ length: 4 }, () => Math.floor(Math.random() * 0x10000).toString(16).padStart(4, '0'));
  return `m-${Date.now().toString(36)}-${random.join('')}`;
}
