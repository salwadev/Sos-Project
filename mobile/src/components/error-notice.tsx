import Ionicons from '@expo/vector-icons/Ionicons';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { describeError, type ActionError } from '@/hooks/use-action';
import { useI18n } from '@/i18n/i18n-context';

type Props = {
  /** Erreur d'une action (useAction) ou d'un chargement (useApiQuery). */
  error: ActionError | unknown;
  onRetry?: () => void;
};

function isActionError(error: unknown): error is ActionError {
  return typeof error === 'object' && error !== null && 'message' in error && 'conflict' in error;
}

/** Refus du serveur affiché tel quel : un 409 porte un message métier explicite (§8.6). */
export function ErrorNotice({ error, onRetry }: Props) {
  const { t } = useI18n();
  if (!error) {
    return null;
  }
  const described = isActionError(error) ? error : describeError(error, t);

  return (
    <View accessibilityRole="alert" style={[styles.box, described.conflict && styles.conflict]}>
      <Ionicons
        name={described.conflict ? 'alert-circle' : 'cloud-offline-outline'}
        size={22}
        color={described.conflict ? Colors.warning : Colors.danger}
      />
      <View style={styles.body}>
        <Text style={Typography.bodyStrong}>{described.conflict ? t.errors.conflictTitle : t.errors.title}</Text>
        <Text style={Typography.caption}>{described.message}</Text>
        {onRetry ? (
          <Pressable accessibilityRole="button" onPress={onRetry} hitSlop={8}>
            <Text style={styles.retry}>{t.errors.retry}</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

export function LoadingView() {
  const { t } = useI18n();
  return (
    <View style={styles.loading}>
      <ActivityIndicator color={Colors.primary} />
      <Text style={Typography.caption}>{t.errors.loading}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    flexDirection: 'row',
    gap: Spacing.sm + 2,
    backgroundColor: Colors.dangerSoft,
    borderRadius: Radius.md,
    padding: Spacing.md,
  },
  conflict: { backgroundColor: Colors.warningSoft, borderWidth: 1, borderColor: Colors.warningBorder },
  body: { flex: 1, gap: 2 },
  retry: { color: Colors.primaryDark, fontWeight: '700', marginTop: Spacing.xs },
  loading: { alignItems: 'center', justifyContent: 'center', gap: Spacing.sm, paddingVertical: Spacing.xl },
});
