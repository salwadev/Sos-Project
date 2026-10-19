import { Stack } from 'expo-router';

import { Colors } from '@/constants/theme';

/** Onglets du professionnel + écrans « Nouvelle demande » et « Mission » empilés par-dessus. */
export default function ProLayout() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: Colors.background } }} />;
}
