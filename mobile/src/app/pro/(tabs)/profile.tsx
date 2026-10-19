import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { LanguageSwitch } from '@/components/language-switch';
import { Screen } from '@/components/screen';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { interpolate } from '@/i18n';
import { useI18n } from '@/i18n/i18n-context';
import { mockProUser, proDemo } from '@/mocks/pro-flow';

export default function ProProfile() {
  const { t } = useI18n();
  const s = t.proSpace.profile;

  const logout = () => {
    proDemo.reset();
    router.replace('/');
  };

  return (
    <Screen>
      <Text style={Typography.title}>{s.title}</Text>
      <View style={styles.identity}>
        <Avatar name={mockProUser.name} size={64} verified />
        <View>
          <Text style={Typography.heading}>{mockProUser.name}</Text>
          <Text style={Typography.caption}>{t.services[mockProUser.trade]}</Text>
        </View>
      </View>

      <Card tone="primarySoft">
        <View style={styles.row}>
          <Ionicons name="shield-checkmark" size={26} color={Colors.primary} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{s.verified}</Text>
            <Text style={Typography.caption}>{interpolate(s.documentsValid, { date: mockProUser.documentsValidUntil })}</Text>
          </View>
        </View>
      </Card>

      <Card>
        <Text style={Typography.bodyStrong}>{s.zones}</Text>
        <View style={styles.chips}>
          {mockProUser.zones.map((zone) => (
            <View key={zone} style={styles.chip}>
              <Ionicons name="location-outline" size={14} color={Colors.primaryDark} />
              <Text style={styles.chipText}>{zone}</Text>
            </View>
          ))}
        </View>
      </Card>

      <Card>
        <View style={styles.row}>
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.language}</Text>
          <LanguageSwitch />
        </View>
      </Card>

      <Button label={s.logout} variant="outline" icon="log-out-outline" onPress={logout} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  identity: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm, marginTop: Spacing.sm },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: Colors.primarySoft,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 5,
  },
  chipText: { fontSize: 13, color: Colors.primaryDark, fontWeight: '600' },
});
