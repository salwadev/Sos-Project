import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { newIdempotencyKey } from '@/api/client';
import { catalogApi, requestsApi } from '@/api/endpoints';
import type { Urgency } from '@/api/types';
import { EmergencyBanner } from '@/components/emergency-banner';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { ScreenHeader } from '@/components/ui/screen-header';
import { categoryStyles, isCategoryCode, serviceIcon, serviceName } from '@/constants/catalog';
import { DEFAULT_PLACE, PLACES } from '@/constants/places';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import type { CategoryCode } from '@/types/service-request';

export default function NewRequest() {
  const { t, language } = useI18n();
  const params = useLocalSearchParams<{ category?: string; service?: string }>();
  const category: CategoryCode = params.category && isCategoryCode(params.category) ? params.category : 'home';
  const isHealth = category === 'health';
  const services = useApiQuery('services', catalogApi.services);
  const inCategory = services.data?.filter((s) => s.category.code === category) ?? [];

  // Données de santé : on ne pré-remplit rien et on limite la saisie au nécessaire (§10.5).
  const [serviceCode, setServiceCode] = useState<string | undefined>(params.service);
  const [description, setDescription] = useState('');
  const [placeCode, setPlaceCode] = useState(DEFAULT_PLACE.code);
  const [addressLine, setAddressLine] = useState('');
  const [landmark, setLandmark] = useState('');
  const [urgency, setUrgency] = useState<Urgency>('asap');
  // Une clé par demande en cours de saisie : un nouvel essai après une coupure réseau ne crée pas de doublon (§8.6).
  const [idempotencyKey] = useState(newIdempotencyKey);
  const { run, pending, error } = useAction();
  const c = categoryStyles[category];

  const selected = inCategory.find((s) => s.code === serviceCode) ?? (inCategory.length === 1 ? inCategory[0] : undefined);
  const place = PLACES.find((p) => p.code === placeCode) ?? DEFAULT_PLACE;
  const placeName = language === 'ar' ? place.ar : place.fr;
  const valid = selected !== undefined && description.trim().length > 0 && addressLine.trim().length > 0;

  const submit = async () => {
    if (!selected) {
      return;
    }
    const created = await run(() =>
      requestsApi.create(
        {
          serviceCode: selected.code,
          urgency,
          description: description.trim(),
          addressLine: `${addressLine.trim()}, ${place.fr}`,
          landmark: landmark.trim() || null,
          latitude: place.latitude,
          longitude: place.longitude,
        },
        idempotencyKey,
      ),
    );
    if (created) {
      router.replace({ pathname: '/client/search', params: { id: created.id } });
    }
  };

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={t.newRequest.title} />}
      footer={
        <>
          <ErrorNotice error={error} />
          <Button label={t.newRequest.submit} disabled={!valid} loading={pending} onPress={submit} />
        </>
      }>
      {isHealth ? (
        <>
          <EmergencyBanner />
          <Text style={Typography.caption}>{t.newRequest.healthNotice}</Text>
        </>
      ) : null}

      <View style={styles.block}>
        <Text style={Typography.heading}>{t.categories[category]}</Text>
        <ErrorNotice error={services.error} onRetry={services.refresh} />
        {services.loading ? <LoadingView /> : null}
        <View style={styles.chips}>
          {inCategory.map((s) => {
            const active = s.code === selected?.code;
            return (
              <Pressable
                key={s.code}
                accessibilityRole="radio"
                accessibilityState={{ checked: active }}
                onPress={() => setServiceCode(s.code)}
                style={[styles.serviceChip, active && styles.chipActive]}>
                <MaterialCommunityIcons name={serviceIcon(s.code, category)} size={20} color={active ? c.color : Colors.text} />
                <Text style={[styles.chipText, active && styles.chipTextActive]}>{serviceName(s, language)}</Text>
              </Pressable>
            );
          })}
        </View>
      </View>

      <View style={styles.block}>
        <Text style={Typography.heading}>{t.newRequest.describe}</Text>
        <TextInput
          value={description}
          onChangeText={setDescription}
          placeholder={t.newRequest.describePlaceholder}
          placeholderTextColor={Colors.textMuted}
          multiline
          maxLength={2000}
          style={styles.textarea}
        />
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{t.newRequest.address}</Text>
        <View style={styles.chips}>
          {PLACES.map((p) => {
            const active = p.code === place.code;
            return (
              <Pressable
                key={p.code}
                accessibilityRole="radio"
                accessibilityState={{ checked: active }}
                onPress={() => setPlaceCode(p.code)}
                style={[styles.placeChip, active && styles.chipActive]}>
                <Text style={[styles.chipText, active && styles.chipTextActive]}>{language === 'ar' ? p.ar : p.fr}</Text>
              </Pressable>
            );
          })}
        </View>
        <View style={styles.inputRow}>
          <Ionicons name="location" size={22} color={Colors.primary} />
          <TextInput
            value={addressLine}
            onChangeText={setAddressLine}
            placeholder={t.newRequest.addressPlaceholder}
            placeholderTextColor={Colors.textMuted}
            maxLength={200}
            style={styles.input}
          />
          <Text style={Typography.caption}>{placeName}</Text>
        </View>
        <TextInput
          value={landmark}
          onChangeText={setLandmark}
          placeholder={t.newRequest.landmarkPlaceholder}
          placeholderTextColor={Colors.textMuted}
          maxLength={255}
          style={styles.field}
        />
        <Text style={Typography.small}>{t.newRequest.addressHint}</Text>
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
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  block: { gap: Spacing.sm },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  serviceChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1.5,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.sm + 4,
    paddingVertical: Spacing.sm,
  },
  placeChip: {
    borderWidth: 1.5,
    borderColor: Colors.border,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 4,
    paddingVertical: 6,
  },
  chipActive: { borderColor: Colors.primary, backgroundColor: Colors.primarySoft },
  chipText: { fontSize: 14, fontWeight: '600', color: Colors.text },
  chipTextActive: { color: Colors.primaryDark },
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
  inputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.md,
  },
  input: { flex: 1, fontSize: 16, paddingVertical: Spacing.md, color: Colors.text },
  field: {
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md - 2,
    fontSize: 15,
    color: Colors.text,
  },
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
