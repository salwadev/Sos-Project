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

export default function ClientTabsLayout() {
  const { t } = useI18n();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: Colors.textSecondary,
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
        tabBarStyle: { borderTopColor: Colors.border, height: 64, paddingTop: 6 },
      }}>
      <Tabs.Screen name="index" options={{ title: t.tabs.home, tabBarIcon: tabIcon('home', 'home-outline') }} />
      <Tabs.Screen
        name="requests"
        options={{ title: t.tabs.requests, tabBarIcon: tabIcon('document-text', 'document-text-outline') }}
      />
      <Tabs.Screen name="help" options={{ title: t.tabs.help, tabBarIcon: tabIcon('help-circle', 'help-circle-outline') }} />
      <Tabs.Screen name="profile" options={{ title: t.tabs.profile, tabBarIcon: tabIcon('person', 'person-outline') }} />
    </Tabs>
  );
}
