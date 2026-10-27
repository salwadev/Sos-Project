import { ApiError } from '@/api/client';
import type { Intervention, InterventionStatus } from '@/api/types';

import type { BadgeTone } from './request-routing';

/** Interventions que le professionnel doit encore faire avancer. */
const ACTIVE: InterventionStatus[] = ['confirmed', 'on_the_way', 'on_site', 'working', 'completion_declared'];

export function isActiveIntervention(intervention: Intervention): boolean {
  return ACTIVE.includes(intervention.status);
}

export function interventionTone(status: InterventionStatus): BadgeTone {
  switch (status) {
    case 'completed':
      return 'success';
    case 'completion_declared':
    case 'cancelled':
      return 'neutral';
    case 'disputed':
      return 'warning';
    default:
      return 'primary';
  }
}

/** Le compte n'a pas (encore) de profil professionnel : GET /api/professional/profile répond 403 ou 404. */
export function isMissingProfile(error: unknown): boolean {
  return error instanceof ApiError && (error.status === 403 || error.status === 404);
}

function sameMonth(iso: string | null, now = new Date()): boolean {
  if (!iso) {
    return false;
  }
  const date = new Date(iso);
  return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
}

/** Relevé du mois (§6.7) : prestations terminées, montant déclaré (base de commission) et commissions dues. */
export function monthlyStatement(interventions: Intervention[] | undefined) {
  const lines = (interventions ?? []).filter((i) => i.commission !== null && sameMonth(i.completedAt));
  return {
    lines,
    declaredMillimes: lines.reduce((sum, i) => sum + (i.commission?.baseMillimes ?? 0), 0),
    dueMillimes: lines.reduce((sum, i) => sum + (i.commission?.status === 'due' ? i.commission.amountMillimes : 0), 0),
  };
}
