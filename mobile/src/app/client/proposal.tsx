import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { offersApi, requestsApi } from '@/api/endpoints';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { ProSummary } from '@/components/pro-summary';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useSecondsUntil } from '@/hooks/use-countdown';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatCountdown, formatMillimes } from '@/utils/format';

/** Proposition d'un professionnel qui a réservé la demande : le client confirme avant l'échéance (§5.5, §6.5). */
export default function Proposal() {
  const { t } = useI18n();
  const { requestId = '', offerId = '' } = useLocalSearchParams<{ requestId?: string; offerId?: string }>();
  const offers = useApiQuery(`offers:${requestId}`, () => requestsApi.offers(requestId), POLL_INTERVAL_MS);
  const offer = offers.data?.find((o) => o.id === offerId);
  const remaining = useSecondsUntil(offer?.reservedUntil ?? null);
  const [travelAccepted, setTravelAccepted] = useState(false);
  const action = useAction();

  const available = offer?.status === 'reserved' && remaining > 0;

  const confirm = async () => {
    const confirmed = await action.run(() => offersApi.accept(offerId), offers.refresh);
    if (confirmed?.interventionId) {
      router.replace({ pathname: '/client/tracking', params: { id: confirmed.interventionId } });
    }
  };

  const refuse = async () => {
    const refused = await action.run(() => offersApi.decline(offerId), offers.refresh);
    if (refused) {
      router.back();
    }
  };

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={t.proposal.title} />}
      footer={
        <>
          <ErrorNotice error={action.error} />
          {available ? (
            <>
              <Button label={t.proposal.confirm} disabled={!travelAccepted} loading={action.pending} onPress={confirm} />
              <Button label={t.proposal.refuse} variant="ghost" disabled={action.pending} onPress={refuse} />
            </>
          ) : (
            <Button label={t.proposal.backToSearch} variant="outline" onPress={() => router.back()} />
          )}
        </>
      }>
      {offers.loading ? <LoadingView /> : null}
      <ErrorNotice error={offers.error} onRetry={offers.refresh} />

      {offers.data && !offer ? <Text style={Typography.body}>{t.proposal.gone}</Text> : null}

      {offer ? (
        <>
          <View style={[styles.timer, !available && styles.timerExpired]}>
            <Ionicons name="time-outline" size={20} color={available ? Colors.warning : Colors.danger} />
            <Text style={[styles.timerText, !available && styles.timerTextExpired]}>
              {available ? interpolate(t.proposal.reservedFor, { time: formatCountdown(remaining) }) : t.proposal.expired}
            </Text>
          </View>

          {offer.professional ? (
            <Card>
              <ProSummary professional={offer.professional} subtitle={offer.request.serviceName} />
            </Card>
          ) : null}

          <Card>
            <View style={styles.feeRow}>
              <MaterialCommunityIcons name="truck-outline" size={24} color={Colors.text} />
              <Text style={[Typography.body, styles.grow]}>{t.proposal.travel}</Text>
              <Text style={Typography.bodyStrong}>{formatMillimes(offer.travelFeeMillimes)}</Text>
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

          {available ? (
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
          ) : null}
        </>
      ) : null}
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
    paddingHorizontal: Spacing.md,
  },
  timerExpired: { backgroundColor: Colors.dangerSoft, borderColor: Colors.dangerSoft },
  timerText: { color: Colors.warning, fontWeight: '700', fontSize: 15, fontVariant: ['tabular-nums'], textAlign: 'center' },
  timerTextExpired: { color: Colors.danger },
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
