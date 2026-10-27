import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { interventionsApi } from '@/api/endpoints';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { serviceIcon } from '@/constants/catalog';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { formatDate, formatMillimes } from '@/utils/format';
import { monthlyStatement } from '@/utils/pro';

/**
 * Relevé mensuel (§6.7) : prestations terminées ce mois-ci, commission calculée par le serveur à la confirmation
 * du client (§9.4). La déclaration des versements viendra avec son endpoint.
 */
export default function ProStatements() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.statements;
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';
  const interventions = useApiQuery('interventions', interventionsApi.list);
  const statement = monthlyStatement(interventions.data);

  return (
    <Screen>
      <View style={styles.header}>
        <Text style={Typography.title}>{s.title}</Text>
        <View style={styles.period}>
          <Ionicons name="calendar-outline" size={16} color={Colors.text} />
          <Text style={styles.periodText}>{s.thisMonth}</Text>
        </View>
      </View>
      <ErrorNotice error={interventions.error} onRetry={interventions.refresh} />
      {interventions.loading ? <LoadingView /> : null}

      <Card>
        <View style={styles.cardHead}>
          <IconTile icon="cash-multiple" round size={44} />
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.declaredTitle}</Text>
        </View>
        <Amount value={statement.declaredMillimes / 1000} size={32} style={styles.bigAmount} />
        <Text style={Typography.caption}>{s.declaredText}</Text>
      </Card>

      <Card tone="warning">
        <View style={styles.cardHead}>
          <IconTile icon="chart-pie" round size={44} color={Colors.warning} background="#FFE3A6" />
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.commissionsTitle}</Text>
        </View>
        <Amount value={statement.dueMillimes / 1000} size={32} style={styles.bigAmount} />
        <Text style={Typography.caption}>{s.commissionsText}</Text>
      </Card>

      <Text style={Typography.heading}>{s.details}</Text>
      {interventions.data && statement.lines.length === 0 ? <Text style={Typography.caption}>{s.empty}</Text> : null}

      {statement.lines.map((line) =>
        line.commission ? (
          <Card key={line.id} onPress={() => router.push({ pathname: '/pro/mission', params: { id: line.id } })}>
            <View style={styles.lineHead}>
              <IconTile icon={serviceIcon(line.request.serviceCode)} size={36} color={Colors.text} background={Colors.surface} />
              <Text style={[Typography.bodyStrong, styles.grow]}>
                #{line.request.reference} · {line.request.serviceName}
              </Text>
              <Text style={Typography.small}>{formatDate(line.completedAt)}</Text>
            </View>
            <View style={styles.lineBody}>
              <View style={styles.grow}>
                <View style={styles.amountRow}>
                  <Text style={Typography.caption}>{s.service} :</Text>
                  <Text style={Typography.bodyStrong}>{formatMillimes(line.commission.baseMillimes)}</Text>
                </View>
                <View style={styles.amountRow}>
                  <Text style={Typography.caption}>{s.commission} :</Text>
                  <Text style={Typography.bodyStrong}>{formatMillimes(line.commission.amountMillimes)}</Text>
                </View>
                {line.payment?.method === 'cash_direct' ? (
                  <View style={styles.amountRow}>
                    <Text style={Typography.caption}>{s.cash} :</Text>
                    <Text style={Typography.bodyStrong}>{s.cashStatus[line.payment.status]}</Text>
                  </View>
                ) : null}
              </View>
              <Badge
                label={s.commissionStatus[line.commission.status]}
                tone={line.commission.status === 'due' ? 'warning' : 'success'}
              />
              <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
            </View>
          </Card>
        ) : null,
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  period: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.sm + 4,
    paddingVertical: Spacing.sm,
  },
  periodText: { fontSize: 13, fontWeight: '600', color: Colors.text },
  cardHead: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  bigAmount: { marginTop: Spacing.sm },
  lineHead: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 2 },
  lineBody: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, marginTop: Spacing.sm + 2 },
  amountRow: { flexDirection: 'row', gap: Spacing.sm, alignItems: 'baseline' },
});
