import { StyleSheet, Text, View } from 'react-native';

import { Colors, Radius, Spacing } from '@/constants/theme';

type Tone = 'warning' | 'success' | 'neutral' | 'primary';

export function Badge({ label, tone = 'neutral' }: { label: string; tone?: Tone }) {
  const t = tones[tone];
  return (
    <View style={[styles.base, { backgroundColor: t.bg }]}>
      <Text style={[styles.text, { color: t.fg }]}>{label}</Text>
    </View>
  );
}

const tones: Record<Tone, { bg: string; fg: string }> = {
  warning: { bg: '#FFD769', fg: '#5A3D00' },
  success: { bg: Colors.primarySoft, fg: Colors.primaryDark },
  neutral: { bg: Colors.surface, fg: Colors.textSecondary },
  primary: { bg: Colors.primary, fg: Colors.primaryText },
};

const styles = StyleSheet.create({
  base: { borderRadius: Radius.pill, paddingHorizontal: Spacing.sm + 2, paddingVertical: 3, alignSelf: 'flex-start' },
  text: { fontSize: 11, fontWeight: '700' },
});
