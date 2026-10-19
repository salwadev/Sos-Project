import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { BrandLogo } from '@/components/brand-logo';
import { TunisiaFlag } from '@/components/tunisia-flag';
import { LanguageSwitch } from '@/components/language-switch';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { WelcomeIllustration } from '@/components/welcome-illustration';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n, useLtrRow } from '@/i18n/i18n-context';
import { formatPhone } from '@/utils/format';

const PHONE_DIGITS = 8;


export default function Welcome() {
  const { t } = useI18n();
  const ltrRow = useLtrRow();
  const [digits, setDigits] = useState('');
  const valid = digits.length === PHONE_DIGITS;

  const submit = () => {
    // POST /api/auth/request-otp — réponse identique que le numéro soit inscrit ou non (§5.1).
    router.push({ pathname: '/verify-otp', params: { phone: digits } });
  };

  return (
    <Screen>
      <LanguageSwitch />
      <View style={styles.logo}>
        <BrandLogo size={52} />
      </View>
      <WelcomeIllustration height={220} />

      <View style={styles.hero}>
        <Text style={styles.headline}>{t.welcome.headline}</Text>
        <Text style={styles.tagline}>{t.welcome.tagline}</Text>
      </View>

      <View style={styles.field}>
        <Text style={Typography.bodyStrong}>{t.welcome.phoneLabel}</Text>
        <View style={[styles.phoneRow, { flexDirection: ltrRow }]}>
          <View style={styles.prefix}>
            <TunisiaFlag width={26} />
            <Text style={styles.prefixText}>+216</Text>
            <Ionicons name="chevron-down" size={16} color={Colors.text} />
          </View>
          <TextInput
            value={formatPhone(digits)}
            onChangeText={(text) => setDigits(text.replace(/\D/g, '').slice(0, PHONE_DIGITS))}
            placeholder={t.welcome.phonePlaceholder}
            placeholderTextColor={Colors.textMuted}
            keyboardType="phone-pad"
            autoComplete="tel"
            textContentType="telephoneNumber"
            style={styles.input}
          />
        </View>
      </View>

      <Button label={t.welcome.submit} disabled={!valid} onPress={submit} />

      <Text style={styles.terms}>
        {t.welcome.termsPrefix}
        <Text style={styles.link}>{t.welcome.termsLink}</Text>.
      </Text>

      <View style={styles.trust}>
        <Ionicons name="shield-checkmark" size={34} color={Colors.primary} />
        <View>
          <Text style={styles.trustTitle}>{t.welcome.trustTitle}</Text>
          <Text style={Typography.small}>{t.welcome.trustSubtitle}</Text>
        </View>
      </View>

      <Pressable accessibilityRole="link" onPress={() => router.push('/pro')} style={styles.proLink}>
        <Text style={styles.proLinkText}>{t.welcome.proLink}</Text>
      </Pressable>
    </Screen>
  );
}

const styles = StyleSheet.create({
  logo: { alignItems: 'center' },
  hero: { alignItems: 'center', gap: Spacing.xs },
  headline: { ...Typography.display, textAlign: 'center' },
  tagline: { fontSize: 17, color: Colors.primaryDark, textAlign: 'center' },
  field: { gap: Spacing.sm },
  phoneRow: {
    flexDirection: 'row',
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    overflow: 'hidden',
  },
  prefix: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: Spacing.md,
    borderRightWidth: 1,
    borderRightColor: Colors.border,
  },

  prefixText: { fontSize: 16, fontWeight: '600', color: Colors.text },
  input: { flex: 1, fontSize: 17, paddingHorizontal: Spacing.md, paddingVertical: Spacing.md, color: Colors.text },
  terms: { ...Typography.caption, textAlign: 'center' },
  link: { color: Colors.primaryDark, textDecorationLine: 'underline' },
  trust: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: Spacing.sm + 4 },
  trustTitle: { fontSize: 14, fontWeight: '600', color: Colors.text },
  proLink: { alignItems: 'center', paddingVertical: Spacing.xs },
  proLinkText: { color: Colors.primaryDark, fontSize: 13, fontWeight: '600' },
});
