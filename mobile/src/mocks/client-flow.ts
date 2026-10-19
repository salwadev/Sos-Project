import type { McIconName } from '@/components/ui/icon-tile';
import { Colors } from '@/constants/theme';
import type { CategoryCode } from '@/types/service-request';

/** Données factices du parcours client — remplacées par l'API (§14.3) une fois les endpoints prêts. */

export const mockClient = {
  firstName: 'Amine',
  area: 'Grand Tunis',
  district: 'La Marsa',
  address: 'Rue des Orangers, La Marsa',
};

export const categoryStyles: Record<CategoryCode, { icon: McIconName; color: string; background: string }> = {
  home: { icon: 'home-variant', color: Colors.primary, background: Colors.primarySoft },
  car: { icon: 'car', color: Colors.info, background: Colors.infoSoft },
  health: { icon: 'heart-pulse', color: Colors.danger, background: Colors.dangerSoft },
  other: { icon: 'dots-horizontal', color: Colors.textSecondary, background: '#EEF1F4' },
};

export type ServiceCode = 'plumbing' | 'electricity' | 'locksmith';

export const popularServices: { code: ServiceCode; icon: McIconName; color: string }[] = [
  { code: 'plumbing', icon: 'faucet', color: Colors.navy },
  { code: 'electricity', icon: 'lightning-bolt', color: Colors.star },
  { code: 'locksmith', icon: 'lock', color: Colors.navy },
];

export const mockRequest = {
  reference: 'UT-0248',
  title: 'Fuite sous l’évier',
  service: 'plumbing' as ServiceCode,
  description: 'Une fuite sous l’évier de la cuisine.',
  district: 'La Marsa',
  sentAt: '09:41',
};

export const mockPro = {
  name: 'Sami B.',
  verified: true,
  rating: 4.9,
  reviews: 48,
  skills: ['Fuites', 'Ballons d’eau chaude', 'Sanitaires', 'Débouchage', 'Rénovation'],
  travelFee: 20,
  reservationSeconds: 2 * 60 + 48,
  etaMinutes: 8,
  confirmedAt: '09:12',
  onTheWayAt: '09:28',
};

export const searchPros = [
  { initials: 'SB', x: 95, y: 110 },
  { initials: 'KM', x: 320, y: 250 },
  { initials: 'WK', x: 150, y: 300 },
];

export const mockQuote = {
  version: 1,
  lines: [
    { key: 'travel', amount: 20 },
    { key: 'labour', amount: 50 },
    { key: 'parts', amount: 15 },
  ] as const,
  get total() {
    return this.lines.reduce((sum, line) => sum + line.amount, 0);
  },
};
