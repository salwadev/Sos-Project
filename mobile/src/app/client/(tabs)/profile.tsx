import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { LanguageSwitch } from '@/components/language-switch';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useI18n } from '@/i18n/i18n-context';
import { formatE164 } from '@/utils/format';

export default function ClientProfile() {
  const { t, rtl } = useI18n();
  const auth = useAuth();
  const phone = auth.status === 'signedIn' ? auth.session.phone : '';

  const openProSpace = async () => {
    await auth.setSpace('pro');
    router.replace('/pro');
  };

  return (
    <Screen>
      <Text style={Typography.title}>{t.profile.title}</Text>
      <View style={styles.identity}>
        <View style={styles.avatar}>
          <Ionicons name="person" size={32} color={Colors.primaryText} />
        </View>
        <View>
          <Text style={Typography.caption}>{t.profile.phone}</Text>
          <Text style={[Typography.heading, styles.ltr]}>{formatE164(phone)}</Text>
        </View>
      </View>

      <Card>
        <View style={styles.row}>
          <Text style={[Typography.bodyStrong, styles.grow]}>{t.profile.language}</Text>
          <LanguageSwitch />
        </View>
      </Card>
      <Card onPress={openProSpace}>
        <View style={styles.row}>
          <Ionicons name="briefcase-outline" size={22} color={Colors.primary} />
          <View style={styles.grow}>
            <Text style={Typography.bodyStrong}>{t.profile.proSpace}</Text>
            <Text style={Typography.caption}>{t.profile.proSpaceHint}</Text>
          </View>
          <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
        </View>
      </Card>

      <Button label={t.profile.logout} variant="outline" icon="log-out-outline" onPress={auth.signOut} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  identity: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  avatar: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: Colors.navySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ltr: { writingDirection: 'ltr' },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
});
