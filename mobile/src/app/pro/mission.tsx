import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState, type ReactNode } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';

import { interventionsApi } from '@/api/endpoints';
import type { Intervention, ProfessionalStep, QuoteLine } from '@/api/types';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { QuoteForm } from '@/components/quote-form';
import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Timeline, type TimelineStep } from '@/components/ui/timeline';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { interpolate, type Translations } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatMillimes, formatTime } from '@/utils/format';
import { interventionTone } from '@/utils/pro';

const DEFAULT_ETA_MINUTES = '20';

function timeline(i: Intervention, t: Translations): TimelineStep[] {
  const s = t.proSpace.mission;
  const state = (done: boolean, current: boolean) => (done ? 'done' : current ? 'current' : 'pending') as TimelineStep['state'];
  const finished = i.completionDeclaredAt !== null;
  return [
    { key: 'confirmed', title: s.confirmed, description: s.confirmedHint, state: 'done' },
    {
      key: 'onTheWay',
      title: t.tracking.onTheWay,
      time: i.onTheWayAt ? formatTime(i.onTheWayAt) : undefined,
      state: state(i.onTheWayAt !== null, i.status === 'confirmed'),
    },
    {
      key: 'onSite',
      title: s.onSite,
      time: i.arrivedAt ? formatTime(i.arrivedAt) : undefined,
      description: s.onSiteHint,
      state: state(i.arrivedAt !== null, i.status === 'on_the_way'),
    },
    {
      key: 'quote',
      title: s.quoteAccepted,
      time: i.acceptedQuote?.decidedAt ? formatTime(i.acceptedQuote.decidedAt) : undefined,
      description: i.pendingQuote ? interpolate(s.quotePending, { n: i.pendingQuote.version }) : s.quoteAcceptedHint,
      state: state(i.acceptedQuote !== null, i.status === 'on_site'),
    },
    {
      key: 'working',
      title: s.working,
      description: i.workStartedAt ? interpolate(s.workingSince, { time: formatTime(i.workStartedAt) }) : undefined,
      state: state(i.workStartedAt !== null && finished, i.status === 'working'),
    },
    {
      key: 'done',
      title: s.done,
      description: i.completedAt ? s.clientConfirmed : finished ? s.doneDeclaredHint : s.doneHint,
      state: state(i.completedAt !== null, i.status === 'completion_declared'),
    },
  ];
}

