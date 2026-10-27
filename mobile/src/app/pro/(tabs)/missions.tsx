import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { interventionsApi, proApi } from '@/api/endpoints';
import type { Intervention } from '@/api/types';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { serviceIcon } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { formatDate, formatMillimes } from '@/utils/format';
import { interventionTone, isActiveIntervention } from '@/utils/pro';

export default function ProMissions() {
  const { t } = useI18n();
  const s = t.proSpace.missions;
  const home = t.proSpace.home;
  const interventions = useApiQuery('interventions', interventionsApi.list, POLL_INTERVAL_MS);
  const offers = useApiQuery('pro-offers', proApi.offers, POLL_INTERVAL_MS);

  const reserved = offers.data?.filter((o) => o.status === 'reserved') ?? [];
  const current = interventions.data?.filter(isActiveIntervention) ?? [];
  const history = interventions.data?.filter((i) => !isActiveIntervention(i)) ?? [];

  return (
    <Screen>
      <Text style={Typography.title}>{s.title}</Text>
      <ErrorNotice error={interventions.error} onRetry={interventions.refresh} />
      {interventions.loading ? <LoadingView /> : null}

      <Text style={styles.section}>{s.current}</Text>
      {reserved.map((offer) => (
        <Card key={offer.id}>
          <View style={styles.row}>
            <IconTile icon={serviceIcon(offer.request.serviceCode)} color={Colors.navy} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>{offer.request.serviceName}</Text>
              <Text style={Typography.caption}>
                #{offer.request.reference} · {offer.request.areaName ?? ''}
              </Text>
            </View>
            <Badge label={home.waitingClient} tone="warning" />
          </View>
        </Card>
      ))}
      {current.map((i) => (
        <MissionRow key={i.id} intervention={i} />
      ))}
      {interventions.data && reserved.length === 0 && current.length === 0 ? (
        <Text style={Typography.caption}>{home.noActivity}</Text>
      ) : null}

      <Text style={styles.section}>{s.history}</Text>
      {interventions.data && history.length === 0 ? <Text style={Typography.caption}>{s.empty}</Text> : null}
      {history.map((i) => (
        <MissionRow key={i.id} intervention={i} />
      ))}
    </Screen>
  );
}

function MissionRow({ intervention }: { intervention: Intervention }) {
  const { t, rtl } = useI18n();
  const total = intervention.acceptedQuote?.totalMillimes;
  const meta = [
    `#${intervention.request.reference}`,
    formatDate(intervention.completedAt ?? intervention.onTheWayAt),
    total !== undefined ? formatMillimes(total) : null,
  ];

  return (
    <Card onPress={() => router.push({ pathname: '/pro/mission', params: { id: intervention.id } })}>
      <View style={styles.row}>
        <IconTile icon={serviceIcon(intervention.request.serviceCode)} color={Colors.text} background={Colors.surface} />
        <View style={styles.grow}>
          <Text style={Typography.bodyStrong}>{intervention.request.serviceName}</Text>
          <Text style={Typography.caption}>{meta.filter(Boolean).join(' · ')}</Text>
        </View>
        <Badge label={t.interventionStatus[intervention.status]} tone={interventionTone(intervention.status)} />
        <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  section: { ...Typography.caption, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1, gap: 2 },
});
