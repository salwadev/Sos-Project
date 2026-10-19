import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { Spacing, Typography, Colors } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockIncoming, mockMission, mockPastMissions, useProDemo } from '@/mocks/pro-flow';
import { formatTND } from '@/utils/format';

export default function ProMissions() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.missions;
  const home = t.proSpace.home;
  const { stage } = useProDemo();
  const hasCurrent = stage !== 'incoming';
  const badge =
    stage === 'proposed'
      ? { label: home.waitingClient, tone: 'warning' as const }
      : stage === 'inProgress'
        ? { label: home.inProgress, tone: 'success' as const }
        : { label: home.completionDeclared, tone: 'neutral' as const };

  return (
    <Screen>
      <Text style={Typography.title}>{s.title}</Text>

      <Text style={styles.section}>{s.current}</Text>
      {hasCurrent ? (
        <Card onPress={() => router.push('/pro/mission')}>
          <View style={styles.row}>
            <IconTile icon="pipe-leak" color={Colors.navy} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>{mockIncoming.title}</Text>
              <Text style={Typography.caption}>
                #{mockMission.reference} · {mockMission.client} · {mockIncoming.district}
              </Text>
            </View>
            <Badge label={badge.label} tone={badge.tone} />
            <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
          </View>
        </Card>
      ) : (
        <Text style={Typography.caption}>{home.noActivity}</Text>
      )}

      <Text style={styles.section}>{s.history}</Text>
      {mockPastMissions.map((m) => (
        <Card key={m.reference}>
          <View style={styles.row}>
            <IconTile icon="wrench-outline" color={Colors.text} background={Colors.surface} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>
                {t.services[m.trade]} · {m.district}
              </Text>
              <Text style={Typography.caption}>
                #{m.reference} · {m.date} · {formatTND(m.amount)}
              </Text>
            </View>
            <Badge label={s.completed} tone="success" />
          </View>
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  section: { ...Typography.caption, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1, gap: 2 },
});
