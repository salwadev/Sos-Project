import Ionicons from '@expo/vector-icons/Ionicons';
import { Redirect, Tabs } from 'expo-router';
import type { ComponentProps } from 'react';
import type { ColorValue } from 'react-native';

import { proApi } from '@/api/endpoints';
import { Colors } from '@/constants/theme';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { isMissingProfile } from '@/utils/pro';

type IconName = ComponentProps<typeof Ionicons>['name'];

function tabIcon(active: IconName, inactive: IconName) {
  return function TabIcon({ color, focused }: { color: ColorValue; focused: boolean }) {
    return <Ionicons name={focused ? active : inactive} size={24} color={typeof color === 'string' ? color : undefined} />;
  };
}

export default function ProTabsLayout() {
  const { t } = useI18n();
  const tabs = t.proSpace.tabs;
  const profile = useApiQuery('pro-profile', proApi.profile);

  // Compte sans profil professionnel : inscription d'abord (§6.1).
  if (isMissingProfile(profile.error)) {
    return <Redirect href="/pro/onboarding" />;
  }

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: Colors.textSecondary,
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
        tabBarStyle: { borderTopColor: Colors.border, height: 64, paddingTop: 6 },
      }}>
      <Tabs.Screen name="index" options={{ title: tabs.home, tabBarIcon: tabIcon('home', 'home-outline') }} />
      <Tabs.Screen name="missions" options={{ title: tabs.missions, tabBarIcon: tabIcon('list', 'list-outline') }} />
      <Tabs.Screen
        name="statements"
        options={{ title: tabs.statements, tabBarIcon: tabIcon('bar-chart', 'bar-chart-outline') }}
      />
      <Tabs.Screen name="profile" options={{ title: tabs.profile, tabBarIcon: tabIcon('person', 'person-outline') }} />
    </Tabs>
  );
}
