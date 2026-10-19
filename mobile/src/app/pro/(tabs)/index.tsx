import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import { BrandLogo } from '@/components/brand-logo';
import { LtrBox } from '@/components/ltr-box';
import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useCountdown } from '@/hooks/use-countdown';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockIncoming, mockMission, mockProUser, mockStats, proDemo, useProDemo } from '@/mocks/pro-flow';
import { formatCountdown, formatTND } from '@/utils/format';

export default function ProDashboard() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.home;
  const { stage, available } = useProDemo();
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';
  const busy = stage === 'proposed' || stage === 'inProgress';

  const status = !available
    ? { title: s.offlineTitle, text: s.offlineText }
    : busy
      ? { title: s.busyTitle, text: stage === 'proposed' ? s.busyProposed : s.busyMission }
      : { title: s.availableTitle, text: s.availableText };

  return (
    <Screen>
      <View style={styles.topBar}>
        <BrandLogo size={34} />
        <View style={styles.topActions}>
          <Pressable accessibilityRole="button" onPress={() => router.push('/pro/request')} hitSlop={8}>
            <Ionicons name="notifications-outline" size={26} color={Colors.text} />
            {stage === 'incoming' && available ? <View style={styles.notifDot} /> : null}
          </Pressable>
          <Avatar name={mockProUser.name} size={44} online={available} />
        </View>
      </View>

      <View>
        <Text style={Typography.title}>{interpolate(s.greeting, { name: mockProUser.firstName })}</Text>
        <Text style={Typography.caption}>{s.subtitle}</Text>
      </View>

      {stage === 'incoming' && available ? (
        <Card tone="primarySoft" onPress={() => router.push('/pro/request')}>
          <View style={styles.row}>
            <IconTile icon="bell-ring-outline" background={Colors.background} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>{s.incomingTitle}</Text>
              <Text style={Typography.caption}>
                {mockIncoming.title} · {mockIncoming.district}
              </Text>
            </View>
            <Badge label={s.incomingAction} tone="primary" />
          </View>
        </Card>
      ) : null}

      <View style={styles.statusCard}>
        <View style={styles.row}>
          <View style={styles.statusIcon}>
            <Ionicons name={available ? 'briefcase' : 'moon'} size={24} color={Colors.primaryText} />
          </View>
          <View style={styles.grow}>
            <Text style={styles.statusTitle}>{status.title}</Text>
            <Text style={styles.statusText}>{status.text}</Text>
          </View>
        </View>
        <View style={styles.statusDivider} />
        <View style={styles.row}>
          <Ionicons name="calendar-outline" size={22} color={Colors.primaryText} />
          <Text style={[styles.statusText, styles.grow]}>
            {interpolate(s.availableUntil, { time: mockProUser.availableUntil })}
          </Text>
          <LtrBox>
            <Switch
              value={available}
              onValueChange={proDemo.setAvailable}
              trackColor={{ true: Colors.primary, false: '#56707E' }}
              thumbColor={Colors.background}
              accessibilityLabel={status.title}
            />
          </LtrBox>
        </View>
        {available ? <Button label={s.extend} variant="onDark" icon="time-outline" /> : null}
      </View>
      <Text style={Typography.small}>{s.availabilityHint}</Text>

      <View style={styles.stats}>
        <Card style={styles.stat}>
          <IconTile icon="check" round size={40} />
          <Text style={styles.statValue}>{mockStats.completed}</Text>
          <Text style={Typography.caption}>{s.completed}</Text>
        </Card>
        <Card style={styles.stat}>
          <IconTile icon="cash-multiple" round size={40} color={Colors.warning} background={Colors.warningSoft} />
          <Amount value={mockStats.declaredPayments} size={22} style={styles.statValue} />
          <Text style={Typography.caption}>{s.declared}</Text>
        </Card>
      </View>

      <View style={styles.sectionHeader}>
        <Text style={Typography.heading}>{s.activity}</Text>
        <Pressable accessibilityRole="link" onPress={() => router.push('/pro/missions')} style={styles.row}>
          <Text style={styles.seeAll}>{s.seeAll}</Text>
          <Ionicons name={chevron} size={14} color={Colors.textSecondary} />
        </Pressable>
      </View>

      <Card>
        {stage === 'incoming' ? <Text style={Typography.caption}>{s.noActivity}</Text> : <ActivityItem />}
        <View style={styles.divider} />
        <Pressable accessibilityRole="button" onPress={() => router.push('/pro/statements')} style={styles.row}>
          <MaterialCommunityIcons name="chart-pie" size={22} color={Colors.textSecondary} />
          <Text style={[Typography.body, styles.grow]}>{s.commissionsDue}</Text>
          <Text style={Typography.bodyStrong}>{formatTND(mockStats.commissionsDue)}</Text>
          <Ionicons name={chevron} size={18} color={Colors.textSecondary} />
        </Pressable>
      </Card>
    </Screen>
  );
}

