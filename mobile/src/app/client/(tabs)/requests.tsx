import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { mockRequest } from '@/mocks/client-flow';
import { formatTND } from '@/utils/format';

export default function ClientRequests() {
  const { t, rtl } = useI18n();

  return (
    <Screen>
      <Text style={Typography.title}>{t.requests.title}</Text>

      <Text style={styles.section}>{t.requests.inProgress}</Text>
      <Card onPress={() => router.push('/client/search')}>
        <View style={styles.row}>
          <IconTile icon="faucet" color={Colors.navy} background={Colors.primarySoft} />
          <View style={styles.body}>
            <Text style={Typography.bodyStrong}>{mockRequest.title}</Text>
            <Text style={Typography.caption}>
              {t.services[mockRequest.service]} · {mockRequest.district} · #{mockRequest.reference}
            </Text>
          </View>
          <Badge label={t.home.searching} tone="warning" />
          <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
        </View>
      </Card>

      <Text style={styles.section}>{t.requests.history}</Text>
      <Card>
        <View style={styles.row}>
          <IconTile icon="key-variant" color={Colors.navy} background={Colors.surface} />
          <View style={styles.body}>
            <Text style={Typography.bodyStrong}>{t.services.locksmith}</Text>
            <Text style={Typography.caption}>Carthage · #UT-0247 · {formatTND(60)}</Text>
          </View>
          <Badge label={t.requests.completed} tone="success" />
        </View>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  section: { ...Typography.caption, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  body: { flex: 1, gap: 2 },
});
