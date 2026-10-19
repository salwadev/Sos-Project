import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import type { ComponentProps } from 'react';
import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';

import { Screen } from '@/components/screen';
import { Amount } from '@/components/ui/amount';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Timeline, type TimelineStep } from '@/components/ui/timeline';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockIncoming, mockMission, proDemo, useProDemo } from '@/mocks/pro-flow';

/** Intervention active (§6.6) : le pro déclare la fin, le client la confirme ensuite (§5.7). */
export default function ActiveMission() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.mission;
  const { stage } = useProDemo();
  const declared = stage === 'completionDeclared';
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';

  const steps: TimelineStep[] = [
    { key: 'onSite', title: s.onSite, description: `${mockMission.onSiteAt} · ${s.onSiteHint}`, state: 'done' },
    {
      key: 'quote',
      title: s.quoteAccepted,
      description: `${mockMission.quoteAcceptedAt} · ${s.quoteAcceptedHint}`,
      state: 'done',
    },
    {
      key: 'working',
      title: s.working,
      description: `${interpolate(s.workingSince, { time: mockMission.workStartedAt })}\n${mockIncoming.description}`,
      state: declared ? 'done' : 'current',
    },
    { key: 'done', title: s.done, description: declared ? s.doneDeclaredHint : s.doneHint, state: declared ? 'current' : 'pending' },
  ];

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={
        <ScreenHeader
          title={interpolate(s.title, { ref: mockMission.reference })}
          onBack={() => router.dismissTo('/pro')}
          right={
            <View style={[styles.statusPill, declared && styles.statusPillDeclared]}>
              <View style={[styles.statusDot, declared && styles.statusDotDeclared]} />
              <Text style={[styles.statusText, declared && styles.statusTextDeclared]}>
                {declared ? t.proSpace.home.completionDeclared : s.inProgress}
              </Text>
            </View>
          }
        />
      }
      footer={
        <>
          {declared ? (
            <Card tone="primarySoft">
              <Text style={[Typography.bodyStrong, styles.center]}>{s.declared}</Text>
            </Card>
          ) : (
            <Button label={s.declareDone} onPress={() => proDemo.setStage('completionDeclared')} />
          )}
          <Text style={[Typography.caption, styles.center]}>{s.declareHint}</Text>
          <Pressable accessibilityRole="link" style={styles.help}>
            <Ionicons name="help-circle-outline" size={18} color={Colors.primaryDark} />
            <Text style={styles.helpText}>{s.help}</Text>
          </Pressable>
        </>
      }>
      <Card>
        <View style={styles.row}>
          <Avatar name={mockMission.client} size={52} color="#3E6A7E" />
          <View style={styles.grow}>
            <Text style={Typography.heading}>{mockMission.client}</Text>
            <Text style={Typography.caption}>{mockMission.address}</Text>
          </View>
          <RoundAction icon="call" label={s.call} onPress={() => Linking.openURL('tel:+21600000000')} />
          <RoundAction icon="chatbox-ellipses-outline" label={s.message} />
        </View>
      </Card>

      <Card>
        <Timeline steps={steps} />
      </Card>

      <Card tone="primarySoft">
        <View style={styles.row}>
          <IconTile icon="file-document-outline" background={Colors.background} />
          <View style={styles.grow}>
            <Text style={Typography.caption}>{s.quoteAccepted}</Text>
            <Amount value={mockMission.quoteTotal} size={24} />
          </View>
          <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
        </View>
      </Card>

      <Card>
        <View style={styles.row}>
          <MaterialCommunityIcons name="wrench-outline" size={24} color={Colors.text} />
          <View style={styles.grow}>
            <Text style={Typography.caption}>{s.jobDetails}</Text>
            <Text style={Typography.bodyStrong}>{mockMission.job}</Text>
            <Text style={Typography.small}>{mockIncoming.title}</Text>
          </View>
          <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
        </View>
      </Card>
    </Screen>
  );
}

function RoundAction({
  icon,
  label,
  onPress,
}: {
  icon: ComponentProps<typeof Ionicons>['name'];
  label: string;
  onPress?: () => void;
}) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} style={styles.action}>
      <Ionicons name={icon} size={22} color={Colors.primaryDark} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  center: { textAlign: 'center' },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#D6F3E4',
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 5,
  },
  statusDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: Colors.success },
  statusText: { fontSize: 12, fontWeight: '700', color: '#0F7A57' },
  statusPillDeclared: { backgroundColor: Colors.surface },
  statusDotDeclared: { backgroundColor: Colors.textSecondary },
  statusTextDeclared: { color: Colors.textSecondary },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1, gap: 2 },
  action: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: Colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  help: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, paddingVertical: Spacing.xs },
  helpText: { color: Colors.primaryDark, fontSize: 13, fontWeight: '600' },
});
