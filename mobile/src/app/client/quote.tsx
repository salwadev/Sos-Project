import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { interventionsApi, quotesApi } from '@/api/endpoints';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { ProSummary } from '@/components/pro-summary';
import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { formatMillimes } from '@/utils/format';

/** Devis versionné : le client doit l'accepter explicitement avant tout travail (§9.2, §16.4). */
export default function Quote() {
  const { t } = useI18n();
  const { id = '' } = useLocalSearchParams<{ id?: string }>();
  const query = useApiQuery(`intervention:${id}`, () => interventionsApi.get(id), POLL_INTERVAL_MS);
  const action = useAction();
  const intervention = query.data;
  const quote = intervention?.pendingQuote ?? intervention?.acceptedQuote ?? null;
  const pending = quote !== null && quote.status === 'proposed';

  const decide = async (accept: boolean) => {
    if (!quote) {
      return;
    }
    const updated = await action.run(() => (accept ? quotesApi.accept(quote.id) : quotesApi.reject(quote.id)), query.refresh);
    if (updated) {
      router.back();
    }
  };

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={
        <ScreenHeader
          title={t.quote.title}
          right={quote ? <Badge label={interpolate(t.quote.version, { n: quote.version })} /> : null}
        />
      }
      footer={
        <>
          <ErrorNotice error={action.error} />
          {pending ? (
            <>
              <Button label={t.quote.accept} loading={action.pending} onPress={() => decide(true)} />
              <Button label={t.quote.requestChange} variant="outline" disabled={action.pending} onPress={() => decide(false)} />
            </>
          ) : (
            <Button label={t.quote.back} variant="outline" onPress={() => router.back()} />
          )}
        </>
      }>
      {query.loading ? <LoadingView /> : null}
      <ErrorNotice error={query.error} onRetry={query.refresh} />
      {intervention && !quote ? <Text style={Typography.body}>{t.quote.none}</Text> : null}

      {intervention && quote ? (
        <>
          <View style={styles.hero}>
            <IconTile icon="file-document-outline" size={58} round />
            <Text style={[Typography.title, styles.center]}>{pending ? t.quote.headline : t.quote.acceptedHeadline}</Text>
            <Text style={[Typography.caption, styles.center]}>{t.quote.subtitle}</Text>
          </View>

          <Card style={styles.quoteCard}>
            <View style={styles.inner}>
              <ProSummary
                professional={intervention.professional}
                title={intervention.request.serviceName}
                subtitle={intervention.professional.displayName}
                size={56}
              />
              <View style={styles.divider} />
              {quote.lines.map((line, index) => (
                <View key={`${index}-${line.label}`} style={styles.line}>
                  <Text style={[Typography.body, styles.grow]}>{line.label}</Text>
                  <Text style={Typography.bodyStrong}>{formatMillimes(line.amountMillimes)}</Text>
                </View>
              ))}
              {quote.explanation ? <Text style={Typography.caption}>{quote.explanation}</Text> : null}
            </View>
            <View style={styles.total}>
              <Text style={styles.totalLabel}>{t.quote.total}</Text>
              <Text style={styles.totalAmount}>{formatMillimes(quote.totalMillimes)}</Text>
            </View>
          </Card>

          <Card tone="primarySoft">
            <View style={styles.lockRow}>
              <Ionicons name="lock-closed" size={22} color={Colors.primaryDark} />
              <View style={styles.grow}>
                <Text style={Typography.bodyStrong}>{t.quote.lockTitle}</Text>
                <Text style={Typography.caption}>{t.quote.lockText}</Text>
              </View>
            </View>
          </Card>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { alignItems: 'center', gap: Spacing.sm },
  center: { textAlign: 'center' },
  quoteCard: { padding: 0, overflow: 'hidden' },
  inner: { padding: Spacing.md, gap: Spacing.sm + 4 },
  divider: { height: 1, backgroundColor: Colors.border },
  line: { flexDirection: 'row', justifyContent: 'space-between', gap: Spacing.sm },
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
