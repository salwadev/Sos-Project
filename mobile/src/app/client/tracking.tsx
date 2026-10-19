import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { useEffect, useState, type ComponentProps } from 'react';
import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';

import { FakeMap } from '@/components/fake-map';
import { MapSheetLayout } from '@/components/map-sheet-layout';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Shadow, Spacing, Typography } from '@/constants/theme';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockClient, mockPro } from '@/mocks/client-flow';

/** Délai simulé avant l'arrivée du professionnel et l'envoi du devis. */
const FAKE_ARRIVAL_DELAY_MS = 5000;

export default function Tracking() {
  const { t } = useI18n();
  const [arrived, setArrived] = useState(false);
  const firstName = mockPro.name.split(' ')[0];

  useEffect(() => {
    const id = setTimeout(() => setArrived(true), FAKE_ARRIVAL_DELAY_MS);
    return () => clearTimeout(id);
  }, []);

  return (
    <MapSheetLayout
      map={<FakeMap variant="tracking" style={StyleSheet.absoluteFill} />}
      header={
        <ScreenHeader overlay title={interpolate(arrived ? t.tracking.arrivedTitle : t.tracking.title, { name: firstName })} />
      }
      floating={
        !arrived ? (
          <View style={styles.eta}>
            <Text style={styles.etaText}>{interpolate(t.tracking.eta, { minutes: mockPro.etaMinutes })}</Text>
          </View>
        ) : null
      }
      footer={arrived ? <Button label={t.tracking.viewQuote} onPress={() => router.push('/client/quote')} /> : null}>
      <View style={styles.proRow}>
        <Avatar name={mockPro.name} size={52} verified />
        <View style={styles.grow}>
          <Text style={Typography.bodyStrong}>
            {mockPro.name} <Text style={styles.trade}>· {t.services.plumbing}</Text>
          </Text>
          <Text style={Typography.small}>{arrived ? t.tracking.onSite : t.tracking.onTheWayTo}</Text>
          <View style={styles.addressRow}>
            <Ionicons name="location" size={13} color={Colors.textSecondary} />
            <Text style={Typography.small}>{mockClient.address}</Text>
          </View>
        </View>
        <RoundAction icon="call" label={t.tracking.call} onPress={() => Linking.openURL('tel:+21600000000')} />
        <RoundAction icon="chatbox-ellipses-outline" label={t.tracking.message} />
      </View>

      <Stepper
        steps={[
          { key: 'confirmed', label: t.tracking.confirmed, time: mockPro.confirmedAt, state: 'done' },
          { key: 'onTheWay', label: t.tracking.onTheWay, time: mockPro.onTheWayAt, state: arrived ? 'done' : 'current' },
          { key: 'onSite', label: t.tracking.onSite, time: arrived ? '09:36' : '--:--', state: arrived ? 'done' : 'pending' },
        ]}
      />

      {arrived ? (
        <Card tone="primarySoft">
          <View style={styles.quoteRow}>
            <Ionicons name="document-text-outline" size={24} color={Colors.primaryDark} />
            <Text style={[Typography.bodyStrong, styles.grow]}>{interpolate(t.tracking.quoteReady, { name: firstName })}</Text>
          </View>
        </Card>
      ) : (
        <Text style={[Typography.small, styles.center]}>{t.tracking.etaDisclaimer}</Text>
      )}
    </MapSheetLayout>
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
      <View style={styles.actionCircle}>
        <Ionicons name={icon} size={22} color={Colors.primaryDark} />
      </View>
      <Text style={styles.actionLabel}>{label}</Text>
    </Pressable>
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
          <Text style={Typography.small}>{step.time}</Text>
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
  action: { alignItems: 'center', gap: 4 },
  actionCircle: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: Colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionLabel: { fontSize: 11, fontWeight: '600', color: Colors.text },
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
});
