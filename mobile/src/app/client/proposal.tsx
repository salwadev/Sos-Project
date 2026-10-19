import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ProSummary } from '@/components/pro-summary';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useCountdown } from '@/hooks/use-countdown';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockPro } from '@/mocks/client-flow';
import { formatCountdown, formatTND } from '@/utils/format';

export default function Proposal() {
  const { t } = useI18n();
  const remaining = useCountdown(mockPro.reservationSeconds);
  const [travelAccepted, setTravelAccepted] = useState(true);
  const expired = remaining === 0;

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={t.proposal.title} />}
      footer={
        <>
          <Button
            label={t.proposal.confirm}
            disabled={!travelAccepted || expired}
            onPress={() => router.replace('/client/tracking')}
          />
          <Button label={t.proposal.refuse} variant="ghost" onPress={() => router.replace('/client/search')} />
        </>
      }>
      <View style={[styles.timer, expired && styles.timerExpired]}>
        <Ionicons name="time-outline" size={20} color={expired ? Colors.danger : Colors.warning} />
        <Text style={[styles.timerText, expired && styles.timerTextExpired]}>
          {expired ? t.proposal.expired : interpolate(t.proposal.reservedFor, { time: formatCountdown(remaining) })}
        </Text>
      </View>

      <Card>
        <ProSummary chevron />
        <View style={styles.chips}>
          {mockPro.skills.map((skill) => (
            <View key={skill} style={styles.chip}>
              <Text style={styles.chipText}>{skill}</Text>
            </View>
          ))}
        </View>
      </Card>

      <Card>
        <View style={styles.feeRow}>
          <MaterialCommunityIcons name="truck-outline" size={24} color={Colors.text} />
          <Text style={[Typography.body, styles.grow]}>{t.proposal.travel}</Text>
          <Text style={Typography.bodyStrong}>{formatTND(mockPro.travelFee)}</Text>
        </View>
        <View style={styles.divider} />
        <View style={styles.feeRow}>
          <MaterialCommunityIcons name="tools" size={24} color={Colors.text} />
          <Text style={[Typography.body, styles.grow]}>{t.proposal.worksAfterDiagnosis}</Text>
        </View>
      </Card>

      <Card tone="primarySoft">
        <View style={styles.feeRow}>
          <Ionicons name="checkmark-circle" size={32} color={Colors.primary} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{t.proposal.agreementTitle}</Text>
            <Text style={Typography.caption}>{t.proposal.agreementText}</Text>
          </View>
        </View>
      </Card>

      <Pressable
        accessibilityRole="checkbox"
        accessibilityState={{ checked: travelAccepted }}
        onPress={() => setTravelAccepted((v) => !v)}
        style={styles.checkRow}>
        <View style={[styles.checkbox, travelAccepted && styles.checkboxOn]}>
          {travelAccepted ? <Ionicons name="checkmark" size={18} color={Colors.primaryText} /> : null}
        </View>
        <View style={styles.grow}>
          <Text style={Typography.body}>{t.proposal.acceptTravel}</Text>
          <Text style={Typography.caption}>{t.proposal.acceptTravelHint}</Text>
        </View>
      </Pressable>
    </Screen>
  );
}

const styles = StyleSheet.create({
  timer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    backgroundColor: Colors.warningSoft,
    borderWidth: 1,
    borderColor: Colors.warningBorder,
    borderRadius: Radius.md,
    paddingVertical: Spacing.sm + 4,
  },
  timerExpired: { backgroundColor: Colors.dangerSoft, borderColor: Colors.dangerSoft },
  timerText: { color: Colors.warning, fontWeight: '700', fontSize: 15, fontVariant: ['tabular-nums'] },
  timerTextExpired: { color: Colors.danger },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm, marginTop: Spacing.md },
  chip: { backgroundColor: Colors.surface, borderRadius: Radius.sm, paddingHorizontal: Spacing.sm + 4, paddingVertical: 6 },
  chipText: { fontSize: 13, color: Colors.text },
  feeRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  divider: { height: 1, backgroundColor: Colors.border, marginVertical: Spacing.md },
  checkRow: { flexDirection: 'row', gap: Spacing.sm + 4, alignItems: 'flex-start' },
  checkbox: {
    width: 26,
    height: 26,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: Colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxOn: { backgroundColor: Colors.primary, borderColor: Colors.primary },
});
