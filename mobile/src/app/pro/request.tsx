import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { offersApi, proApi } from '@/api/endpoints';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { FakeMap } from '@/components/fake-map';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { serviceIcon } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useSecondsUntil } from '@/hooks/use-countdown';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatCountdown, formatMillimes, parseTNDToMillimes } from '@/utils/format';

/**
 * Sollicitation reçue (§6.4) : zone approximative seulement, l'adresse exacte n'est révélée qu'après confirmation.
 * « Proposer et réserver » réserve temporairement la demande ; côté API, l'acceptation est atomique (§6.5) :
 * si un autre professionnel a été plus rapide, le 409 est affiché tel quel.
 */
export default function IncomingRequest() {
  const { t } = useI18n();
  const s = t.proSpace.request;
  const { id = '' } = useLocalSearchParams<{ id?: string }>();
  const offers = useApiQuery('pro-offers', proApi.offers, POLL_INTERVAL_MS);
  const offer = offers.data?.find((o) => o.id === id);
  const remaining = useSecondsUntil(offer?.respondBefore ?? null);
  const [fee, setFee] = useState<number | null>(null);
  const [editingFee, setEditingFee] = useState(false);
  const [feeInput, setFeeInput] = useState('');
  const action = useAction();

  const travelFee = fee ?? offer?.travelFeeMillimes ?? 0;
  const open = offer?.status === 'sent' && remaining > 0;

  const startEditing = () => {
    setFeeInput(String(travelFee / 1000));
    setEditingFee(true);
  };

  const commitFee = () => {
    const value = parseTNDToMillimes(feeInput);
    if (value !== null) {
      setFee(value);
    }
    setEditingFee(false);
  };

  const propose = async () => {
    const reserved = await action.run(() => offersApi.accept(id, travelFee), offers.refresh);
    if (reserved) {
      router.dismissTo('/pro');
    }
  };

  const decline = async () => {
    const declined = await action.run(() => offersApi.decline(id), offers.refresh);
    if (declined) {
      router.dismissTo('/pro');
    }
  };

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={s.title} onBack={() => router.dismissTo('/pro')} />}
      footer={
        <>
          <ErrorNotice error={action.error} />
          {open ? (
            <>
              <Button label={s.propose} disabled={editingFee} loading={action.pending} onPress={propose} />
              <Text style={[Typography.caption, styles.center]}>{s.proposeHint}</Text>
              <Button label={s.decline} variant="outline" disabled={action.pending} onPress={decline} />
            </>
          ) : (
            <Button label={s.back} variant="outline" onPress={() => router.dismissTo('/pro')} />
          )}
        </>
      }>
      {offers.loading ? <LoadingView /> : null}
      <ErrorNotice error={offers.error} onRetry={offers.refresh} />
      {offers.data && !offer ? <Text style={Typography.body}>{s.gone}</Text> : null}

      {offer ? (
        <>
          <View style={[styles.timer, !open && styles.timerExpired]}>
            <Ionicons name="time-outline" size={22} color={open ? Colors.text : Colors.danger} />
            <Text style={[styles.timerText, !open && styles.timerTextExpired]}>
              {open ? interpolate(s.respondIn, { time: formatCountdown(remaining) }) : s.expired}
            </Text>
          </View>

          <View style={styles.hero}>
            <View style={styles.illustration}>
              <MaterialCommunityIcons name={serviceIcon(offer.request.serviceCode, offer.request.categoryCode)} size={64} color={Colors.primary} />
            </View>
            <View style={styles.heroText}>
              <Text style={Typography.title}>{offer.request.serviceName}</Text>
              <View style={styles.zonePill}>
                <Ionicons name="location" size={14} color={Colors.text} />
                <Text style={styles.zoneText}>
                  {offer.request.areaName ?? s.nearby}
                  {offer.request.approximateDistanceKm !== null
                    ? ` · ${interpolate(s.distance, { km: String(offer.request.approximateDistanceKm).replace('.', ',') })}`
                    : ''}
                </Text>
              </View>
              {offer.request.urgency === 'asap' ? <Text style={styles.urgent}>{t.newRequest.asap}</Text> : null}
            </View>
          </View>

          <View>
            <FakeMap variant="search" style={styles.map} />
            <Text style={[Typography.small, styles.mapNote]}>{s.approximate}</Text>
          </View>

          <Card>
            <View style={styles.labelRow}>
              <MaterialCommunityIcons name="text-box-outline" size={18} color={Colors.textSecondary} />
              <Text style={Typography.caption}>{s.need}</Text>
            </View>
            <Text style={[Typography.body, styles.description]}>{offer.request.description}</Text>
          </Card>

          <Card>
            <View style={styles.row}>
              <MaterialCommunityIcons name="truck-outline" size={22} color={Colors.text} />
              <Text style={[styles.feeLabel, styles.grow]}>{s.travelFee}</Text>
              {editingFee ? (
                <TextInput
                  value={feeInput}
                  onChangeText={setFeeInput}
                  onBlur={commitFee}
                  onSubmitEditing={commitFee}
                  keyboardType="decimal-pad"
                  autoFocus
                  style={styles.feeInput}
                  accessibilityLabel={s.editFee}
                />
              ) : (
                <Text style={Typography.bodyStrong}>{formatMillimes(travelFee)}</Text>
              )}
              {open ? (
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={s.editFee}
                  hitSlop={8}
                  onPress={() => (editingFee ? commitFee() : startEditing())}>
                  <Ionicons name={editingFee ? 'checkmark' : 'pencil'} size={18} color={Colors.text} />
                </Pressable>
              ) : null}
            </View>
            <Text style={[Typography.small, styles.feeHint]}>{s.feeHint}</Text>
          </Card>

          <View style={styles.note}>
            <MaterialCommunityIcons name="file-document-outline" size={20} color={Colors.textSecondary} />
            <Text style={[Typography.caption, styles.grow]}>{s.worksAfterDiagnosis}</Text>
          </View>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { textAlign: 'center' },
  timer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    backgroundColor: '#FFE9A8',
    borderWidth: 1,
    borderColor: Colors.warningBorder,
    borderRadius: Radius.md,
    paddingVertical: Spacing.sm + 4,
    paddingHorizontal: Spacing.md,
  },
  timerExpired: { backgroundColor: Colors.dangerSoft, borderColor: Colors.dangerSoft },
  timerText: { color: Colors.text, fontWeight: '700', fontSize: 16, fontVariant: ['tabular-nums'], textAlign: 'center' },
  timerTextExpired: { color: Colors.danger, fontSize: 14 },
  hero: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  illustration: {
    width: 110,
    height: 100,
    borderRadius: Radius.lg,
    backgroundColor: Colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroText: { flex: 1, gap: Spacing.sm },
  zonePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    alignSelf: 'flex-start',
    backgroundColor: Colors.surface,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 4,
  },
  zoneText: { fontSize: 13, color: Colors.text },
  urgent: { fontSize: 13, fontWeight: '700', color: Colors.danger },
  map: { height: 150, borderRadius: Radius.lg },
  mapNote: { marginTop: Spacing.xs },
  labelRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  description: { marginTop: Spacing.xs },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 2 },
  grow: { flex: 1 },
  feeLabel: { fontSize: 15, fontWeight: '600', color: Colors.text },
  feeHint: { marginTop: Spacing.xs },
  feeInput: {
    minWidth: 80,
    borderWidth: 1,
    borderColor: Colors.primary,
    borderRadius: Radius.sm,
    paddingHorizontal: Spacing.sm,
    paddingVertical: 4,
    fontSize: 15,
    color: Colors.text,
    textAlign: 'right',
  },
  note: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    backgroundColor: Colors.surface,
    borderRadius: Radius.md,
    padding: Spacing.md,
  },
});
