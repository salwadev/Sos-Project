import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { Platform, StyleSheet, View } from 'react-native';

import { AuthProvider } from '@/auth/auth-context';
import { Colors } from '@/constants/theme';
import { I18nProvider, useI18n } from '@/i18n/i18n-context';

SplashScreen.preventAutoHideAsync();

function RootStack() {
  const { rtl } = useI18n();
  // Natif : direction Yoga héritée par tout l'arbre. Web : géré par l'attribut `dir` du document (I18nProvider).
  const direction = Platform.OS === 'web' ? undefined : { direction: rtl ? ('rtl' as const) : ('ltr' as const) };

  return (
    <View style={[styles.root, direction]}>
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: Colors.background } }} />
    </View>
  );
}

export default function RootLayout() {
  // Polices d'icônes chargées avant le premier rendu, sinon les icônes restent vides jusqu'au rendu suivant.
  const [fontsLoaded, fontError] = useFonts({ ...Ionicons.font, ...MaterialCommunityIcons.font });
  const ready = fontsLoaded || fontError !== null;

  useEffect(() => {
    if (ready) {
      SplashScreen.hideAsync();
    }
  }, [ready]);

  if (!ready) {
    return null;
  }

  return (
    <I18nProvider>
      <AuthProvider>
        <StatusBar style="dark" />
        <RootStack />
      </AuthProvider>
    </I18nProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
});
