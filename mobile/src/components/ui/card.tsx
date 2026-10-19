import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { Colors, Radius, Shadow, Spacing } from '@/constants/theme';

type Props = {
  children: ReactNode;
  onPress?: () => void;
  tone?: 'default' | 'soft' | 'navy' | 'warning' | 'primarySoft';
  style?: StyleProp<ViewStyle>;
};

export function Card({ children, onPress, tone = 'default', style }: Props) {
  const content = [styles.base, tones[tone], style];

  if (onPress) {
    return (
      <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [content, pressed && styles.pressed]}>
        {children}
      </Pressable>
    );
  }
  return <View style={content}>{children}</View>;
}

const styles = StyleSheet.create({
  base: { borderRadius: Radius.lg, padding: Spacing.md },
  pressed: { opacity: 0.9 },
});

const tones = StyleSheet.create({
  default: { backgroundColor: Colors.background, borderWidth: 1, borderColor: Colors.border, ...Shadow },
  soft: { backgroundColor: Colors.surface },
  navy: { backgroundColor: Colors.navy },
  warning: { backgroundColor: Colors.warningSoft, borderWidth: 1, borderColor: Colors.warningBorder },
  primarySoft: { backgroundColor: Colors.primarySoft },
});
