import type { Service } from '@/api/types';
import type { McIconName } from '@/components/ui/icon-tile';
import type { Language } from '@/i18n';
import { Colors } from '@/constants/theme';
import type { CategoryCode } from '@/types/service-request';

/** Présentation des catégories du catalogue (codes chargés par app:seed-catalog). */
export const categoryStyles: Record<CategoryCode, { icon: McIconName; color: string; background: string }> = {
  home: { icon: 'home-variant', color: Colors.primary, background: Colors.primarySoft },
  car: { icon: 'car', color: Colors.info, background: Colors.infoSoft },
  health: { icon: 'heart-pulse', color: Colors.danger, background: Colors.dangerSoft },
  other: { icon: 'dots-horizontal', color: Colors.textSecondary, background: '#EEF1F4' },
};

export function isCategoryCode(code: string): code is CategoryCode {
  return code in categoryStyles;
}

const SERVICE_ICONS: Record<string, McIconName> = {
  plumbing: 'faucet',
  electricity: 'lightning-bolt',
  locksmith: 'lock',
  glazing: 'window-closed-variant',
  air_conditioning: 'air-conditioner',
  appliances: 'washing-machine',
  battery: 'car-battery',
  tyre: 'tire',
  roadside: 'car-wrench',
  towing: 'tow-truck',
  on_call_dentist: 'tooth-outline',
  doctor: 'stethoscope',
  home_it_support: 'laptop',
};

export function serviceIcon(serviceCode: string | undefined, categoryCode?: string): McIconName {
  if (serviceCode && SERVICE_ICONS[serviceCode]) {
    return SERVICE_ICONS[serviceCode];
  }
  return categoryCode && isCategoryCode(categoryCode) ? categoryStyles[categoryCode].icon : 'wrench-outline';
}

/** Services mis en avant sur l'accueil client. */
export const POPULAR_SERVICE_CODES = ['plumbing', 'electricity', 'locksmith'];

export function serviceName(service: Pick<Service, 'nameFr' | 'nameAr'>, language: Language): string {
  return language === 'ar' ? service.nameAr : service.nameFr;
}

/** Retrouve un service du catalogue par son code ou par l'IRI renvoyée dans une demande (/api/services/{id}). */
export function findService(services: Service[] | undefined, codeOrIri: string): Service | undefined {
  return services?.find((s) => s.code === codeOrIri || s['@id'] === codeOrIri || codeOrIri.endsWith(`/${s.id}`));
}
