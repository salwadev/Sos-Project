import { router, useLocalSearchParams } from 'expo-router';
import { useEffect } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { catalogApi, requestsApi } from '@/api/endpoints';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { FakeMap } from '@/components/fake-map';
import { MapSheetLayout } from '@/components/map-sheet-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Timeline, type TimelineStep } from '@/components/ui/timeline';
import { findService, serviceIcon, serviceName } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { formatTime } from '@/utils/format';

/**
 * Recherche en cours (§5.4) : la demande et ses offres sont relues toutes les 5 s en attendant les notifications push.
 * Dès qu'un professionnel réserve la demande, sa proposition peut être ouverte ; une fois confirmée, on passe au suivi.
 */
export default function SearchInProgress() {
  const { t, language } = useI18n();
  const { id = '' } = useLocalSearchParams<{ id?: string }>();
  const request = useApiQuery(`request:${id}`, () => requestsApi.get(id), POLL_INTERVAL_MS);
  const offers = useApiQuery(`offers:${id}`, () => requestsApi.offers(id), POLL_INTERVAL_MS);
  const services = useApiQuery('services', catalogApi.services);
  const cancel = useAction();

  const status = request.data?.status;
  const reserved = offers.data?.find((o) => o.status === 'reserved');
  const confirmed = offers.data?.find((o) => o.status === 'confirmed' && o.interventionId);
  const service = request.data ? findService(services.data, request.data.service) : undefined;
  const closed = status === 'CANCELLED' || status === 'EXPIRED';

  // Professionnel confirmé (depuis cet appareil ou un autre) : l'intervention se suit sur l'écran dédié.
  useEffect(() => {
    if (confirmed?.interventionId) {
      router.replace({ pathname: '/client/tracking', params: { id: confirmed.interventionId } });
    }
  }, [confirmed?.interventionId]);

  const cancelSearch = async () => {
    const cancelled = await cancel.run(() => requestsApi.cancel(id), request.refresh);
    if (cancelled) {
      router.dismissTo('/client');
    }
  };

  const steps: TimelineStep[] = [
    { key: 'sent', title: t.search.sent, time: formatTime(request.data?.submittedAt), state: 'done' },
    {
      key: 'contacted',
      title: t.search.contacted,
      description: t.search.contactedHint,
      state: reserved ? 'done' : status === 'SUBMITTED' ? 'pending' : 'current',
    },
    {
      key: 'proposal',
      title: t.search.proposal,
      description: reserved ? t.search.proposalReady : t.search.proposalHint,
      state: reserved ? 'current' : 'pending',
    },
  ];

  return (
    <MapSheetLayout
      map={<FakeMap variant="search" style={StyleSheet.absoluteFill} />}
      header={<ScreenHeader overlay title={t.search.title} onBack={() => router.dismissTo('/client')} />}
      footer={
        closed ? (
          <Button label={t.search.newRequest} onPress={() => router.replace('/client/new-request')} />
        ) : (
          <>
            <ErrorNotice error={cancel.error} />
            {reserved ? (
              <Button
                label={t.search.viewProposal}
                onPress={() => router.push({ pathname: '/client/proposal', params: { requestId: id, offerId: reserved.id } })}
              />
            ) : null}
            <Button label={t.search.cancel} variant="outline" loading={cancel.pending} onPress={cancelSearch} />
          </>
        )
      }>
      {request.loading ? <LoadingView /> : null}
      <ErrorNotice error={request.error} onRetry={request.refresh} />

      {closed ? (
        <View style={styles.titles}>
          <Text style={Typography.title}>{status === 'EXPIRED' ? t.common.noProAvailable : t.search.cancelled}</Text>
          <Text style={Typography.caption}>{status === 'EXPIRED' ? t.search.expiredHint : request.data?.cancelReason}</Text>
        </View>
      ) : (
        <View style={styles.titles}>
          <Text style={Typography.title}>{t.search.headline}</Text>
          <Text style={Typography.caption}>{t.search.subtitle}</Text>
        </View>
      )}

      {request.data ? (
        <Card tone="soft">
          <View style={styles.row}>
            <IconTile icon={serviceIcon(service?.code)} color={Colors.navy} background={Colors.background} />
            <View style={styles.grow}>
              <Text style={Typography.heading}>{service ? serviceName(service, language) : request.data.reference}</Text>
              <Text style={Typography.caption} numberOfLines={1}>
                #{request.data.reference} · {request.data.addressLine}
              </Text>
            </View>
          </View>
        </Card>
      ) : null}

      {!closed ? <Timeline steps={steps} /> : null}
    </MapSheetLayout>
  );
}

const styles = StyleSheet.create({
  titles: { gap: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1, gap: 2 },
});
