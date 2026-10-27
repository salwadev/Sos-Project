import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import { interventionsApi, proApi } from '@/api/endpoints';
import type { Intervention, Offer } from '@/api/types';
import { BrandLogo } from '@/components/brand-logo';
import { ErrorNotice } from '@/components/error-notice';
import { LtrBox } from '@/components/ltr-box';
import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { serviceIcon } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useSecondsUntil } from '@/hooks/use-countdown';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatCountdown, formatMillimes, formatTime } from '@/utils/format';
import { interventionTone, isActiveIntervention, monthlyStatement } from '@/utils/pro';

export default function ProDashboard() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.home;
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';
  const profile = useApiQuery('pro-profile', proApi.profile);
  const availability = useApiQuery('availability', proApi.availability, POLL_INTERVAL_MS);
  const offers = useApiQuery('pro-offers', proApi.offers, POLL_INTERVAL_MS);
  const interventions = useApiQuery('interventions', interventionsApi.list, POLL_INTERVAL_MS);
  const toggle = useAction();

  const available = availability.data?.available ?? false;
  const incoming = offers.data?.filter((o) => o.status === 'sent') ?? [];
  const reserved = offers.data?.find((o) => o.status === 'reserved');
  const active = interventions.data?.filter(isActiveIntervention) ?? [];
  const completed = interventions.data?.filter((i) => i.status === 'completed') ?? [];
  const statement = monthlyStatement(interventions.data);
  const busy = reserved !== undefined || active.length > 0;
  const verification = profile.data?.verificationStatus;

  const status = !available
    ? { title: s.offlineTitle, text: s.offlineText }
    : busy
      ? { title: s.busyTitle, text: reserved ? s.busyProposed : s.busyMission }
      : { title: s.availableTitle, text: s.availableText };

  // merge-patch : passer disponible (ou prolonger) renouvelle la durée par défaut côté serveur.
  const setAvailable = async (value: boolean) => {
    const updated = await toggle.run(() => proApi.updateAvailability({ available: value }), availability.refresh);
    if (updated) {
      availability.refresh();
    }
  };

  return (
    <Screen>
      <View style={styles.topBar}>
        <BrandLogo size={34} />
        <Avatar name={profile.data?.displayName ?? '?'} size={44} online={available} />
      </View>

      <View>
        <Text style={Typography.title}>{interpolate(s.greeting, { name: profile.data?.displayName ?? '' })}</Text>
        <Text style={Typography.caption}>{s.subtitle}</Text>
      </View>

      <ErrorNotice error={profile.error ?? offers.error} onRetry={offers.refresh} />

      {verification && verification !== 'verified' ? (
        <Card tone="warning" onPress={() => router.push('/pro/profile')}>
          <Text style={Typography.bodyStrong}>{t.proSpace.verification[verification]}</Text>
          <Text style={Typography.caption}>{profile.data?.verificationNote ?? t.proSpace.verification.hint}</Text>
        </Card>
      ) : null}

      {incoming.map((offer) => (
        <Card key={offer.id} tone="primarySoft" onPress={() => router.push({ pathname: '/pro/request', params: { id: offer.id } })}>
          <View style={styles.row}>
            <IconTile icon="bell-ring-outline" background={Colors.background} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>{s.incomingTitle}</Text>
              <Text style={Typography.caption} numberOfLines={1}>
                {offer.request.serviceName} · {offer.request.areaName ?? t.proSpace.request.nearby}
              </Text>
            </View>
            <Badge label={s.incomingAction} tone="primary" />
          </View>
        </Card>
      ))}

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
            {available && availability.data?.availableUntil
              ? interpolate(s.availableUntil, { time: formatTime(availability.data.availableUntil) })
              : s.offlineText}
          </Text>
          <LtrBox>
            <Switch
              value={available}
              disabled={toggle.pending || availability.data === undefined}
              onValueChange={setAvailable}
              trackColor={{ true: Colors.primary, false: '#56707E' }}
              thumbColor={Colors.background}
              accessibilityLabel={status.title}
            />
          </LtrBox>
        </View>
        {available ? (
          <Button label={s.extend} variant="onDark" icon="time-outline" loading={toggle.pending} onPress={() => setAvailable(true)} />
        ) : null}
      </View>
      <ErrorNotice error={toggle.error ?? availability.error} />
      <Text style={Typography.small}>{s.availabilityHint}</Text>

      <View style={styles.stats}>
        <Card style={styles.stat}>
          <IconTile icon="check" round size={40} />
          <Text style={styles.statValue}>{completed.length}</Text>
          <Text style={Typography.caption}>{s.completed}</Text>
        </Card>
        <Card style={styles.stat}>
          <IconTile icon="cash-multiple" round size={40} color={Colors.warning} background={Colors.warningSoft} />
          <Amount value={statement.declaredMillimes / 1000} size={22} style={styles.statValue} />
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
        {reserved ? <ReservedItem offer={reserved} /> : null}
        {active.map((intervention) => (
          <MissionItem key={intervention.id} intervention={intervention} />
        ))}
        {!reserved && active.length === 0 ? <Text style={Typography.caption}>{s.noActivity}</Text> : null}
        <View style={styles.divider} />
        <Pressable accessibilityRole="button" onPress={() => router.push('/pro/statements')} style={styles.row}>
          <MaterialCommunityIcons name="chart-pie" size={22} color={Colors.textSecondary} />
          <Text style={[Typography.body, styles.grow]}>{s.commissionsDue}</Text>
          <Text style={Typography.bodyStrong}>{formatMillimes(statement.dueMillimes)}</Text>
          <Ionicons name={chevron} size={18} color={Colors.textSecondary} />
        </Pressable>
      </Card>
    </Screen>
  );
}

