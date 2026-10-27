import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { catalogApi, proApi } from '@/api/endpoints';
import type { LegalStatus } from '@/api/types';
import { useAuth } from '@/auth/auth-context';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { ScreenHeader } from '@/components/ui/screen-header';
import { serviceName } from '@/constants/catalog';
import { DEFAULT_PLACE, PLACES } from '@/constants/places';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { parseTNDToMillimes } from '@/utils/format';

/**
 * Inscription professionnelle (§6.1) : métiers et frais de déplacement connus à l'avance, zones couvertes,
 * point de départ. Le profil reste « en attente » jusqu'à la vérification des justificatifs par l'administration (§6.2).
 */
export default function ProOnboarding() {
  const { t, language } = useI18n();
  const s = t.proSpace.onboarding;
  const auth = useAuth();
  const services = useApiQuery('services', catalogApi.services);
  const areas = useApiQuery('areas', catalogApi.areas);
  const action = useAction();
  const [displayName, setDisplayName] = useState('');
  const [legalStatus, setLegalStatus] = useState<LegalStatus>('individual');
  /** code du service → frais de déplacement saisis (TND). */
  const [fees, setFees] = useState<Record<string, string>>({});
  const [areaCodes, setAreaCodes] = useState<string[]>([]);
  const [placeCode, setPlaceCode] = useState(DEFAULT_PLACE.code);

  const selected = Object.entries(fees).map(([serviceCode, fee]) => ({
    serviceCode,
    travelFeeMillimes: parseTNDToMillimes(fee),
  }));
  const valid =
    displayName.trim().length >= 2 &&
    selected.length > 0 &&
    selected.every((service) => service.travelFeeMillimes !== null) &&
    areaCodes.length > 0;

  const toggleService = (code: string, defaultFee: number | null) =>
    setFees((current) => {
      const next = { ...current };
      if (code in next) {
        delete next[code];
      } else {
        next[code] = String((defaultFee ?? 0) / 1000);
      }
      return next;
    });

  const toggleArea = (code: string) =>
    setAreaCodes((current) => (current.includes(code) ? current.filter((c) => c !== code) : [...current, code]));

  const submit = async () => {
    const place = PLACES.find((p) => p.code === placeCode) ?? DEFAULT_PLACE;
    const saved = await action.run(() =>
      proApi.saveProfile({
        displayName: displayName.trim(),
        legalStatus,
        services: selected.map((service) => ({ serviceCode: service.serviceCode, travelFeeMillimes: service.travelFeeMillimes ?? 0 })),
        serviceAreaCodes: areaCodes,
        baseLatitude: place.latitude,
        baseLongitude: place.longitude,
      }),
    );
    if (saved) {
      router.replace('/pro/profile');
    }
  };

  const backToClient = async () => {
    await auth.setSpace('client');
    router.replace('/client');
  };

  return (
    <Screen
      edges={['top', 'left', 'right', 'bottom']}
      header={<ScreenHeader title={s.title} onBack={backToClient} />}
      footer={
        <>
          <ErrorNotice error={action.error} />
          <Button label={s.submit} disabled={!valid} loading={action.pending} onPress={submit} />
        </>
      }>
      <Text style={Typography.caption}>{s.intro}</Text>
      {services.loading || areas.loading ? <LoadingView /> : null}
      <ErrorNotice error={services.error ?? areas.error} onRetry={services.refresh} />

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{s.displayName}</Text>
        <TextInput
          value={displayName}
          onChangeText={setDisplayName}
          placeholder={s.displayNamePlaceholder}
          placeholderTextColor={Colors.textMuted}
          maxLength={120}
          style={styles.input}
        />
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{s.legalStatus}</Text>
        <View style={styles.chips}>
          {(['individual', 'company'] as const).map((status) => (
            <Chip key={status} label={s.legal[status]} active={legalStatus === status} onPress={() => setLegalStatus(status)} />
          ))}
        </View>
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{s.services}</Text>
        <Text style={Typography.small}>{s.servicesHint}</Text>
        {services.data?.map((service) => {
          const active = service.code in fees;
          return (
            <View key={service.code} style={styles.serviceRow}>
              <Pressable
                accessibilityRole="checkbox"
                accessibilityState={{ checked: active }}
                onPress={() => toggleService(service.code, service.defaultTravelFeeMillimes)}
                style={styles.serviceToggle}>
                <Ionicons name={active ? 'checkbox' : 'square-outline'} size={22} color={active ? Colors.primary : Colors.textSecondary} />
                <Text style={Typography.body}>{serviceName(service, language)}</Text>
              </Pressable>
              {active ? (
                <TextInput
                  value={fees[service.code]}
                  onChangeText={(fee) => setFees((current) => ({ ...current, [service.code]: fee }))}
                  keyboardType="decimal-pad"
                  style={styles.feeInput}
                  accessibilityLabel={s.travelFee}
                />
              ) : null}
            </View>
          );
        })}
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{s.areas}</Text>
        <View style={styles.chips}>
          {areas.data?.map((area) => (
            <Chip key={area.code} label={area.name} active={areaCodes.includes(area.code)} onPress={() => toggleArea(area.code)} />
          ))}
        </View>
      </View>

      <View style={styles.block}>
        <Text style={Typography.bodyStrong}>{s.base}</Text>
        <Text style={Typography.small}>{s.baseHint}</Text>
        <View style={styles.chips}>
          {PLACES.map((place) => (
            <Chip
              key={place.code}
              label={language === 'ar' ? place.ar : place.fr}
              active={placeCode === place.code}
              onPress={() => setPlaceCode(place.code)}
            />
          ))}
        </View>
      </View>
    </Screen>
  );
}

function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      onPress={onPress}
      style={[styles.chip, active && styles.chipActive]}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  block: { gap: Spacing.sm },
  input: {
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md - 2,
    fontSize: 16,
    color: Colors.text,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  chip: {
    borderWidth: 1.5,
    borderColor: Colors.border,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 4,
    paddingVertical: 6,
  },
  chipActive: { borderColor: Colors.primary, backgroundColor: Colors.primarySoft },
  chipText: { fontSize: 14, fontWeight: '600', color: Colors.text },
  chipTextActive: { color: Colors.primaryDark },
  serviceRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, minHeight: 40 },
  serviceToggle: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: Spacing.sm },
  feeInput: {
    width: 90,
    borderWidth: 1,
    borderColor: Colors.primary,
    borderRadius: Radius.sm,
    paddingHorizontal: Spacing.sm,
    paddingVertical: 4,
    fontSize: 15,
    color: Colors.text,
    textAlign: 'right',
  },
});
