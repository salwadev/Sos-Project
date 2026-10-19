import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, Text, View } from 'react-native';

import { EmergencyBanner } from '@/components/emergency-banner';
import { Screen } from '@/components/screen';
import { Card } from '@/components/ui/card';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';

export default function ClientHelp() {
  const { t } = useI18n();

  return (
    <Screen>
      <Text style={Typography.title}>{t.help.title}</Text>
      <EmergencyBanner />
      <Card>
        <View style={styles.row}>
          <Ionicons name="chatbubble-ellipses-outline" size={26} color={Colors.primary} />
          <View style={styles.body}>
            <Text style={Typography.bodyStrong}>{t.help.support}</Text>
            <Text style={Typography.caption}>{t.help.supportHint}</Text>
          </View>
        </View>
      </Card>
      <Card>
        <View style={styles.row}>
          <Ionicons name="flag-outline" size={26} color={Colors.danger} />
          <Text style={[Typography.bodyStrong, styles.body]}>{t.help.report}</Text>
        </View>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  body: { flex: 1, gap: 2 },
});