/** Mission en cours : proposition réservée, puis intervention confirmée par le client. */
function ActivityItem() {
  const { t } = useI18n();
  const s = t.proSpace.home;
  const { stage } = useProDemo();
  const remaining = useCountdown(mockMission.clientConfirmSeconds);

  const badge =
    stage === 'proposed'
      ? { label: s.waitingClient, tone: 'warning' as const }
      : stage === 'inProgress'
        ? { label: s.inProgress, tone: 'success' as const }
        : { label: s.completionDeclared, tone: 'neutral' as const };

  const openMission = () => {
    // Démo : la confirmation du client arrive quand le pro ouvre la mission.
    if (stage === 'proposed') {
      proDemo.setStage('inProgress');
    }
    router.push('/pro/mission');
  };

  return (
    <Pressable accessibilityRole="button" onPress={openMission} style={styles.activity}>
      <View style={styles.row}>
        <MaterialCommunityIcons name="wrench-outline" size={22} color={Colors.text} />
        <Text style={[Typography.bodyStrong, styles.grow]}>
          {t.services.plumbing} · {mockIncoming.district}
        </Text>
        <Badge label={badge.label} tone={badge.tone} />
      </View>
      <View style={styles.row}>
        <Avatar name={mockMission.client} size={46} color="#3E6A7E" />
        <View style={styles.grow}>
          <Text style={Typography.bodyStrong}>{mockMission.client}</Text>
          <Text style={Typography.caption}>
            {stage === 'proposed'
              ? s.proposalReserved
              : stage === 'inProgress'
                ? s.clientConfirmed
                : s.awaitingConfirmation}
          </Text>
        </View>
        {stage === 'proposed' ? (
          <View style={styles.row}>
            <Ionicons name="time-outline" size={18} color={Colors.text} />
            <Text style={styles.remaining}>{interpolate(s.remaining, { time: formatCountdown(remaining) })}</Text>
          </View>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  topActions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  notifDot: {
    position: 'absolute',
    top: 1,
    right: 2,
    width: 9,
    height: 9,
    borderRadius: 5,
    backgroundColor: Colors.danger,
    borderWidth: 1.5,
    borderColor: Colors.background,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 2 },
  grow: { flex: 1 },
  statusCard: { backgroundColor: Colors.navy, borderRadius: Radius.xl, padding: Spacing.md + 2, gap: Spacing.md },
  statusIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: Colors.navySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statusTitle: { color: Colors.primaryText, fontSize: 22, fontWeight: '800' },
  statusText: { color: '#C9D8DF', fontSize: 14 },
  statusDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.12)' },
  stats: { flexDirection: 'row', gap: Spacing.sm + 4 },
  stat: { flex: 1, gap: Spacing.xs },
  statValue: { fontSize: 22, fontWeight: '800', color: Colors.text, marginTop: Spacing.xs },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  seeAll: { color: Colors.textSecondary, fontSize: 13 },
  activity: { gap: Spacing.md },
  remaining: { fontSize: 13, color: Colors.text, fontVariant: ['tabular-nums'] },
  divider: { height: 1, backgroundColor: Colors.border, marginVertical: Spacing.md },
});
