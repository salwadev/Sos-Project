import { apiFetch } from './client';
import type {
  Availability,
  DocumentType,
  HydraCollection,
  Intervention,
  Offer,
  ProfessionalDocument,
  ProfessionalProfile,
  ProfessionalProfileInput,
  ProfessionalStep,
  QuoteLine,
  Service,
  ServiceArea,
  ServiceRequest,
  TokenPair,
  Urgency,
} from './types';

const members = <T>(collection: HydraCollection<T>) => collection.member;

/** Numéro saisi (8 chiffres) au format attendu par l'API. */
export function toE164(digits: string): string {
  return `+216${digits}`;
}

export const authApi = {
  /** Réponse 202 identique que le numéro soit inscrit ou non (§5.1). */
  requestOtp: (phone: string) =>
    apiFetch<null>('/auth/request-otp', { method: 'POST', body: { phone }, anonymous: true }),
  verifyOtp: (phone: string, code: string) =>
    apiFetch<TokenPair>('/auth/verify-otp', { method: 'POST', body: { phone, code }, anonymous: true }),
  logout: (refreshToken: string) =>
    apiFetch<null>('/auth/logout', { method: 'POST', body: { refreshToken }, anonymous: true }),
};

export const catalogApi = {
  services: () => apiFetch<HydraCollection<Service>>('/services').then(members),
  areas: () => apiFetch<HydraCollection<ServiceArea>>('/service-areas').then(members),
};

export type CreateRequestInput = {
  serviceCode: string;
  urgency: Urgency;
  description: string;
  addressLine: string;
  landmark?: string | null;
  latitude: number;
  longitude: number;
};

export const requestsApi = {
  /** Demandes du client connecté, les plus récentes d'abord. */
  list: () => apiFetch<HydraCollection<ServiceRequest>>('/requests').then(members),
  get: (id: string) => apiFetch<ServiceRequest>(`/requests/${id}`),
  /** La clé d'idempotence évite les doublons quand l'envoi est rejoué sur réseau faible (§8.6). */
  create: (input: CreateRequestInput, idempotencyKey: string) =>
    apiFetch<ServiceRequest>('/requests', { method: 'POST', body: input, idempotencyKey }),
  cancel: (id: string, reason?: string) =>
    apiFetch<ServiceRequest>(`/requests/${id}/cancel`, { method: 'POST', body: { reason: reason ?? null } }),
  /** Offres réservées, confirmées ou refusées, avec le nom public et la note du professionnel. */
  offers: (id: string) => apiFetch<HydraCollection<Offer>>(`/requests/${id}/offers`).then(members),
};

export const offersApi = {
  /** Professionnel : réserve la demande (frais revus à la baisse possibles). Client : confirme le professionnel. */
  accept: (id: string, travelFeeMillimes?: number) =>
    apiFetch<Offer>(`/offers/${id}/accept`, {
      method: 'POST',
      body: travelFeeMillimes === undefined ? {} : { travelFeeMillimes },
    }),
  /** Professionnel : décline. Client : refuse la proposition, la recherche reprend. */
  decline: (id: string) => apiFetch<Offer>(`/offers/${id}/decline`, { method: 'POST', body: {} }),
};

export const interventionsApi = {
  /** Interventions de l'utilisateur, comme client ou comme professionnel assigné. */
  list: () => apiFetch<HydraCollection<Intervention>>('/interventions').then(members),
  get: (id: string) => apiFetch<Intervention>(`/interventions/${id}`),
  updateStatus: (id: string, status: ProfessionalStep, estimatedArrivalMinutes?: number) =>
    apiFetch<Intervention>(`/interventions/${id}/status`, {
      method: 'POST',
      body: estimatedArrivalMinutes === undefined ? { status } : { status, estimatedArrivalMinutes },
    }),
  proposeQuote: (id: string, lines: QuoteLine[], explanation?: string) =>
    apiFetch<Intervention>(`/interventions/${id}/quotes`, {
      method: 'POST',
      body: { lines, explanation: explanation || null },
    }),
  confirmCompletion: (id: string) =>
    apiFetch<Intervention>(`/interventions/${id}/confirm-completion`, { method: 'POST', body: {} }),
  confirmCashPayment: (id: string) =>
    apiFetch<Intervention>(`/interventions/${id}/payment/confirm-cash`, { method: 'POST', body: {} }),
  dispute: (id: string, reason: string) =>
    apiFetch<Intervention>(`/interventions/${id}/dispute`, { method: 'POST', body: { reason } }),
  review: (id: string, rating: number, comment?: string) =>
    apiFetch<Intervention>(`/interventions/${id}/reviews`, {
      method: 'POST',
      body: { rating, comment: comment || null },
    }),
};

export const quotesApi = {
  accept: (id: string) => apiFetch<Intervention>(`/quotes/${id}/accept`, { method: 'POST', body: {} }),
  reject: (id: string) => apiFetch<Intervention>(`/quotes/${id}/reject`, { method: 'POST', body: {} }),
};

/** Fichier à déposer : objet { uri, name, type } sur mobile, Blob/File sur le web. */
export type UploadFile = Blob | { uri: string; name: string; type: string };

export const proApi = {
  profile: () => apiFetch<ProfessionalProfile>('/professional/profile'),
  /** Crée ou met à jour le profil ; le compte reçoit le rôle professionnel et reste « pending ». */
  saveProfile: (input: ProfessionalProfileInput) =>
    apiFetch<ProfessionalProfile>('/professional/profile', { method: 'POST', body: input }),
  uploadDocument: (file: UploadFile, type: DocumentType, expiresAt?: string) => {
    const form = new FormData();
    // FormData de React Native accepte { uri, name, type } ; le typage DOM ne le connaît pas.
    form.append('file', file as Blob);
    form.append('type', type);
    if (expiresAt) {
      form.append('expiresAt', expiresAt);
    }
    return apiFetch<ProfessionalDocument>('/professional/documents', { method: 'POST', form });
  },
  availability: () => apiFetch<Availability>('/professional/availability'),
  /** merge-patch : seuls les champs envoyés changent. */
  updateAvailability: (patch: { available?: boolean; durationMinutes?: number; latitude?: number; longitude?: number }) =>
    apiFetch<Availability>('/professional/availability', { method: 'PATCH', body: patch }),
  /** Sollicitations ouvertes et réservations en cours : zone approximative seulement. */
  offers: () => apiFetch<HydraCollection<Offer>>('/professional/requests').then(members),
};
