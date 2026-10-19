import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';

import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { ScreenHeader } from '@/components/ui/screen-header';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useI18n, useLtrRow } from '@/i18n/i18n-context';
import { formatPhone } from '@/utils/format';

const CODE_LENGTH = 6;

export default function VerifyOtp() {
  const { t } = useI18n();
  const ltrRow = useLtrRow();
  const { phone = '' } = useLocalSearchParams<{ phone?: string }>();
  const [code, setCode] = useState('');

  const verify = () => {
    // POST /api/auth/verify-otp puis stockage des jetons (token-storage). Démo : tout code à 6 chiffres passe.
    router.replace('/client');
  };

  return (
    <Screen
      header={<ScreenHeader title={t.otp.title} onBack={() => router.back()} />}
      footer={<Button label={t.otp.submit} disabled={code.length !== CODE_LENGTH} onPress={verify} />}>
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

      <Text style={styles.resend}>{t.otp.resend}</Text>
      <Text style={styles.hint}>{t.otp.demoHint}</Text>
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
