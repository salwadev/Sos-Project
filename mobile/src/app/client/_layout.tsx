import { Redirect, Stack } from 'expo-router';

import { useAuth } from '@/auth/auth-context';
import { Colors } from '@/constants/theme';

/** Onglets du client + écrans du parcours de demande empilés par-dessus. Réservé aux utilisateurs connectés. */
export default function ClientLayout() {
  const auth = useAuth();
  if (auth.status === 'loading') {
    return null;
  }
  if (auth.status === 'signedOut') {
    return <Redirect href="/" />;
  }
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: Colors.background } }} />;
}
