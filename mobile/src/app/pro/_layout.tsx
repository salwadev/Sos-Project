import { Redirect, Stack } from 'expo-router';

import { useAuth } from '@/auth/auth-context';
import { Colors } from '@/constants/theme';

/**
 * Onglets du professionnel + écrans « Nouvelle demande », « Mission » et « Inscription » empilés par-dessus.
 * Réservé aux utilisateurs connectés ; sans profil professionnel, les onglets renvoient vers l'inscription.
 */
export default function ProLayout() {
  const auth = useAuth();
  if (auth.status === 'loading') {
    return null;
  }
  if (auth.status === 'signedOut') {
    return <Redirect href={{ pathname: '/', params: { space: 'pro' } }} />;
  }
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: Colors.background } }} />;
}
