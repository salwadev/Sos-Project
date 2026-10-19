import type { AuthProvider } from 'react-admin'

import { entrypoint } from './config.ts'

/** Rôles autorisés dans le back-office (§4.3, §4.4). ROLE_ADMIN inclut ROLE_SUPPORT côté API. */
export const BACKOFFICE_ROLES = ['ROLE_ADMIN', 'ROLE_SUPPORT']

/** Réponse de /api/auth/verify-otp et /api/auth/refresh (App\Auth\TokenPair). */
interface TokenPair {
  token: string
  refreshToken: string
  expiresIn: number
  userId: string
  roles: string[]
}

interface Session {
  token: string
  refreshToken: string
  /** Échéance du jeton d'accès, en ms depuis l'epoch. */
  expiresAt: number
  userId: string
  roles: string[]
  phone: string
}

const STORAGE_KEY = 'sos.backoffice.session'
/** Marge avant l'échéance du JWT (15 min) : on rafraîchit un peu avant pour ne pas envoyer un jeton périmé. */
const REFRESH_MARGIN_MS = 30_000

function readSession(): Session | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as Session) : null
  } catch {
    return null
  }
}

function saveSession(pair: TokenPair, phone: string): Session {
  const session: Session = {
    token: pair.token,
    refreshToken: pair.refreshToken,
    expiresAt: Date.now() + pair.expiresIn * 1000,
    userId: pair.userId,
    roles: pair.roles,
    phone,
  }
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session))
  } catch {
    // Stockage indisponible (navigation privée stricte) : la session ne survivra pas au rechargement.
  }
  memorySession = session
  return session
}

function clearSession(): void {
  memorySession = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    // rien à nettoyer
  }
}

let memorySession: Session | null = readSession()

const session = (): Session | null => memorySession

const isBackofficeUser = (roles: string[]) => roles.some((role) => BACKOFFICE_ROLES.includes(role))

/** Erreur d'appel à l'API d'authentification, avec le `detail` renvoyé par le backend. */
export class AuthApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

async function postAuth<T>(path: string, body: Record<string, string>): Promise<T | null> {
  const response = await fetch(`${entrypoint}/auth/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    let message = `Erreur ${response.status}`
    try {
      const problem = await response.json()
      message = problem.detail ?? problem.description ?? problem.message ?? message
    } catch {
      // corps vide ou non JSON
    }
    throw new AuthApiError(message, response.status)
  }
  return response.status === 204 || response.status === 202 ? null : ((await response.json()) as T)
}

/** Étape 1 : envoie le code à 6 chiffres par SMS (en dev : backend/var/sms_outbox.log). */
export async function requestOtp(phone: string): Promise<void> {
  await postAuth('request-otp', { phone })
}

let pendingRefresh: Promise<Session | null> | null = null

/**
 * Rotation du jeton de rafraîchissement (usage unique côté API : deux rotations simultanées révoqueraient
 * toute la session). Les appels concurrents partagent donc la même promesse.
 */
export function refreshSession(): Promise<Session | null> {
  const current = session()
  if (!current) {
    return Promise.resolve(null)
  }
  pendingRefresh ??= postAuth<TokenPair>('refresh', { refreshToken: current.refreshToken })
    .then((pair) => (pair ? saveSession(pair, current.phone) : null))
    .catch(() => {
      clearSession()
      return null
    })
    .finally(() => {
      pendingRefresh = null
    })
  return pendingRefresh
}

/** Jeton d'accès valide (rafraîchi s'il arrive à échéance), ou null si la session est perdue. */
export async function accessToken(): Promise<string | null> {
  const current = session()
  if (!current) {
    return null
  }
  if (current.expiresAt - REFRESH_MARGIN_MS > Date.now()) {
    return current.token
  }
  return (await refreshSession())?.token ?? null
}

function withBearer(init: RequestInit, token: string | null): RequestInit {
  const headers = new Headers(init.headers)
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }
  return { ...init, headers }
}

/** fetch authentifié : ajoute le Bearer, et sur 401 tente une rotation puis rejoue une fois. */
export async function authFetch(url: string | URL, init: RequestInit = {}): Promise<Response> {
  let response = await fetch(url, withBearer(init, await accessToken()))
  if (response.status === 401 && (await refreshSession())) {
    response = await fetch(url, withBearer(init, await accessToken()))
  }
  return response
}

export const authProvider: AuthProvider = {
  async login({ phone, code }: { phone: string; code: string }) {
    const pair = await postAuth<TokenPair>('verify-otp', { phone, code })
    if (!pair) {
      throw new Error('Réponse inattendue du serveur.')
    }
    if (!isBackofficeUser(pair.roles)) {
      await postAuth('logout', { refreshToken: pair.refreshToken }).catch(() => undefined)
      throw new Error('Accès réservé à l’administration et au support.')
    }
    saveSession(pair, phone)
  },

  async logout() {
    const current = session()
    clearSession()
    if (current) {
      await postAuth('logout', { refreshToken: current.refreshToken }).catch(() => undefined)
    }
  },

  async checkAuth() {
    if (!session()) {
      throw { message: false }
    }
  },

  /** Appelé par react-admin après chaque erreur du dataProvider : un 401 (rotation déjà tentée) déconnecte. */
  async checkError(error: { status?: number }) {
    if (error?.status === 401) {
      clearSession()
      throw new Error('Session expirée, reconnectez-vous.')
    }
  },

  async getPermissions() {
    return session()?.roles ?? []
  },

  async getIdentity() {
    const current = session()
    if (!current) {
      throw new Error('Non connecté.')
    }
    return { id: current.userId, fullName: current.phone }
  },
}
