import Ionicons from '@expo/vector-icons/Ionicons';
import { Tabs } from 'expo-router';
import type { ComponentProps } from 'react';
import type { ColorValue } from 'react-native';

import { Colors } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';

type IconName = ComponentProps<typeof Ionicons>['name'];

function tabIcon(active: IconName, inactive: IconName) {
  return function TabIcon({ color, focused }: { color: ColorValue; focused: boolean }) {
    return <Ionicons name={focused ? active : inactive} size={24} color={typeof color === 'string' ? color : undefined} />;
  };
}

export default function ProTabsLayout() {
  const { t } = useI18n();
  const tabs = t.proSpace.tabs;

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
