import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { EmergencyBanner } from '@/components/emergency-banner';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { categoryStyles, mockClient, mockRequest, type ServiceCode } from '@/mocks/client-flow';
import type { CategoryCode } from '@/types/service-request';

type Urgency = 'asap' | 'today';

export default function NewRequest() {
  const { t, rtl } = useI18n();
  const params = useLocalSearchParams<{ category?: CategoryCode; service?: ServiceCode }>();
  const category: CategoryCode = params.category ?? 'home';
  const service: ServiceCode | undefined = params.service ?? (category === 'home' ? 'plumbing' : undefined);
  const isHealth = category === 'health';

  // Données de santé : on ne pré-remplit rien et on limite la saisie au nécessaire (§10.5).
  const [description, setDescription] = useState(isHealth ? '' : mockRequest.description);
  const [urgency, setUrgency] = useState<Urgency>('asap');
  const [hasPhoto, setHasPhoto] = useState(!isHealth);
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';
  const c = categoryStyles[category];

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={t.newRequest.title} right={<Text style={styles.step}>{t.newRequest.step}</Text>} />}
      footer={
        <Button
          label={t.newRequest.submit}
          disabled={description.trim().length === 0}
          onPress={() => router.push('/client/search')}
        />
      }>
      {isHealth ? (
        <>
          <EmergencyBanner />
          <Text style={Typography.caption}>{t.newRequest.healthNotice}</Text>
        </>
      ) : null}

      <Card tone="soft">
        <View style={styles.row}>
          <IconTile icon={service === 'plumbing' ? 'faucet' : c.icon} color={c.color} background={c.background} />
          <Text style={[Typography.heading, styles.grow]}>{service ? t.services[service] : t.categories[category]}</Text>
          <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
        </View>
      </Card>

      <View style={styles.block}>
        <Text style={Typography.heading}>{t.newRequest.describe}</Text>
        <TextInput
          value={description}
          onChangeText={setDescription}
          placeholder={t.newRequest.describePlaceholder}
          placeholderTextColor={Colors.textMuted}
          multiline
          maxLength={500}
          style={styles.textarea}
        />
      </View>

      {!isHealth ? (
        <View style={styles.block}>
          <Text style={Typography.bodyStrong}>
            {t.newRequest.photos} <Text style={styles.optional}>· {t.newRequest.optional}</Text>
          </Text>
          <View style={styles.photos}>
            {hasPhoto ? (
              <View style={styles.photo}>
                <MaterialCommunityIcons name="pipe-leak" size={44} color="#C9D6DD" />
                <Pressable accessibilityRole="button" style={styles.photoRemove} onPress={() => setHasPhoto(false)}>
                  <Ionicons name="close" size={14} color={Colors.text} />
                </Pressable>
              </View>
            ) : null}
            <Pressable accessibilityRole="button" style={styles.addPhoto} onPress={() => setHasPhoto(true)}>
              <Ionicons name="add" size={28} color={Colors.textSecondary} />
              <Text style={styles.addPhotoText}>{t.newRequest.addPhoto}</Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{t.newRequest.address}</Text>
        <Card>
          <View style={styles.row}>
            <Ionicons name="location" size={26} color={Colors.primary} />
            <View style={styles.grow}>
              <Text style={Typography.bodyStrong}>{mockClient.district}</Text>
              <Text style={Typography.caption}>{t.newRequest.chooseAddress}</Text>
            </View>
            <Ionicons name={chevron} size={20} color={Colors.textSecondary} />
          </View>
        </Card>
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{t.newRequest.urgency}</Text>
        <View style={styles.row}>
          <UrgencyOption icon="time-outline" label={t.newRequest.asap} active={urgency === 'asap'} onPress={() => setUrgency('asap')} />
          <UrgencyOption
            icon="calendar-outline"
            label={t.newRequest.today}
            active={urgency === 'today'}
            onPress={() => setUrgency('today')}
          />
        </View>
      </View>

      <View style={styles.note}>
        <Ionicons name="information-circle-outline" size={20} color={Colors.textSecondary} />
        <Text style={[Typography.caption, styles.grow]}>{t.newRequest.priceNote}</Text>
      </View>
    </Screen>
  );
}

function UrgencyOption({
  icon,
  label,
  active,
  onPress,
}: {
  icon: 'time-outline' | 'calendar-outline';
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: active }}
      onPress={onPress}
      style={[styles.option, active && styles.optionActive]}>
      <Ionicons name={icon} size={22} color={active ? Colors.primaryDark : Colors.text} />
      <Text style={[styles.optionLabel, active && styles.optionLabelActive]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  step: { fontSize: 13, color: Colors.textSecondary },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  block: { gap: Spacing.sm },
  textarea: {
    minHeight: 96,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    padding: Spacing.md,
    fontSize: 16,
    color: Colors.text,
    textAlignVertical: 'top',
  },
  optional: { fontWeight: '400', color: Colors.textSecondary },
  photos: { flexDirection: 'row', gap: Spacing.sm + 4 },
  photo: {
    width: 104,
    height: 104,
    borderRadius: Radius.md,
    backgroundColor: '#3E4A52',
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoRemove: {
    position: 'absolute',
    top: 6,
    right: 6,
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: Colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addPhoto: {
    width: 104,
    height: 104,
    borderRadius: Radius.md,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderColor: Colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
  },
  addPhotoText: { fontSize: 12, color: Colors.textSecondary, textAlign: 'center' },
  option: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    paddingVertical: Spacing.md,
    borderRadius: Radius.md,
    borderWidth: 1.5,
    borderColor: Colors.border,
  },
  optionActive: { borderColor: Colors.primary, backgroundColor: Colors.primarySoft },
  optionLabel: { fontSize: 15, fontWeight: '600', color: Colors.text },
  optionLabelActive: { color: Colors.text, fontWeight: '700' },
  note: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    backgroundColor: Colors.surface,
    borderRadius: Radius.md,
    padding: Spacing.md,
  },
});
