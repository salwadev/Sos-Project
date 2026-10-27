import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';

import { interventionsApi } from '@/api/endpoints';
import type { Intervention } from '@/api/types';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { FakeMap } from '@/components/fake-map';
import { MapSheetLayout } from '@/components/map-sheet-layout';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Shadow, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { interpolate, type Translations } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatMillimes, formatTime, minutesUntil } from '@/utils/format';

const DISPUTE_MIN_LENGTH = 10;

function headline(intervention: Intervention, t: Translations): string {
  const name = intervention.professional.displayName;
  const titles: Record<Intervention['status'], string> = {
    confirmed: t.tracking.confirmedTitle,
    on_the_way: t.tracking.title,
    on_site: t.tracking.arrivedTitle,
    working: t.tracking.workingTitle,
    completion_declared: t.tracking.completionDeclaredTitle,
    completed: t.tracking.completedTitle,
    disputed: t.tracking.disputedTitle,
    cancelled: t.tracking.cancelledTitle,
  };
  return interpolate(titles[intervention.status], { name });
}

/**
 * Suivi de l'intervention confirmée (§5.6) : étapes déclarées par le professionnel, devis à accepter,
 * puis confirmation de fin ou signalement d'un problème (§5.7). Relu toutes les 5 s faute de push.
 */
