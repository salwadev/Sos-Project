import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Colors, Spacing } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';

type Props = {
  title: string;
  right?: ReactNode;
  onBack?: () => void;
  /** Fond transparent pour les écrans posés sur une carte. */
  overlay?: boolean;
};

export function ScreenHeader({ title, right, onBack, overlay }: Props) {
  const { rtl } = useI18n();

  return (
    <View style={[styles.row, overlay && styles.overlay]}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Retour"
        hitSlop={12}
        onPress={onBack ?? (() => (router.canGoBack() ? router.back() : router.replace('/client')))}
        style={styles.side}>
        <Ionicons name={rtl ? 'arrow-forward' : 'arrow-back'} size={24} color={Colors.text} />
      </Pressable>
      <Text style={styles.title} numberOfLines={1}>
        {title}
      </Text>
      <View style={[styles.side, styles.right]}>{right}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm + 4,
    backgroundColor: Colors.background,
  },
  overlay: { backgroundColor: 'transparent' },
  side: { minWidth: 44 },
  right: { alignItems: 'flex-end' },
  title: { flex: 1, textAlign: 'center', fontSize: 18, fontWeight: '700', color: Colors.text },
});