/** Intervention active (§6.6) : le pro déclare chaque étape, propose le devis, puis la fin que le client confirme (§5.7). */
export default function ActiveMission() {
  const { t } = useI18n();
  const s = t.proSpace.mission;
  const { id = '' } = useLocalSearchParams<{ id?: string }>();
  const query = useApiQuery(`intervention:${id}`, () => interventionsApi.get(id), POLL_INTERVAL_MS);
  const action = useAction();
  const [eta, setEta] = useState(DEFAULT_ETA_MINUTES);
  const [quoteOpen, setQuoteOpen] = useState(false);
  const intervention = query.data;

  const advance = async (status: ProfessionalStep, minutes?: number) => {
    const updated = await action.run(() => interventionsApi.updateStatus(id, status, minutes), query.refresh);
    if (updated) {
      query.refresh();
    }
  };

  const sendQuote = async (lines: QuoteLine[], explanation: string) => {
    const updated = await action.run(() => interventionsApi.proposeQuote(id, lines, explanation), query.refresh);
    if (updated) {
      setQuoteOpen(false);
      query.refresh();
    }
  };

  const confirmCash = async () => {
    const updated = await action.run(() => interventionsApi.confirmCashPayment(id), query.refresh);
    if (updated) {
      query.refresh();
    }
  };

  if (!intervention) {
    return (
      <Screen header={<ScreenHeader title={s.loadingTitle} onBack={() => router.dismissTo('/pro')} />}>
        {query.loading ? <LoadingView /> : null}
        <ErrorNotice error={query.error} onRetry={query.refresh} />
      </Screen>
    );
  }

  const status = intervention.status;
  const canQuote = (status === 'on_site' || status === 'working') && intervention.pendingQuote === null;
  const showQuoteForm = canQuote && (quoteOpen || (status === 'on_site' && intervention.acceptedQuote === null));
  const etaMinutes = Number(eta);
  const payment = intervention.payment;
  const cashToCollect = payment?.method === 'cash_direct' && payment.status === 'declared';

  let primary: ReactNode = null;
  if (status === 'confirmed') {
    primary = (
      <>
        <View style={styles.etaRow}>
          <Text style={[Typography.body, styles.grow]}>{s.etaLabel}</Text>
          <TextInput value={eta} onChangeText={setEta} keyboardType="number-pad" maxLength={3} style={styles.etaInput} />
        </View>
        <Button
          label={s.leave}
          disabled={!Number.isInteger(etaMinutes) || etaMinutes < 1 || etaMinutes > 240}
          loading={action.pending}
          onPress={() => advance('on_the_way', etaMinutes)}
        />
      </>
    );
  } else if (status === 'on_the_way') {
    primary = <Button label={s.arrived} loading={action.pending} onPress={() => advance('on_site')} />;
  } else if (status === 'on_site' && intervention.acceptedQuote && !intervention.pendingQuote) {
    primary = <Button label={s.startWork} loading={action.pending} onPress={() => advance('working')} />;
  } else if (status === 'working') {
    primary = <Button label={s.declareDone} loading={action.pending} onPress={() => advance('completion_declared')} />;
  } else if (status === 'completion_declared') {
    primary = (
      <Card tone="primarySoft">
        <Text style={[Typography.bodyStrong, styles.center]}>{s.declared}</Text>
      </Card>
    );
  } else if (cashToCollect) {
    primary = <Button label={s.confirmCash} icon="cash-outline" loading={action.pending} onPress={confirmCash} />;
  }

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={
        <ScreenHeader
          title={interpolate(s.title, { ref: intervention.request.reference })}
          onBack={() => router.dismissTo('/pro')}
          right={<Badge label={t.interventionStatus[status]} tone={interventionTone(status)} />}
        />
      }
      footer={
        primary || action.error ? (
          <>
            <ErrorNotice error={action.error} />
            {primary}
            {status === 'working' ? <Text style={[Typography.caption, styles.center]}>{s.declareHint}</Text> : null}
            {cashToCollect ? <Text style={[Typography.caption, styles.center]}>{s.confirmCashHint}</Text> : null}
          </>
        ) : null
      }>
      <ErrorNotice error={query.error} onRetry={query.refresh} />

      <Card>
        <View style={styles.row}>
          <Ionicons name="location" size={26} color={Colors.primary} />
          <View style={styles.grow}>
            <Text style={Typography.caption}>{s.address}</Text>
            <Text style={Typography.bodyStrong}>{intervention.address?.addressLine ?? s.addressHidden}</Text>
            {intervention.address?.landmark ? <Text style={Typography.small}>{intervention.address.landmark}</Text> : null}
          </View>
        </View>
      </Card>

      <Card>
        <View style={styles.row}>
          <MaterialCommunityIcons name="wrench-outline" size={24} color={Colors.text} />
          <View style={styles.grow}>
            <Text style={Typography.caption}>{s.jobDetails}</Text>
            <Text style={Typography.bodyStrong}>{intervention.request.serviceName}</Text>
            <Text style={Typography.small}>{intervention.request.description}</Text>
          </View>
        </View>
      </Card>

      <Card>
        <Timeline steps={timeline(intervention, t)} />
      </Card>

      {intervention.acceptedQuote ? (
        <Card tone="primarySoft">
          <Text style={Typography.caption}>{interpolate(s.acceptedQuote, { n: intervention.acceptedQuote.version })}</Text>
          <Amount value={intervention.acceptedQuote.totalMillimes / 1000} size={24} />
        </Card>
      ) : null}
      {intervention.pendingQuote ? (
        <Card tone="warning">
          <Text style={Typography.bodyStrong}>{interpolate(s.quotePending, { n: intervention.pendingQuote.version })}</Text>
          <Text style={Typography.caption}>{formatMillimes(intervention.pendingQuote.totalMillimes)}</Text>
        </Card>
      ) : null}
      {intervention.quotes.some((q) => q.status === 'rejected') && !intervention.pendingQuote && !intervention.acceptedQuote ? (
        <Text style={Typography.caption}>{s.quoteRejected}</Text>
      ) : null}

      {showQuoteForm ? (
        <Card>
          <QuoteForm
            requiresExplanation={intervention.quotes.length > 0}
            travelFeeMillimes={intervention.travelFeeMillimes}
            pending={action.pending}
            onSubmit={sendQuote}
          />
        </Card>
      ) : canQuote ? (
        <Button label={s.newQuote} variant="outline" icon="document-text-outline" onPress={() => setQuoteOpen(true)} />
      ) : null}

      {payment ? (
        <Card>
          <View style={styles.row}>
            <MaterialCommunityIcons name="cash-multiple" size={22} color={Colors.textSecondary} />
            <View style={styles.grow}>
              <Text style={Typography.body}>{s.cashPayment}</Text>
              <Text style={Typography.bodyStrong}>{formatMillimes(payment.amountMillimes)}</Text>
            </View>
            <Badge
              label={t.proSpace.statements.cashStatus[payment.status]}
              tone={payment.status === 'declared' ? 'warning' : 'success'}
            />
          </View>
        </Card>
      ) : null}

      {intervention.commission ? (
        <Card>
          <View style={styles.row}>
            <MaterialCommunityIcons name="chart-pie" size={22} color={Colors.textSecondary} />
            <Text style={[Typography.body, styles.grow]}>{t.proSpace.statements.commission}</Text>
            <Text style={Typography.bodyStrong}>{formatMillimes(intervention.commission.amountMillimes)}</Text>
          </View>
        </Card>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { textAlign: 'center' },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1, gap: 2 },
  etaRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm },
  etaInput: {
    width: 70,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.sm,
    paddingHorizontal: Spacing.sm,
    paddingVertical: Spacing.xs + 2,
    fontSize: 16,
    color: Colors.text,
    textAlign: 'center',
  },
});