export default function Tracking() {
  const { t } = useI18n();
  const { id = '' } = useLocalSearchParams<{ id?: string }>();
  const query = useApiQuery(`intervention:${id}`, () => interventionsApi.get(id), POLL_INTERVAL_MS);
  const action = useAction();
  const [disputeOpen, setDisputeOpen] = useState(false);
  const [reason, setReason] = useState('');
  const intervention = query.data;

  const confirmCompletion = async () => {
    const done = await action.run(() => interventionsApi.confirmCompletion(id), query.refresh);
    if (done) {
      router.replace({ pathname: '/client/review', params: { id } });
    }
  };

  const sendDispute = async () => {
    const disputed = await action.run(() => interventionsApi.dispute(id, reason.trim()), query.refresh);
    if (disputed) {
      setDisputeOpen(false);
      setReason('');
      query.refresh();
    }
  };

  if (!intervention) {
    return (
      <MapSheetLayout
        map={<FakeMap variant="tracking" style={StyleSheet.absoluteFill} />}
        header={<ScreenHeader overlay title={t.tracking.loadingTitle} onBack={() => router.dismissTo('/client')} />}>
        {query.loading ? <LoadingView /> : null}
        <ErrorNotice error={query.error} onRetry={query.refresh} />
      </MapSheetLayout>
    );
  }

  const status = intervention.status;
  const eta = status === 'on_the_way' ? minutesUntil(intervention.estimatedArrivalAt) : null;
  const canDispute = ['on_site', 'working', 'completion_declared', 'completed'].includes(status) && !intervention.review;
  const reached = (ts: string | null) => (ts ? 'done' : 'pending') as 'done' | 'pending';
  // Montant à remettre en espèces : annoncé dès la fin déclarée, puis celui du paiement enregistré à la clôture.
  const cashAmount =
    intervention.payment?.method === 'cash_direct'
      ? intervention.payment.amountMillimes
      : status === 'completion_declared' && intervention.acceptedQuote
        ? intervention.acceptedQuote.totalMillimes
        : null;

  const footer = (
    <>
      <ErrorNotice error={action.error} />
      {intervention.pendingQuote ? (
        <Button label={t.tracking.viewQuote} onPress={() => router.push({ pathname: '/client/quote', params: { id } })} />
      ) : null}
      {status === 'completion_declared' ? (
        <Button label={t.tracking.confirmCompletion} loading={action.pending} onPress={confirmCompletion} />
      ) : null}
      {status === 'completed' && !intervention.review ? (
        <Button label={t.tracking.leaveReview} onPress={() => router.push({ pathname: '/client/review', params: { id } })} />
      ) : null}
      {canDispute && !disputeOpen ? (
        <Button label={t.tracking.reportProblem} variant="ghost" onPress={() => setDisputeOpen(true)} />
      ) : null}
    </>
  );

  return (
    <MapSheetLayout
      map={<FakeMap variant="tracking" style={StyleSheet.absoluteFill} />}
      header={<ScreenHeader overlay title={headline(intervention, t)} onBack={() => router.dismissTo('/client')} />}
      floating={
        eta !== null ? (
          <View style={styles.eta}>
            <Text style={styles.etaText}>{interpolate(t.tracking.eta, { minutes: eta })}</Text>
          </View>
        ) : null
      }
      footer={footer}>
      <ErrorNotice error={query.error} onRetry={query.refresh} />

      <View style={styles.proRow}>
        <Avatar name={intervention.professional.displayName} size={52} verified />
        <View style={styles.grow}>
          <Text style={Typography.bodyStrong}>
            {intervention.professional.displayName}{' '}
            <Text style={styles.trade}>· {intervention.request.serviceName}</Text>
          </Text>
          <Text style={Typography.small}>
            {t.interventionStatus[status]} · #{intervention.request.reference}
          </Text>
          {intervention.address ? (
            <View style={styles.addressRow}>
              <Ionicons name="location" size={13} color={Colors.textSecondary} />
              <Text style={Typography.small}>{intervention.address.addressLine}</Text>
            </View>
          ) : null}
        </View>
      </View>

      <Stepper
        steps={[
          { key: 'confirmed', label: t.tracking.confirmed, time: '', state: 'done' },
          {
            key: 'onTheWay',
            label: t.tracking.onTheWay,
            time: formatTime(intervention.onTheWayAt),
            state: intervention.arrivedAt ? 'done' : intervention.onTheWayAt ? 'current' : 'pending',
          },
          { key: 'onSite', label: t.tracking.onSite, time: formatTime(intervention.arrivedAt), state: reached(intervention.arrivedAt) },
        ]}
      />
      {status === 'on_the_way' ? <Text style={[Typography.small, styles.center]}>{t.tracking.etaDisclaimer}</Text> : null}

      {intervention.pendingQuote ? (
        <Card tone="primarySoft">
          <View style={styles.quoteRow}>
            <Ionicons name="document-text-outline" size={24} color={Colors.primaryDark} />
            <Text style={[Typography.bodyStrong, styles.grow]}>
              {interpolate(t.tracking.quoteReady, { name: intervention.professional.displayName })}
            </Text>
          </View>
        </Card>
      ) : null}

      {intervention.acceptedQuote ? (
        <Card>
          <View style={styles.quoteRow}>
            <MaterialCommunityIcons name="file-document-check-outline" size={24} color={Colors.text} />
            <Text style={[Typography.body, styles.grow]}>
              {interpolate(t.tracking.acceptedQuote, { n: intervention.acceptedQuote.version })}
            </Text>
            <Text style={Typography.bodyStrong}>{formatMillimes(intervention.acceptedQuote.totalMillimes)}</Text>
          </View>
        </Card>
      ) : null}

      {cashAmount !== null ? (
        <Card tone={intervention.payment?.status === 'confirmed' ? undefined : 'warning'}>
          <View style={styles.quoteRow}>
            <MaterialCommunityIcons name="cash-multiple" size={24} color={Colors.text} />
            <Text style={[Typography.body, styles.grow]}>
              {intervention.payment?.status === 'confirmed' ? t.tracking.cashPaid : t.tracking.cashToPay}
            </Text>
            <Text style={Typography.bodyStrong}>{formatMillimes(cashAmount)}</Text>
          </View>
        </Card>
      ) : null}

      {status === 'completion_declared' ? <Text style={Typography.caption}>{t.tracking.confirmHint}</Text> : null}
      {status === 'disputed' ? <Text style={Typography.caption}>{t.tracking.disputed}</Text> : null}
      {intervention.review ? <Text style={Typography.caption}>{t.review.thanks}</Text> : null}

      {disputeOpen ? (
        <View style={styles.dispute}>
          <Text style={Typography.bodyStrong}>{t.tracking.reportProblem}</Text>
          <TextInput
            value={reason}
            onChangeText={setReason}
            placeholder={t.tracking.disputePlaceholder}
            placeholderTextColor={Colors.textMuted}
            multiline
            maxLength={1000}
            style={styles.disputeInput}
          />
          <Button
            label={t.tracking.disputeSubmit}
            variant="danger"
            disabled={reason.trim().length < DISPUTE_MIN_LENGTH}
            loading={action.pending}
            onPress={sendDispute}
          />
          <Button label={t.common.cancel} variant="ghost" onPress={() => setDisputeOpen(false)} />
        </View>
      ) : null}
    </MapSheetLayout>
  );
}

