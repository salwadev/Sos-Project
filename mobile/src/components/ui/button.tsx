import Ionicons from '@expo/vector-icons/Ionicons';
import type { ComponentProps } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, type StyleProp, type ViewStyle } from 'react-native';

import { Colors, Radius, Spacing } from '@/constants/theme';

type Variant = 'primary' | 'outline' | 'ghost' | 'danger' | 'onDark';

type Props = {
  label: string;
  onPress?: () => void;
  variant?: Variant;
  icon?: ComponentProps<typeof Ionicons>['name'];
  disabled?: boolean;
  loading?: boolean;
  style?: StyleProp<ViewStyle>;
};

export function Button({ label, onPress, variant = 'primary', icon, disabled, loading, style }: Props) {
  const v = variants[variant];
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: inactive }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [styles.base, v.container, inactive && styles.disabled, pressed && styles.pressed, style]}>
      {loading ? (
        <ActivityIndicator color={v.text.color} />
      ) : (
        <>
          {icon ? <Ionicons name={icon} size={20} color={v.text.color} /> : null}
          <Text style={[styles.label, v.text]}>{label}</Text>
        </>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: 54,
    borderRadius: Radius.md + 2,
    paddingHorizontal: Spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
  },
  label: { fontSize: 16, fontWeight: '700' },
  disabled: { opacity: 0.45 },
  pressed: { opacity: 0.85 },
});

const variants = {
  primary: StyleSheet.create({
    container: { backgroundColor: Colors.primary },
    text: { color: Colors.primaryText },
  }),
  outline: StyleSheet.create({
    container: { backgroundColor: Colors.background, borderWidth: 1.5, borderColor: Colors.primary },
    text: { color: Colors.primaryDark },
  }),
  ghost: StyleSheet.create({
    container: { backgroundColor: 'transparent', minHeight: 40 },
    text: { color: Colors.textSecondary, fontWeight: '600' },
  }),
  danger: StyleSheet.create({
    container: { backgroundColor: Colors.danger },
    text: { color: Colors.primaryText },
  }),
  onDark: StyleSheet.create({
    container: { backgroundColor: 'transparent', borderWidth: 1.5, borderColor: '#3FC1B7', minHeight: 46 },
    text: { color: Colors.primaryText },
  }),
} satisfies Record<Variant, { container: ViewStyle; text: { color: string } }>;