/** Proposition réservée : le client doit confirmer avant l'échéance (§6.5). */
function ReservedItem({ offer }: { offer: Offer }) {
  const { t } = useI18n();
  const s = t.proSpace.home;
  const remaining = useSecondsUntil(offer.reservedUntil);

  return (
    <View style={styles.activity}>
      <View style={styles.row}>
        <MaterialCommunityIcons name={serviceIcon(offer.request.serviceCode)} size={22} color={Colors.text} />
        <Text style={[Typography.bodyStrong, styles.grow]} numberOfLines={1}>
          {offer.request.serviceName} · {offer.request.areaName ?? ''}
        </Text>
        <Badge label={s.waitingClient} tone="warning" />
      </View>
      <View style={styles.row}>
        <Text style={[Typography.caption, styles.grow]}>
          {s.proposalReserved} · #{offer.request.reference}
        </Text>
        <Ionicons name="time-outline" size={18} color={Colors.text} />
        <Text style={styles.remaining}>{interpolate(s.remaining, { time: formatCountdown(remaining) })}</Text>
      </View>
    </View>
  );
}

function MissionItem({ intervention }: { intervention: Intervention }) {
  const { t } = useI18n();

  return (
    <Pressable
      accessibilityRole="button"
      onPress={() => router.push({ pathname: '/pro/mission', params: { id: intervention.id } })}
      style={styles.activity}>
      <View style={styles.row}>
        <MaterialCommunityIcons name={serviceIcon(intervention.request.serviceCode)} size={22} color={Colors.text} />
        <Text style={[Typography.bodyStrong, styles.grow]} numberOfLines={1}>
          {intervention.request.serviceName} · #{intervention.request.reference}
        </Text>
        <Badge label={t.interventionStatus[intervention.status]} tone={interventionTone(intervention.status)} />
      </View>
      <Text style={Typography.caption} numberOfLines={1}>
        {intervention.address?.addressLine ?? intervention.request.description}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
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
  activity: { gap: Spacing.sm, paddingVertical: Spacing.xs },
  remaining: { fontSize: 13, color: Colors.text, fontVariant: ['tabular-nums'] },
  divider: { height: 1, backgroundColor: Colors.border, marginVertical: Spacing.md },
});
