import Ionicons from '@expo/vector-icons/Ionicons';
import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';

import { EMERGENCY_NUMBERS } from '@/constants/config';
import { Colors, Radius, Spacing } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';

function callSamu() {
  return Linking.openURL(`tel:${EMERGENCY_NUMBERS.samu}`);
}

/** Bandeau complet : orientation santé et écrans sensibles (§10.2, §16.8). */
export function EmergencyBanner() {
  const { t } = useI18n();

  return (
    <View style={styles.banner}>
      <Text style={styles.title}>{t.emergency.title}</Text>
      <Pressable accessibilityRole="button" style={styles.button} onPress={callSamu}>
        <Ionicons name="call" size={18} color={Colors.primaryText} />
        <Text style={styles.buttonText}>{t.emergency.action}</Text>
      </Pressable>
      <Text style={styles.disclaimer}>{t.emergency.disclaimer}</Text>
    </View>
  );
}

/** Accès compact toujours visible sur l'accueil, sans passer par le matching. */
export function SamuPill() {
  const { t } = useI18n();

  return (
    <Pressable accessibilityRole="button" onPress={callSamu} style={styles.pill}>
      <Ionicons name="call" size={14} color={Colors.danger} />
      <Text style={styles.pillText}>{t.emergency.short}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  banner: {
    backgroundColor: Colors.dangerSoft,
    borderRadius: Radius.lg,
    padding: Spacing.md,
    gap: Spacing.sm,
  },
  title: { color: Colors.danger, fontWeight: '700', fontSize: 15 },
  button: {
    flexDirection: 'row',
    gap: Spacing.sm,
    backgroundColor: Colors.danger,
    borderRadius: Radius.md,
    paddingVertical: Spacing.sm + 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: { color: Colors.primaryText, fontWeight: '700', fontSize: 15 },
  disclaimer: { color: Colors.textSecondary, fontSize: 12 },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    alignSelf: 'flex-start',
    backgroundColor: Colors.dangerSoft,
    borderRadius: Radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  pillText: { color: Colors.danger, fontWeight: '700', fontSize: 12 },
});
