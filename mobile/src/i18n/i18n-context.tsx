import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { Platform } from 'react-native';

import { detectLanguage, getTranslations, isRTL, type Language, type Translations } from '.';

type I18nValue = {
  language: Language;
  rtl: boolean;
  t: Translations;
  setLanguage: (language: Language) => void;
};

const I18nContext = createContext<I18nValue | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>(detectLanguage);
  const rtl = isRTL(language);

  // Web : react-native-web ne gère pas le style `direction`, on pose `dir`/`lang` sur le document.
  useEffect(() => {
    if (Platform.OS === 'web' && typeof document !== 'undefined') {
      document.documentElement.dir = rtl ? 'rtl' : 'ltr';
      document.documentElement.lang = language;
    }
  }, [language, rtl]);

  const value = useMemo<I18nValue>(
    () => ({ language, rtl, t: getTranslations(language), setLanguage }),
    [language, rtl],
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nValue {
  const value = useContext(I18nContext);
  if (!value) {
    throw new Error('useI18n doit être utilisé dans un I18nProvider');
  }
  return value;
}

/**
 * Direction d'une rangée qui doit rester de gauche à droite même en arabe
 * (numéro de téléphone, code OTP, logo) : la direction globale inverse déjà `row`.
 */
export function useLtrRow(): 'row' | 'row-reverse' {
  return useI18n().rtl ? 'row-reverse' : 'row';
}