type Step = { key: string; label: string; time: string; state: 'done' | 'current' | 'pending' };

/** Progression horizontale Confirmée → En route → Sur place. */
function Stepper({ steps }: { steps: Step[] }) {
  return (
    <View style={styles.stepper}>
      {steps.map((step, index) => (
        <View key={step.key} style={styles.step}>
          <View style={styles.stepTrack}>
            <View style={[styles.stepLine, index === 0 && styles.hidden, step.state !== 'pending' && styles.stepLineOn]} />
            <StepDot state={step.state} />
            <View
              style={[
                styles.stepLine,
                index === steps.length - 1 && styles.hidden,
                steps[index + 1]?.state !== 'pending' && styles.stepLineOn,
              ]}
            />
          </View>
          <Text style={[styles.stepLabel, step.state === 'pending' && styles.stepLabelPending]}>{step.label}</Text>
          {step.time ? <Text style={Typography.small}>{step.time}</Text> : null}
        </View>
      ))}
    </View>
  );
}

function StepDot({ state }: { state: Step['state'] }) {
  if (state === 'done') {
    return (
      <View style={[styles.dot, styles.dotOn]}>
        <Ionicons name="checkmark" size={20} color={Colors.primaryText} />
      </View>
    );
  }
  if (state === 'current') {
    return (
      <View style={[styles.dot, styles.dotCurrent]}>
        <MaterialCommunityIcons name="truck-fast-outline" size={22} color={Colors.primaryText} />
      </View>
    );
  }
  return <View style={[styles.dot, styles.dotPending]} />;
}

const styles = StyleSheet.create({
  eta: {
    backgroundColor: Colors.background,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm + 2,
    ...Shadow,
  },
  etaText: { fontSize: 14, fontWeight: '600', color: Colors.text },
  proRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  trade: { fontWeight: '400', color: Colors.textSecondary, fontSize: 13 },
  addressRow: { flexDirection: 'row', alignItems: 'center', gap: 3, marginTop: 2 },
  stepper: { flexDirection: 'row', marginTop: Spacing.sm },
  step: { flex: 1, alignItems: 'center', gap: 4 },
  stepTrack: { flexDirection: 'row', alignItems: 'center', alignSelf: 'stretch' },
  stepLine: { flex: 1, height: 3, backgroundColor: Colors.border },
  stepLineOn: { backgroundColor: Colors.primary },
  hidden: { opacity: 0 },
  stepLabel: { fontSize: 14, fontWeight: '700', color: Colors.text, marginTop: 4 },
  stepLabelPending: { color: Colors.textSecondary, fontWeight: '600' },
  dot: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  dotOn: { backgroundColor: Colors.primary },
  dotCurrent: { backgroundColor: Colors.primary, borderWidth: 4, borderColor: Colors.primarySoft },
  dotPending: { width: 26, height: 26, borderWidth: 3, borderColor: Colors.border, backgroundColor: Colors.background },
  quoteRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  center: { textAlign: 'center' },
  dispute: { gap: Spacing.sm },
  disputeInput: {
    minHeight: 80,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    padding: Spacing.md,
    fontSize: 15,
    color: Colors.text,
    textAlignVertical: 'top',
  },
});
