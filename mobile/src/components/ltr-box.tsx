import type { ReactNode } from 'react';
import { Platform, View } from 'react-native';

// react-native-web transmet l'attribut HTML `dir` ; il n'existe pas dans les types React Native.
const webLtr = { dir: 'ltr' } as object;

/**
 * Force la direction gauche → droite pour un contrôle qui se dessine mal en RTL
 * (ex. : le `Switch` de react-native-web décroche son curseur sous `dir="rtl"`).
 */
export function LtrBox({ children }: { children: ReactNode }) {
  if (Platform.OS === 'web') {
    return <View {...webLtr}>{children}</View>;
  }
  return <View style={{ direction: 'ltr' }}>{children}</View>;
}
