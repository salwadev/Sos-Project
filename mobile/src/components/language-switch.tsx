import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Colors, Radius } from '@/constants/theme';
import type { Language } from '@/i18n';
import { useI18n, useLtrRow } from '@/i18n/i18n-context';

const OPTIONS: { code: Language; label: string }[] = [
  { code: 'fr', label: 'FR' },
  { code: 'ar', label: 'العربية' },
];

/** Bascule FR | العربية en pilule, comme sur l'écran de bienvenue. */
export function LanguageSwitch() {
  const { language, setLanguage } = useI18n();
  const ltrRow = useLtrRow();

  return (
    <View style={[styles.pill, { flexDirection: ltrRow }]}>
      {OPTIONS.map((option) => {
        const active = option.code === language;
        return (
          <Pressable
            key={option.code}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            onPress={() => setLanguage(option.code)}
            style={[styles.option, active && styles.optionActive]}>
            <Text style={[styles.label, active && styles.labelActive]}>{option.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.pill,
    padding: 3,
    alignSelf: 'flex-end',
    backgroundColor: Colors.background,
  },
  option: { paddingHorizontal: 14, paddingVertical: 6, borderRadius: Radius.pill },
  optionActive: { backgroundColor: Colors.primary },
  label: { fontSize: 13, fontWeight: '700', color: Colors.textSecondary },
  labelActive: { color: Colors.primaryText },
});
