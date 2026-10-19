import { getLocales } from 'expo-localization';

import { ar } from './ar';
import { fr, type Translations } from './fr';

export type Language = 'fr' | 'ar';

const translations: Record<Language, Translations> = { fr, ar };

/** Langue de l'appareil si supportée, sinon français. */
export function detectLanguage(): Language {
  const code = getLocales()[0]?.languageCode;
  return code === 'ar' ? 'ar' : 'fr';
}

export function isRTL(language: Language): boolean {
  return language === 'ar';
}

export function getTranslations(language: Language): Translations {
  return translations[language];
}

/** Remplace les {variables} d'un libellé : interpolate('Bonjour, {name}', { name: 'Amine' }). */
export function interpolate(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (match, key: string) => (key in values ? String(values[key]) : match));
}

export type { Translations };
