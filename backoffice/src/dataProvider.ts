import { fetchHydra, hydraDataProvider } from '@api-platform/admin'
import type { HttpClientOptions } from '@api-platform/admin'
import { HttpError } from 'react-admin'

import { accessToken, authFetch, refreshSession } from './auth.ts'
import { entrypoint } from './config.ts'

/** Client Hydra authentifié : Bearer sur chaque appel, rotation et nouvel essai unique sur 401. */
async function httpClient(url: URL, options: HttpClientOptions = {}) {
  const send = async () => {
    const headers = new Headers(typeof options.headers === 'function' ? options.headers() : options.headers)
    const token = await accessToken()
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }
    return fetchHydra(url, { ...options, headers })
  }

  try {
    return await send()
  } catch (error) {
    if (statusOf(error) === 401 && (await refreshSession())) {
      return send().catch((retryError: unknown) => Promise.reject(asHttpError(retryError)))
    }
    throw asHttpError(error)
  }
}

/**
 * fetchHydra rejette avec un HttpError quand la réponse est du JSON-LD, mais avec un simple { response } sinon
 * (ex. le 401 JSON de lexik/jwt) : on récupère le statut dans les deux cas.
 */
function statusOf(error: unknown): number | undefined {
  if (error instanceof HttpError) {
    return error.status
  }
  const response = (error as { response?: unknown } | null)?.response
  return response instanceof Response ? response.status : undefined
}

/** Toujours un HttpError avec son statut, pour que react-admin (checkError) déconnecte sur 401. */
function asHttpError(error: unknown): unknown {
  const status = statusOf(error)
  if (error instanceof HttpError || status === undefined) {
    return error
  }
  return new HttpError(status === 401 ? 'Session expirée, reconnectez-vous.' : `Erreur ${status}`, status)
}

/** Corps de PATCH /api/admin/professionals/{id}/verification (App\ApiResource\Admin\VerificationDecisionInput). */
export interface VerificationDecision {
  action?: 'approve' | 'reject' | 'suspend' | 'reactivate'
  reason: string
  documents?: Array<{
    id: string
    status: 'approved' | 'rejected'
    rejectionReason?: string
    expiresAt?: string
  }>
}

const base = hydraDataProvider({ entrypoint, httpClient, mercure: false })

export const dataProvider = {
  ...base,

  /** Décision admin sur un profil (record.id = IRI /api/admin/professionals/{id}) ; merge-patch exigé par l'API. */
  async reviewProfessional(iri: string, decision: VerificationDecision) {
    const response = await httpClient(new URL(`${iri}/verification`, entrypoint), {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' },
      body: JSON.stringify(decision),
    })
    return { data: response.json }
  },

  /** Contenu d'un justificatif (fichier protégé : pas de lien direct, il faut le Bearer). */
  async fetchDocument(fileUrl: string) {
    const response = await authFetch(new URL(fileUrl, entrypoint))
    if (!response.ok) {
      throw new HttpError(response.status === 403 ? 'Accès refusé à ce justificatif.' : `Erreur ${response.status}`, response.status)
    }
    return { data: await response.blob() }
  },
}

export type BackofficeDataProvider = typeof dataProvider
