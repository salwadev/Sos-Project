import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { LanguageSwitch } from '@/components/language-switch';
import { Screen } from '@/components/screen';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockClient } from '@/mocks/client-flow';

export default function ClientProfile() {
  const { t } = useI18n();

  return (
    <Screen>
      <Text style={Typography.title}>{t.profile.title}</Text>
      <View style={styles.identity}>
        <Avatar name={mockClient.firstName} size={64} />
        <View>
          <Text style={Typography.heading}>{mockClient.firstName}</Text>
          <Text style={Typography.caption}>+216 •• ••• •••</Text>
        </View>
      </View>

      <Card>
        <View style={styles.row}>
          <Text style={[Typography.bodyStrong, styles.grow]}>{t.profile.language}</Text>
          <LanguageSwitch />
        </View>
      </Card>
      <Card>
        <View style={styles.row}>
          <Ionicons name="location-outline" size={22} color={Colors.primary} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{t.profile.addresses}</Text>
            <Text style={Typography.caption}>{mockClient.address}</Text>
          </View>
        </View>
      </Card>

      <Button label={t.profile.logout} variant="outline" icon="log-out-outline" onPress={() => router.replace('/')} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  identity: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
});
