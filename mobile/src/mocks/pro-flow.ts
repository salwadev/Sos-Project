import { useSyncExternalStore } from 'react';

/** Données factices de l'espace professionnel — remplacées par l'API (§14.4) une fois les endpoints prêts. */

export const mockProUser = {
  firstName: 'Sami',
  name: 'Sami B.',
  trade: 'plumbing' as const,
  zones: ['La Marsa', 'Carthage', 'Sidi Bou Saïd'],
  documentsValidUntil: '30/06/2027',
  availableUntil: '18:30',
};

export const mockIncoming = {
  reference: 'UT-0248',
  title: 'Fuite sous l’évier',
  description: 'Une fuite sous l’évier de la cuisine.',
  district: 'La Marsa',
  /** Délai de réponse avant que la demande parte au professionnel suivant (§5.8). */
  responseSeconds: 42,
  travelFee: 20,
};

export const mockMission = {
  reference: 'UT-0248',
  client: 'Amine',
  address: 'Rue des Orangers, La Marsa',
  job: 'Réparation d’une fuite',
  quoteTotal: 85,
  onSiteAt: '10:15',
  quoteAcceptedAt: '10:42',
  workStartedAt: '10:45',
  /** Temps restant au client pour confirmer la proposition. */
  clientConfirmSeconds: 12 * 60 + 36,
};

export const mockStats = {
  completed: 2,
  declaredPayments: 170,
  commissionsDue: 17,
};

export const mockStatements = {
  period: 'octobre 2026',
  declaredPayments: 850,
  commissionsToPay: 85,
  /** Taux de commission de démonstration — configurable par catégorie côté admin (§9.4). */
  commissionRate: 0.1,
  lines: [
    { kind: 'intervention' as const, reference: 'UT-0248', date: '12 oct. 2026', amount: 85 },
    { kind: 'payment' as const, reference: 'VR-0031', date: '8 oct. 2026', amount: 50 },
  ],
};

export const mockPastMissions = [
  { reference: 'UT-0241', trade: 'plumbing' as const, district: 'Carthage', date: '5 oct. 2026', amount: 60 },
  { reference: 'UT-0236', trade: 'plumbing' as const, district: 'La Marsa', date: '1 oct. 2026', amount: 110 },
];

/**
 * Étape de la démo, partagée entre les écrans du parcours pro :
 * incoming → proposed (attente du client) → inProgress → completionDeclared.
 */
export type ProDemoStage = 'incoming' | 'proposed' | 'inProgress' | 'completionDeclared';

type DemoState = { stage: ProDemoStage; available: boolean; travelFee: number };

let state: DemoState = { stage: 'incoming', available: true, travelFee: mockIncoming.travelFee };
const listeners = new Set<() => void>();

function emit() {
  listeners.forEach((listener) => listener());
}

export const proDemo = {
  setStage(stage: ProDemoStage) {
    state = { ...state, stage };
    emit();
  },
  setAvailable(available: boolean) {
    state = { ...state, available };
    emit();
  },
  setTravelFee(travelFee: number) {
    state = { ...state, travelFee };
    emit();
  },
  reset() {
    state = { stage: 'incoming', available: true, travelFee: mockIncoming.travelFee };
    emit();
  },
};

function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function useProDemo(): DemoState {
  return useSyncExternalStore(subscribe, () => state, () => state);
}
