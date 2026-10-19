import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { catalogApi, interventionsApi, requestsApi } from '@/api/endpoints';
import { SamuPill } from '@/components/emergency-banner';
import { ErrorNotice } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { IconTile } from '@/components/ui/icon-tile';
import { categoryStyles, findService, POPULAR_SERVICE_CODES, serviceIcon, serviceName } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Radius, Shadow, Spacing, Typography } from '@/constants/theme';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import type { CategoryCode } from '@/types/service-request';
import { clientRequestHref, interventionFor, isActiveRequest } from '@/utils/request-routing';

const CATEGORIES: CategoryCode[] = ['home', 'car', 'health', 'other'];

export default function ClientHome() {
  const { t, rtl, language } = useI18n();
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';
  const services = useApiQuery('services', catalogApi.services);
  const requests = useApiQuery('requests', requestsApi.list, POLL_INTERVAL_MS);
  const interventions = useApiQuery('interventions', interventionsApi.list, POLL_INTERVAL_MS);

  const current = requests.data?.find(isActiveRequest);
  const currentService = current ? findService(services.data, current.service) : undefined;
  const popular = POPULAR_SERVICE_CODES.map((code) => findService(services.data, code)).filter((s) => s !== undefined);

  return (
    <Screen>
      <View style={styles.topBar}>
        <View style={styles.location}>
          <Ionicons name="location" size={22} color={Colors.primary} />
          <Text style={styles.locationText}>{t.home.coverage}</Text>
        </View>
      </View>

      <View style={styles.greeting}>
        <Text style={Typography.body}>{t.home.greeting}</Text>
        <Text style={Typography.display}>{t.home.question}</Text>
      </View>

      <SamuPill />

      <ErrorNotice error={services.error ?? requests.error} 
        onRetry={() => {
          services.refresh();
          requests.refresh();
        }}
      />

      <View style={styles.grid}>
        {CATEGORIES.map((code) => {
          const c = categoryStyles[code];
          return (
            <Pressable
              key={code}
              accessibilityRole="button"
              onPress={() => router.push({ pathname: '/client/new-request', params: { category: code } })}
              style={({ pressed }) => [styles.category, { backgroundColor: c.background }, pressed && styles.pressed]}>
              <View style={styles.categoryTop}>
                <MaterialCommunityIcons name={c.icon} size={36} color={c.color} />
                <Ionicons name={chevron} size={18} color={Colors.textSecondary} />
              </View>
              <Text style={styles.categoryLabel}>{t.categories[code]}</Text>
            </Pressable>
          );
        })}
      </View>

      {current ? (
        <Pressable
          accessibilityRole="button"
          onPress={() => router.push(clientRequestHref(current, interventionFor(interventions.data, current.id)))}
          style={({ pressed }) => [styles.current, pressed && styles.pressed]}>
          <Text style={styles.currentTitle}>{t.home.currentRequest}</Text>
          <View style={styles.currentRow}>
            <IconTile icon={serviceIcon(currentService?.code)} size={50} color={Colors.navy} background="#EAF4F8" />
            <View style={styles.currentBody}>
              <View style={styles.currentHeading}>
                <Text style={styles.currentName} numberOfLines={1}>
                  {currentService ? serviceName(currentService, language) : current.reference}
                </Text>
                <Badge label={t.requestStatus[current.status]} tone="warning" />
              </View>
              <Text style={styles.currentMeta} numberOfLines={1}>
                #{current.reference} · {current.description}
              </Text>
            </View>
            <Ionicons name={chevron} size={20} color={Colors.primaryText} />
          </View>
        </Pressable>
      ) : null}

      {popular.length > 0 ? (
        <>
          <View style={styles.sectionHeader}>
            <Text style={Typography.heading}>{t.home.popular}</Text>
          </View>
          <View style={styles.popular}>
            {popular.map((s) => (
              <Pressable
                key={s.code}
                accessibilityRole="button"
                onPress={() =>
                  router.push({ pathname: '/client/new-request', params: { category: s.category.code, service: s.code } })
                }
                style={({ pressed }) => [styles.service, pressed && styles.pressed]}>
                <MaterialCommunityIcons name={serviceIcon(s.code)} size={32} color={Colors.navy} />
                <Text style={styles.serviceLabel} numberOfLines={1}>
                  {serviceName(s, language)}
                </Text>
              </Pressable>
            ))}
          </View>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  location: { flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1 },
  locationText: { fontSize: 15, fontWeight: '500', color: Colors.text },
  greeting: { gap: 2 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  category: {
    flexBasis: '47%',
    flexGrow: 1,
    borderRadius: Radius.lg,
    padding: Spacing.md,
    minHeight: 104,
    justifyContent: 'space-between',
  },
  categoryTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  categoryLabel: { fontSize: 16, fontWeight: '700', color: Colors.text },
  current: { backgroundColor: Colors.navy, borderRadius: Radius.lg, padding: Spacing.md, gap: Spacing.sm + 4 },
  currentTitle: { color: Colors.primaryText, fontSize: 17, fontWeight: '700' },
  currentRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  currentBody: { flex: 1, gap: 4 },
  currentHeading: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, flexWrap: 'wrap' },
  currentName: { color: Colors.primaryText, fontSize: 16, fontWeight: '700' },
  currentMeta: { color: '#B9CBD4', fontSize: 13 },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: Spacing.xs },
  popular: { flexDirection: 'row', gap: Spacing.sm },
  service: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.sm,
    paddingVertical: Spacing.md,
    paddingHorizontal: Spacing.xs,
    borderRadius: Radius.lg,
    borderWidth: 1,
    borderColor: Colors.border,
    backgroundColor: Colors.background,
    ...Shadow,
  },
  serviceLabel: { fontSize: 14, fontWeight: '600', color: Colors.text },
  pressed: { opacity: 0.85 },
});
