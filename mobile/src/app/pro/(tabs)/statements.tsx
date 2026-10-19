import Ionicons from '@expo/vector-icons/Ionicons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockStatements } from '@/mocks/pro-flow';
import { formatTND } from '@/utils/format';

/**
 * Relevé mensuel (§6.7) : règlements directs déclarés, commissions dues et versements à rapprocher.
 * Chaque ligne correspond à un mouvement traçable côté backend (§9.6).
 */
export default function ProStatements() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.statements;
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';

  return (
    <Screen
      footer={<Button label={s.declarePayment} />}>
      <View style={styles.header}>
        <Text style={Typography.title}>{s.title}</Text>
        <Pressable accessibilityRole="button" style={styles.period}>
          <Ionicons name="calendar-outline" size={16} color={Colors.text} />
          <Text style={styles.periodText}>{s.thisMonth}</Text>
          <Ionicons name="chevron-down" size={14} color={Colors.text} />
        </Pressable>
      </View>

      <Card>
        <View style={styles.cardHead}>
          <IconTile icon="cash-multiple" round size={44} />
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.declaredTitle}</Text>
          <Ionicons name="information-circle-outline" size={20} color={Colors.textSecondary} />
        </View>
        <Amount value={mockStatements.declaredPayments} size={32} style={styles.bigAmount} />
        <Text style={Typography.caption}>{s.declaredText}</Text>
      </Card>

      <Card tone="warning">
        <View style={styles.cardHead}>
          <IconTile icon="chart-pie" round size={44} color={Colors.warning} background="#FFE3A6" />
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.commissionsTitle}</Text>
          <Ionicons name="information-circle-outline" size={20} color={Colors.textSecondary} />
        </View>
        <Amount value={mockStatements.commissionsToPay} size={32} style={styles.bigAmount} />
        <Text style={Typography.caption}>{s.commissionsText}</Text>
      </Card>

      <Text style={Typography.heading}>{s.details}</Text>

      {mockStatements.lines.map((line) =>
        line.kind === 'intervention' ? (
          <Card key={line.reference}>
            <View style={styles.lineHead}>
              <IconTile icon="wrench-outline" size={36} color={Colors.text} background={Colors.surface} />
              <Text style={[Typography.bodyStrong, styles.grow]}>
                #{line.reference} · {t.services.plumbing}
              </Text>
              <Text style={Typography.small}>{line.date}</Text>
            </View>
            <View style={styles.lineBody}>
              <View style={styles.grow}>
                <View style={styles.amountRow}>
                  <Text style={Typography.caption}>{s.service} :</Text>
                  <Text style={Typography.bodyStrong}>{formatTND(line.amount)}</Text>
                </View>
                <View style={styles.amountRow}>
                  <Text style={Typography.caption}>{s.commission} :</Text>
                  <Text style={Typography.bodyStrong}>{formatTND(line.amount * mockStatements.commissionRate)}</Text>
                </View>
              </View>
              <Badge label={s.toPay} tone="warning" />
              <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
            </View>
          </Card>
        ) : (
          <Card key={line.reference}>
            <View style={styles.lineHead}>
              <IconTile icon="currency-usd" size={36} round color={Colors.primaryDark} />
              <View style={styles.grow}>
                <Text style={Typography.bodyStrong}>{s.paymentDeclared}</Text>
                <Amount value={line.amount} size={18} />
              </View>
              <Text style={Typography.small}>{line.date}</Text>
            </View>
            <View style={styles.lineBody}>
              <View style={styles.grow}>
                <Badge label={s.reconciliationPending} tone="warning" />
              </View>
              <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
            </View>
          </Card>
        ),
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
