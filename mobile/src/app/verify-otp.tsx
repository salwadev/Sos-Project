import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { authApi, toE164 } from '@/api/endpoints';
import { useAuth, type Space } from '@/auth/auth-context';
import { ErrorNotice } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useI18n, useLtrRow } from '@/i18n/i18n-context';
import { formatPhone } from '@/utils/format';

const CODE_LENGTH = 6;

export default function VerifyOtp() {
  const { t } = useI18n();
  const ltrRow = useLtrRow();
  const { signIn } = useAuth();
  const { phone = '', space = 'client' } = useLocalSearchParams<{ phone?: string; space?: Space }>();
  const [code, setCode] = useState('');
  const [resent, setResent] = useState(false);
  const { run, pending, error } = useAction();

  const verify = async () => {
    // Jetons stockés dans le trousseau ; le compte client est créé au premier passage.
    const session = await run(() => signIn(toE164(phone), code, space === 'pro' ? 'pro' : 'client'));
    if (session) {
      router.replace(session.space === 'pro' ? '/pro' : '/client');
    } else {
      setCode('');
    }
  };

  const resend = async () => {
    setResent(false);
    const sent = await run(async () => {
      await authApi.requestOtp(toE164(phone));
      return true;
    });
    setResent(sent === true);
  };

  return (
    <Screen
      header={<ScreenHeader title={t.otp.title} onBack={() => router.back()} />}
      footer={<Button label={t.otp.submit} disabled={code.length !== CODE_LENGTH} loading={pending} onPress={verify} />}>
      <Text style={Typography.body}>
        {t.otp.subtitle} <Text style={styles.phone}>+216 {formatPhone(phone)}</Text>
      </Text>

      <View style={[styles.cells, { flexDirection: ltrRow }]}>
        {Array.from({ length: CODE_LENGTH }, (_, i) => (
          <View key={i} style={[styles.cell, i === code.length && styles.cellActive]}>
            <Text style={styles.digit}>{code[i] ?? ''}</Text>
          </View>
        ))}
        <TextInput
          value={code}
          onChangeText={(text) => setCode(text.replace(/\D/g, '').slice(0, CODE_LENGTH))}
          keyboardType="number-pad"
          autoComplete="one-time-code"
          textContentType="oneTimeCode"
          autoFocus
          style={styles.hiddenInput}
          accessibilityLabel={t.otp.title}
        />
      </View>

      <ErrorNotice error={error} />

      <Pressable accessibilityRole="button" onPress={resend} disabled={pending} hitSlop={8}>
        <Text style={styles.resend}>{resent ? t.otp.resent : t.otp.resend}</Text>
      </Pressable>
      {__DEV__ ? <Text style={styles.hint}>{t.otp.devHint}</Text> : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  phone: { fontWeight: '700', writingDirection: 'ltr' },
  cells: { justifyContent: 'space-between', gap: Spacing.sm },
  cell: {
    flex: 1,
    aspectRatio: 0.85,
    borderWidth: 1.5,
    borderColor: Colors.border,
    borderRadius: Radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cellActive: { borderColor: Colors.primary },
  digit: { fontSize: 24, fontWeight: '700', color: Colors.text },
  hiddenInput: { ...StyleSheet.absoluteFill, opacity: 0.02, color: 'transparent' },
  resend: { color: Colors.primaryDark, fontWeight: '600', textAlign: 'center' },
  hint: { ...Typography.small, textAlign: 'center' },
});
