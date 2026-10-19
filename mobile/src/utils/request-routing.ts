import type { Href } from 'expo-router';

import type { Intervention, ServiceRequest } from '@/api/types';
import type { ServiceRequestStatus } from '@/types/service-request';

/** Demandes encore suivies par le client (§8.4). */
export const ACTIVE_REQUEST_STATUSES: ServiceRequestStatus[] = ['SUBMITTED', 'SEARCHING', 'OFFERED', 'ACCEPTED', 'IN_PROGRESS'];

export function isActiveRequest(request: ServiceRequest): boolean {
  return ACTIVE_REQUEST_STATUSES.includes(request.status);
}

export function interventionFor(interventions: Intervention[] | undefined, requestId: string): Intervention | undefined {
  return interventions?.find((i) => i.request.id === requestId);
}

/** Écran à ouvrir pour une demande : recherche tant qu'aucun professionnel n'est confirmé, suivi ensuite. */
export function clientRequestHref(request: ServiceRequest, intervention?: Intervention): Href {
  if (intervention) {
    return { pathname: '/client/tracking', params: { id: intervention.id } };
  }
  return { pathname: '/client/search', params: { id: request.id } };
}

export type BadgeTone = 'warning' | 'success' | 'neutral' | 'primary';

export function requestStatusTone(status: ServiceRequestStatus): BadgeTone {
  switch (status) {
    case 'SUBMITTED':
    case 'SEARCHING':
    case 'OFFERED':
      return 'warning';
    case 'ACCEPTED':
    case 'IN_PROGRESS':
      return 'primary';
    case 'COMPLETED':
      return 'success';
    default:
      return 'neutral';
  }
}
