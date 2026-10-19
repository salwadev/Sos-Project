import { Stack } from 'expo-router';

import { Colors } from '@/constants/theme';

/** Onglets du client + écrans du parcours de demande empilés par-dessus. */
export default function ClientLayout() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: Colors.background } }} />;
}
