import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { SamuPill } from '@/components/emergency-banner';
import { Screen } from '@/components/screen';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { IconTile } from '@/components/ui/icon-tile';
import { Colors, Radius, Shadow, Spacing, Typography } from '@/constants/theme';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { categoryStyles, mockClient, mockRequest, popularServices } from '@/mocks/client-flow';
import type { CategoryCode } from '@/types/service-request';

const CATEGORIES: CategoryCode[] = ['home', 'car', 'health', 'other'];

export default function ClientHome() {
  const { t, rtl } = useI18n();
  const chevron = rtl ? 'chevron-back' : 'chevron-forward';

  return (
    <Screen>
      <View style={styles.topBar}>
        <Pressable accessibilityRole="button" style={styles.location}>
          <Ionicons name="location" size={22} color={Colors.primary} />
          <Text style={styles.locationText}>
            {mockClient.area} · {mockClient.district}
          </Text>
          <Ionicons name="chevron-down" size={16} color={Colors.text} />
        </Pressable>
        <View style={styles.topActions}>
          <View>
            <Ionicons name="notifications-outline" size={26} color={Colors.text} />
            <View style={styles.notifDot} />
          </View>
          <Avatar name={mockClient.firstName} size={40} />
        </View>
      </View>

      <View style={styles.greeting}>
        <Text style={Typography.body}>{interpolate(t.home.greeting, { name: mockClient.firstName })}</Text>
        <Text style={Typography.display}>{t.home.question}</Text>
      </View>

      <SamuPill />

      <Pressable accessibilityRole="search" style={styles.search}>
        <Ionicons name="search" size={22} color={Colors.textSecondary} />
        <Text style={styles.searchText}>{t.home.searchPlaceholder}</Text>
      </Pressable>

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

      <Pressable
        accessibilityRole="button"
        onPress={() => router.push('/client/search')}
        style={({ pressed }) => [styles.current, pressed && styles.pressed]}>
        <Text style={styles.currentTitle}>{t.home.currentRequest}</Text>
        <View style={styles.currentRow}>
          <IconTile icon="faucet" size={50} color={Colors.navy} background="#EAF4F8" />
          <View style={styles.currentBody}>
            <View style={styles.currentHeading}>
              <Text style={styles.currentName} numberOfLines={1}>
                {mockRequest.title}
              </Text>
              <Badge label={t.home.searching} tone="warning" />
            </View>
            <Text style={styles.currentMeta}>
              {t.services[mockRequest.service]} · {mockRequest.district}
            </Text>
          </View>
          <Ionicons name={chevron} size={20} color={Colors.primaryText} />
        </View>
      </Pressable>

      <View style={styles.sectionHeader}>
        <Text style={Typography.heading}>{t.home.popular}</Text>
        <Text style={styles.seeAll}>{t.home.seeAll}</Text>
      </View>
      <View style={styles.popular}>
        {popularServices.map((s) => (
          <Pressable
            key={s.code}
            accessibilityRole="button"
            onPress={() => router.push({ pathname: '/client/new-request', params: { category: 'home', service: s.code } })}
            style={({ pressed }) => [styles.service, pressed && styles.pressed]}>
            <MaterialCommunityIcons name={s.icon} size={32} color={s.color} />
            <Text style={styles.serviceLabel}>{t.services[s.code]}</Text>
          </Pressable>
        ))}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  location: { flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1 },
  locationText: { fontSize: 15, fontWeight: '500', color: Colors.text },
  topActions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  notifDot: {
    position: 'absolute',
    top: 1,
    right: 2,
    width: 9,
    height: 9,
    borderRadius: 5,
    backgroundColor: Colors.danger,
    borderWidth: 1.5,
    borderColor: Colors.background,
  },
  greeting: { gap: 2 },
  search: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm + 4,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.lg,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md - 2,
  },
  searchText: { fontSize: 16, color: Colors.textSecondary },
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
  seeAll: { color: Colors.textSecondary, fontSize: 13 },
  popular: { flexDirection: 'row', gap: Spacing.sm },
  service: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.sm,
    paddingVertical: Spacing.md,
    borderRadius: Radius.lg,
    borderWidth: 1,
    borderColor: Colors.border,
    backgroundColor: Colors.background,
    ...Shadow,
  },
  serviceLabel: { fontSize: 14, fontWeight: '600', color: Colors.text },
  pressed: { opacity: 0.85 },
});
