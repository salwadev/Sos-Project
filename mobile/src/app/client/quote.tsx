import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { ProSummary } from '@/components/pro-summary';
import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockPro, mockQuote } from '@/mocks/client-flow';
import { formatTND } from '@/utils/format';

/** Devis versionné : le client doit l'accepter explicitement avant tout travail (§9.2, §16.4). */
export default function Quote() {
  const { t } = useI18n();

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={
        <ScreenHeader
          title={t.quote.title}
          right={<Badge label={interpolate(t.quote.version, { n: mockQuote.version })} />}
        />
      }
      footer={
        <>
          <Button label={t.quote.accept} onPress={() => router.replace('/client/review')} />
          <Button label={t.quote.requestChange} variant="outline" />
        </>
      }>
      <View style={styles.hero}>
        <IconTile icon="file-document-outline" size={58} round />
        <Text style={[Typography.title, styles.center]}>{t.quote.headline}</Text>
        <Text style={[Typography.caption, styles.center]}>{t.quote.subtitle}</Text>
      </View>

      <Card style={styles.quoteCard}>
        <View style={styles.inner}>
          <ProSummary title={t.quote.job} subtitle={`${mockPro.name} · ${t.proposal.verifiedPlumber}`} size={56} />
          <View style={styles.divider} />
          {mockQuote.lines.map((line) => (
            <View key={line.key} style={styles.line}>
              <Text style={Typography.body}>{t.quote.lines[line.key]}</Text>
              <Text style={Typography.bodyStrong}>{formatTND(line.amount)}</Text>
            </View>
          ))}
        </View>
        <View style={styles.total}>
          <Text style={styles.totalLabel}>{t.quote.total}</Text>
          <Text style={styles.totalAmount}>{formatTND(mockQuote.total)}</Text>
        </View>
      </Card>
      <Text style={[Typography.small, styles.center]}>{t.quote.travelIncluded}</Text>

      <Card tone="primarySoft">
        <View style={styles.lockRow}>
          <Ionicons name="lock-closed" size={22} color={Colors.primaryDark} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{t.quote.lockTitle}</Text>
            <Text style={Typography.caption}>{t.quote.lockText}</Text>
          </View>
        </View>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { alignItems: 'center', gap: Spacing.sm },
  center: { textAlign: 'center' },
  quoteCard: { padding: 0, overflow: 'hidden' },
  inner: { padding: Spacing.md, gap: Spacing.sm + 4 },
  divider: { height: 1, backgroundColor: Colors.border },
  line: { flexDirection: 'row', justifyContent: 'space-between' },
  total: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: Colors.navy,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md,
    borderBottomLeftRadius: Radius.lg,
    borderBottomRightRadius: Radius.lg,
  },
  totalLabel: { color: Colors.primaryText, fontSize: 20, fontWeight: '700' },
  totalAmount: { color: Colors.primaryText, fontSize: 20, fontWeight: '800' },
  lockRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
});
